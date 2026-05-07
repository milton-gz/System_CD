<?php
session_start();
require_once "../config/conexion.php";

// Verificar sesión y rol
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'recepcion') {
    switch ($_SESSION['rol'] ?? '') {
        case "admin":
            header("Location: admin.php");
            break;
        case "doctor":
            header("Location: doctor.php");
            break;
        case "paciente":
            header("Location: paciente.php");
            break;
        default:
            session_destroy();
            header("Location: ../login.php");
            break;
    }
    exit();
}

if (!isset($_SESSION['nombre'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$nombreRecepcion = $_SESSION['nombre'];
$mensaje = "";
$error = "";
$busquedaReceta = trim($_GET['buscar_receta'] ?? "");

// Funciones auxiliares
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function estadoBadge($estado) {
    $map = [
        "pendiente" => "bg-yellow-100 text-yellow-700",
        "confirmada" => "bg-green-100 text-green-700",
        "atendida" => "bg-blue-100 text-blue-700",
        "cancelada" => "bg-red-100 text-red-700"
    ];
    return $map[$estado] ?? "bg-gray-100 text-gray-700";
}

function tipoTexto($tipo) {
    $map = [
        "limpieza" => "Limpieza dental",
        "revision" => "Revision general",
        "emergencia" => "Emergencia",
        "otros" => "Otros"
    ];
    return $map[$tipo] ?? ucfirst((string) $tipo);
}

// Generador de contraseña aleatoria
function generarPassword($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $password;
}

// Envío de correo (función simple con mail(), ajusta según tu servidor)
function enviarCredenciales($correo, $nombre, $passwordPlano) {
    $asunto = "Bienvenido a Dental Guru - Tus credenciales de acceso";
    $mensaje = "Hola $nombre,\n\n";
    $mensaje .= "Tu cuenta ha sido creada exitosamente en el sistema Dental Guru.\n";
    $mensaje .= "Tu correo de acceso es: $correo\n";
    $mensaje .= "Tu contraseña temporal es: $passwordPlano\n\n";
    $mensaje .= "Por seguridad, cambia tu contraseña después de iniciar sesión.\n";
    $mensaje .= "Accede al sistema: http://tudominio.com/login.php\n\n";
    $mensaje .= "Saludos,\nEquipo Dental Guru";
    $cabeceras = "From: no-reply@dentalguru.com\r\n";
    return mail($correo, $asunto, $mensaje, $cabeceras);
}

// Procesar acciones POST (crear usuario, agendar cita, cancelar cita)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // ---------- CREAR NUEVO PACIENTE (USUARIO) ----------
    if ($accion === "crear_paciente") {
        $nombre = trim($_POST["nombre"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $telefono = trim($_POST["telefono"] ?? "");
        $edad = trim($_POST["edad"] ?? "");
        $tipoSangre = trim($_POST["tipo_sangre"] ?? "");
        $alergias = trim($_POST["alergias"] ?? "");

        // Validaciones básicas
        if ($nombre === "" || $correo === "") {
            $error = "Nombre y correo son obligatorios.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Correo electrónico no válido.";
        } else {
            // Verificar si el correo ya existe
            $stmtCheck = $conn->prepare("SELECT id_usuario FROM USUARIO WHERE correo = ?");
            $stmtCheck->bind_param("s", $correo);
            $stmtCheck->execute();
            $existe = $stmtCheck->get_result()->fetch_assoc();
            if ($existe) {
                $error = "Ya existe un usuario con ese correo electrónico.";
            } else {
                // Generar contraseña aleatoria y hashearla
                $passwordPlano = generarPassword(8);
                $passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);
                $rol = 'paciente';
                $estado = 'activo';

                // Insertar en USUARIO
                $stmt = $conn->prepare("INSERT INTO USUARIO (nombre, correo, password, rol, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nombre, $correo, $passwordHash, $rol, $estado);
                if ($stmt->execute()) {
                    $idUsuario = $conn->insert_id;

                    // Insertar en PACIENTE
                    $stmt2 = $conn->prepare("INSERT INTO PACIENTE (USUARIO_id_usuario, telefono, edad, tipo_sangre, alergias) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("issss", $idUsuario, $telefono, $edad, $tipoSangre, $alergias);
                    if ($stmt2->execute()) {
                        // Enviar correo con credenciales
                        if (enviarCredenciales($correo, $nombre, $passwordPlano)) {
                            $mensaje = "Paciente creado exitosamente. Se enviaron las credenciales al correo $correo.";
                        } else {
                            $mensaje = "Paciente creado, pero no se pudo enviar el correo. Contraseña generada: $passwordPlano (anótala).";
                        }
                    } else {
                        $error = "Error al crear el registro en PACIENTE.";
                        // Opcional: eliminar usuario insertado
                        $conn->query("DELETE FROM USUARIO WHERE id_usuario = $idUsuario");
                    }
                } else {
                    $error = "Error al crear el usuario: " . $conn->error;
                }
            }
        }
    }

    // ---------- AGENDAR CITA (para cualquier paciente) ----------
    if ($accion === "agendar_cita") {
        date_default_timezone_set('America/El_Salvador');
        $pacienteId = (int)($_POST["paciente_id"] ?? 0);
        $fecha = trim($_POST["fecha"] ?? "");
        $hora = trim($_POST["hora"] ?? "");
        $tipo = trim($_POST["tipo"] ?? "");
        $doctorId = trim($_POST["doctor"] ?? "");
        $observaciones = trim($_POST["observaciones"] ?? "");
        $tiposValidos = ["limpieza", "revision", "emergencia", "otros"];

        if ($pacienteId <= 0 || $fecha === "" || $hora === "" || !in_array($tipo, $tiposValidos)) {
            $error = "Completa todos los campos obligatorios (paciente, fecha, hora, tipo).";
        } else {
            // Validar fecha no pasada
            if ($fecha < date("Y-m-d")) {
                $error = "La fecha no puede ser anterior a hoy.";
            } else {
                $fechaHoraSeleccionada = strtotime("$fecha $hora");
                if ($fechaHoraSeleccionada <= time()) {
                    $error = "No puedes agendar en una hora pasada.";
                } else {
                    // Verificar disponibilidad
                    if ($doctorId !== "") {
                        $stmtCheck = $conn->prepare("
                            SELECT COUNT(*) as total FROM CITA 
                            WHERE fecha = ? AND hora = ? AND DOCTOR_id_doctor = ? 
                            AND estado IN ('pendiente','confirmada')
                        ");
                        $doctorParam = (int)$doctorId;
                        $stmtCheck->bind_param("ssi", $fecha, $hora, $doctorParam);
                    } else {
                        $stmtCheck = $conn->prepare("
                            SELECT COUNT(*) as total FROM CITA 
                            WHERE fecha = ? AND hora = ? AND estado IN ('pendiente','confirmada')
                        ");
                        $stmtCheck->bind_param("ss", $fecha, $hora);
                    }
                    $stmtCheck->execute();
                    $resCheck = $stmtCheck->get_result()->fetch_assoc();
                    if ($resCheck['total'] > 0) {
                        $error = "Horario no disponible.";
                    } else {
                        $doctorParam = $doctorId !== "" ? (int)$doctorId : null;
                        $stmt = $conn->prepare("
                            INSERT INTO CITA (PACIENTE_id_paciente, DOCTOR_id_doctor, fecha, hora, tipo, estado, observaciones)
                            VALUES (?, ?, ?, ?, ?, 'pendiente', ?)
                        ");
                        $stmt->bind_param("iissss", $pacienteId, $doctorParam, $fecha, $hora, $tipo, $observaciones);
                        if ($stmt->execute()) {
                            $mensaje = "Cita agendada correctamente (estado pendiente).";
                        } else {
                            $error = "No se pudo agendar la cita.";
                        }
                    }
                }
            }
        }
    }

    // ---------- CANCELAR CITA ----------
    if ($accion === "cancelar_cita") {
        $idCita = (int)($_POST["id_cita"] ?? 0);
        $stmt = $conn->prepare("SELECT estado FROM CITA WHERE id_cita = ? AND estado IN ('pendiente','confirmada')");
        $stmt->bind_param("i", $idCita);
        $stmt->execute();
        $citaActual = $stmt->get_result()->fetch_assoc();
        if ($citaActual) {
            $stmtUp = $conn->prepare("UPDATE CITA SET estado = 'cancelada' WHERE id_cita = ?");
            $stmtUp->bind_param("i", $idCita);
            if ($stmtUp->execute()) {
                $historial = $conn->prepare("INSERT INTO HISTORIAL_CITA (CITA_id_cita, estado_anterior, estado_nuevo) VALUES (?, ?, 'cancelada')");
                $historial->bind_param("is", $idCita, $citaActual["estado"]);
                $historial->execute();
                $mensaje = "Cita cancelada correctamente.";
            } else {
                $error = "No se pudo cancelar la cita.";
            }
        } else {
            $error = "Solo se pueden cancelar citas pendientes o confirmadas.";
        }
    }
}

// Obtener listado de pacientes (para el select de agendar cita)
$pacientes = [];
$resPac = $conn->query("
    SELECT P.id_paciente, U.nombre, U.correo 
    FROM PACIENTE P 
    JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario 
    WHERE U.estado = 'activo' 
    ORDER BY U.nombre
");
if ($resPac) {
    while ($row = $resPac->fetch_assoc()) {
        $pacientes[] = $row;
    }
}

// Obtener listado de doctores activos
$doctores = [];
$resDoc = $conn->query("
    SELECT D.id_doctor, U.nombre, D.especialidad
    FROM DOCTOR D
    JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
    WHERE D.estado = 'activo'
    ORDER BY U.nombre
");
if ($resDoc) {
    while ($row = $resDoc->fetch_assoc()) {
        $doctores[] = $row;
    }
}

// Listado de todas las citas (para recepción)
$citas = [];
$stmtCitas = $conn->prepare("
    SELECT C.*, 
           U_pac.nombre AS paciente_nombre,
           U_doc.nombre AS doctor_nombre,
           D.especialidad
    FROM CITA C
    JOIN PACIENTE P ON P.id_paciente = C.PACIENTE_id_paciente
    JOIN USUARIO U_pac ON U_pac.id_usuario = P.USUARIO_id_usuario
    LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor
    LEFT JOIN USUARIO U_doc ON U_doc.id_usuario = D.USUARIO_id_usuario
    ORDER BY C.fecha DESC, C.hora DESC
");
$stmtCitas->execute();
$citas = $stmtCitas->get_result()->fetch_all(MYSQLI_ASSOC);

// Búsqueda de recetas (para recepción)
$recetas = [];
if ($busquedaReceta !== "") {
    $like = "%" . $busquedaReceta . "%";
    $stmtRec = $conn->prepare("
        SELECT R.*, E.diagnostico, E.tratamiento, U_pac.nombre AS paciente_nombre, U_doc.nombre AS doctor_nombre
        FROM RECETA R
        JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente
        JOIN PACIENTE P ON P.id_paciente = E.PACIENTE_id_paciente
        JOIN USUARIO U_pac ON U_pac.id_usuario = P.USUARIO_id_usuario
        LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor
        LEFT JOIN USUARIO U_doc ON U_doc.id_usuario = D.USUARIO_id_usuario
        WHERE (R.medicamento LIKE ? OR R.dosis LIKE ? OR R.indicaciones LIKE ? OR E.diagnostico LIKE ?)
        ORDER BY R.fecha DESC
    ");
    $stmtRec->bind_param("ssss", $like, $like, $like, $like);
} else {
    $stmtRec = $conn->prepare("
        SELECT R.*, E.diagnostico, E.tratamiento, U_pac.nombre AS paciente_nombre, U_doc.nombre AS doctor_nombre
        FROM RECETA R
        JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente
        JOIN PACIENTE P ON P.id_paciente = E.PACIENTE_id_paciente
        JOIN USUARIO U_pac ON U_pac.id_usuario = P.USUARIO_id_usuario
        LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor
        LEFT JOIN USUARIO U_doc ON U_doc.id_usuario = D.USUARIO_id_usuario
        ORDER BY R.fecha DESC
    ");
}
$stmtRec->execute();
$recetas = $stmtRec->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Guru | Recepción</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>
<style>
:root{--primary:#b18ddd;--secondary-soft:#e4f3ea;}
body{background:var(--secondary-soft);}
.card-ui{background:white;border-radius:20px;box-shadow:0 8px 18px rgba(0,0,0,.06);}
.badge-fix{display:inline-flex;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;}
.team-tooltip{position:absolute;bottom:120%;left:50%;transform:translateX(-50%) translateY(10px);background:white;padding:12px;border-radius:14px;opacity:0;pointer-events:none;transition:all .25s;}
.team-container:hover .team-tooltip{opacity:1;transform:translateX(-50%) translateY(0);}
.recipe-toggle{background:#6FAE84;color:white;border-radius:999px;padding:8px 14px;font-size:12px;}
</style>
</head>
<body>
<header class="bg-[#b18ddd] px-6 py-3 flex items-center justify-between">
<img src="../assets/logo.png" class="w-28 rounded-xl shadow" alt="Dental Guru">
<div class="flex items-center gap-4">
<span class="text-sm font-semibold">Recepcionista: <?php echo e($nombreRecepcion); ?></span>
<a href="../config/cerrar_sesion.php" class="text-xs font-semibold hover:underline">Cerrar sesión</a>
</div>
</header>

<main class="max-w-7xl mx-auto p-4 md:p-6">
<div class="flex flex-col md:flex-row md:justify-between gap-3 mb-6">
<h2 class="text-2xl font-bold">Panel de Recepción</h2>
<div class="flex gap-2 flex-wrap">
<a href="#crear" class="btn-main text-sm">+ Nuevo Paciente</a>
<a href="#agendar" class="btn-main text-sm">Agendar Cita</a>
<a href="#recetas" class="btn-outline text-sm">Buscar Recetas</a>
</div>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl bg-green-50 text-green-700 border border-green-200"><?php echo e($mensaje); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 p-3 rounded-2xl bg-red-50 text-red-700 border border-red-200"><?php echo e($error); ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-5">
    <!-- Columna izquierda: formularios -->
    <div class="space-y-5">
        <!-- Formulario crear paciente -->
        <div id="crear" class="card-ui p-5">
            <h3 class="font-bold mb-4">Registrar nuevo paciente</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_paciente">
                <div class="space-y-3">
                    <input type="text" name="nombre" placeholder="Nombre completo *" class="input-ui w-full" required>
                    <input type="email" name="correo" placeholder="Correo electrónico *" class="input-ui w-full" required>
                    <input type="tel" name="telefono" placeholder="Teléfono" class="input-ui w-full">
                    <input type="number" name="edad" placeholder="Edad" class="input-ui w-full">
                    <input type="text" name="tipo_sangre" placeholder="Tipo de sangre" class="input-ui w-full">
                    <textarea name="alergias" rows="2" placeholder="Alergias" class="input-ui w-full"></textarea>
                    <button class="btn-main w-full">Crear paciente (envía correo con contraseña)</button>
                </div>
            </form>
            <p class="text-xs text-gray-500 mt-3">La contraseña se generará automáticamente y se enviará al correo.</p>
        </div>

        <!-- Formulario agendar cita -->
        <div id="agendar" class="card-ui p-5">
            <h3 class="font-bold mb-4">Agendar cita para paciente</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="agendar_cita">
                <div class="space-y-3">
                    <select name="paciente_id" class="input-ui w-full" required>
                        <option value="">Seleccionar paciente</option>
                        <?php foreach ($pacientes as $pac): ?>
                            <option value="<?php echo $pac['id_paciente']; ?>"><?php echo e($pac['nombre']); ?> (<?php echo e($pac['correo']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="fecha" class="input-ui w-full" required min="<?php echo date("Y-m-d"); ?>">
                    <input type="time" name="hora" class="input-ui w-full" required step="1800">
                    <select name="tipo" class="input-ui w-full" required>
                        <option value="">Tipo de cita</option>
                        <option value="limpieza">Limpieza dental</option>
                        <option value="revision">Revisión general</option>
                        <option value="emergencia">Emergencia</option>
                        <option value="otros">Otros</option>
                    </select>
                    <select name="doctor" class="input-ui w-full">
                        <option value="">Cualquier doctor (opcional)</option>
                        <?php foreach ($doctores as $doc): ?>
                            <option value="<?php echo $doc['id_doctor']; ?>"><?php echo e($doc['nombre']); ?> (<?php echo e($doc['especialidad']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="observaciones" rows="2" placeholder="Observaciones" class="input-ui w-full"></textarea>
                    <button class="btn-main w-full">Agendar cita</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Columna derecha: listado de citas y búsqueda de recetas -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Listado de citas (todas) -->
        <div class="card-ui p-5">
            <h3 class="font-bold mb-4">Todas las citas</h3>
            <input type="text" id="searchAppointment" class="input-ui w-full mb-3 text-sm" placeholder="Buscar por paciente, doctor, tipo o estado">
            <div class="space-y-3" id="appointmentList">
                <?php if (empty($citas)): ?>
                    <div class="border rounded-2xl p-4 text-gray-500">No hay citas registradas.</div>
                <?php endif; ?>
                <?php foreach ($citas as $cita): 
                    $searchText = strtolower(($cita['paciente_nombre']??'') . ' ' . ($cita['doctor_nombre']??'') . ' ' . ($cita['tipo']??'') . ' ' . ($cita['estado']??''));
                    $puedeCancelar = in_array($cita['estado'], ['pendiente','confirmada']);
                ?>
                <details class="appointment-row border rounded-2xl overflow-hidden" data-search="<?php echo e($searchText); ?>">
                    <summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center gap-3">
                        <div>
                            <h4 class="font-semibold"><?php echo e(date("d/m/Y", strtotime($cita['fecha']))); ?> - <?php echo e(substr($cita['hora'],0,5)); ?></h4>
                            <p class="text-xs text-gray-500">Paciente: <?php echo e($cita['paciente_nombre'] ?? 'N/A'); ?> | Doctor: <?php echo e($cita['doctor_nombre'] ?? 'No asignado'); ?></p>
                        </div>
                        <span class="badge-fix <?php echo estadoBadge($cita['estado']); ?>"><?php echo e($cita['estado']); ?></span>
                    </summary>
                    <div class="p-4 bg-gray-50 text-sm space-y-3">
                        <p><strong>Tipo:</strong> <?php echo e(tipoTexto($cita['tipo'])); ?></p>
                        <p><strong>Observaciones:</strong> <?php echo e($cita['observaciones'] ?: 'Sin observaciones'); ?></p>
                        <?php if ($puedeCancelar): ?>
                        <form method="POST" onsubmit="return confirm('Cancelar esta cita?');">
                            <input type="hidden" name="accion" value="cancelar_cita">
                            <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                            <button class="btn-outline text-red-700 text-sm">Cancelar cita</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Búsqueda y listado de recetas -->
        <div id="recetas" class="card-ui p-5">
            <div class="flex flex-col md:flex-row md:justify-between gap-3 mb-4">
                <h3 class="font-bold">Recetas / Expedientes</h3>
                <form method="GET" class="flex gap-2">
                    <input type="text" name="buscar_receta" value="<?php echo e($busquedaReceta); ?>" class="input-ui text-sm" placeholder="Medicamento, dosis o diagnóstico">
                    <button class="btn-main text-sm">Buscar</button>
                </form>
            </div>
            <div class="space-y-3">
                <?php if (empty($recetas)): ?>
                    <div class="border rounded-2xl p-4 text-gray-500">No hay recetas que coincidan.</div>
                <?php endif; ?>
                <?php foreach ($recetas as $receta): ?>
                <details class="border rounded-2xl overflow-hidden bg-white">
                    <summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center">
                        <div>
                            <span class="font-semibold"><?php echo e($receta['medicamento'] ?: 'Medicamento'); ?></span>
                            <p class="text-xs text-gray-500">Paciente: <?php echo e($receta['paciente_nombre']); ?> | Doctor: <?php echo e($receta['doctor_nombre'] ?: 'No asignado'); ?> | <?php echo e(date("d/m/Y", strtotime($receta['fecha']))); ?></p>
                        </div>
                        <span class="recipe-toggle text-xs px-3 py-1">Ver receta</span>
                    </summary>
                    <div class="p-4 bg-gray-50 border-t">
                        <div class="grid md:grid-cols-2 gap-3 text-sm">
                            <div><strong>Medicamento:</strong> <?php echo e($receta['medicamento'] ?: 'N/A'); ?></div>
                            <div><strong>Dosis:</strong> <?php echo e($receta['dosis'] ?: 'N/A'); ?></div>
                            <div class="md:col-span-2"><strong>Indicaciones:</strong> <?php echo e($receta['indicaciones'] ?: 'N/A'); ?></div>
                            <div><strong>Diagnóstico:</strong> <?php echo e($receta['diagnostico'] ?: 'N/A'); ?></div>
                            <div><strong>Tratamiento:</strong> <?php echo e($receta['tratamiento'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</main>

<footer class="bg-[#b18ddd] p-5 text-center text-sm mt-8">
    <div class="team-container cursor-pointer font-semibold relative inline-block">
        <span>Error 404: Members not found</span>
        <div class="team-tooltip absolute z-10">
            <p>SHIRLEY ESTEFANIA SALAZAR MORALES</p>
            <p>KEVIN ALEJANDRO LEMUS TEJADA</p>
            <p>MARCOS ANTONIO QUINTANILLA VALLE</p>
            <p>MILTON ALEXIS GUTIERREZ RODRIGUEZ</p>
            <p>OTTO FERNANDO SANCHEZ CENTENO</p>
        </div>
    </div>
    <p class="mt-2 text-xs">Sistema clínico Dental Guru</p>
</footer>

<script>
    // Filtro para citas
    const searchInput = document.getElementById('searchAppointment');
    const rows = document.querySelectorAll('.appointment-row');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            rows.forEach(row => {
                const data = row.getAttribute('data-search') || '';
                row.hidden = !data.includes(term);
            });
        });
    }
</script>
</body>
</html>