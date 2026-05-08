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
        case "admin": header("Location: admin.php"); break;
        case "doctor": header("Location: doctor.php"); break;
        case "paciente": header("Location: paciente.php"); break;
        default: session_destroy(); header("Location: ../login.php"); break;
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
        "pendiente" => "bg-amber-100 text-amber-700",
        "confirmada" => "bg-emerald-100 text-emerald-700",
        "atendida" => "bg-sky-100 text-sky-700",
        "cancelada" => "bg-rose-100 text-rose-700"
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

// Envío de correo
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

// Procesar acciones POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // CREAR NUEVO PACIENTE
    if ($accion === "crear_paciente") {
        $nombre = trim($_POST["nombre"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $telefono = trim($_POST["telefono"] ?? "");
        $edad = trim($_POST["edad"] ?? "");
        $tipoSangre = trim($_POST["tipo_sangre"] ?? "");
        $alergias = trim($_POST["alergias"] ?? "");
        $direccion = trim($_POST["direccion"] ?? "");

        if ($nombre === "" || $correo === "") {
            $error = "Nombre y correo son obligatorios.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "Correo electrónico no valido.";
        } else {
            $stmtCheck = $conn->prepare("SELECT id_usuario FROM USUARIO WHERE correo = ?");
            $stmtCheck->bind_param("s", $correo);
            $stmtCheck->execute();
            $existe = $stmtCheck->get_result()->fetch_assoc();
            if ($existe) {
                $error = "Ya existe un usuario con ese correo electronico.";
            } else {
                $passwordPlano = generarPassword(8);
                $passwordHash = password_hash($passwordPlano, PASSWORD_DEFAULT);
                $rol = 'paciente';
                $estado = 'activo';

                $stmt = $conn->prepare("INSERT INTO USUARIO (nombre, correo, password, rol, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nombre, $correo, $passwordHash, $rol, $estado);
                if ($stmt->execute()) {
                    $idUsuario = $conn->insert_id;

                    $stmt2 = $conn->prepare("INSERT INTO PACIENTE (USUARIO_id_usuario, telefono, edad, tipo_sangre, alergias, direccion) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt2->bind_param("isssss", $idUsuario, $telefono, $edad, $tipoSangre, $alergias, $direccion);
                    if ($stmt2->execute()) {
                        if (enviarCredenciales($correo, $nombre, $passwordPlano)) {
                            $mensaje = "Paciente creado exitosamente. Se enviaron las credenciales al correo $correo.";
                        } else {
                            $mensaje = "Paciente creado, pero no se pudo enviar el correo. Contraseña generada: $passwordPlano (anotala).";
                        }
                    } else {
                        $error = "Error al crear el registro en PACIENTE.";
                        $conn->query("DELETE FROM USUARIO WHERE id_usuario = $idUsuario");
                    }
                } else {
                    $error = "Error al crear el usuario: " . $conn->error;
                }
            }
        }
    }

    // AGENDAR CITA
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
        } elseif ($fecha < date("Y-m-d")) {
            $error = "La fecha no puede ser anterior a hoy.";
        } else {
            $fechaHoraSeleccionada = strtotime("$fecha $hora");
            if ($fechaHoraSeleccionada <= time()) {
                $error = "No puedes agendar en una hora pasada.";
            } else {
                if ($doctorId !== "") {
                    $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM CITA WHERE fecha = ? AND hora = ? AND DOCTOR_id_doctor = ? AND estado IN ('pendiente','confirmada')");
                    $doctorParam = (int)$doctorId;
                    $stmtCheck->bind_param("ssi", $fecha, $hora, $doctorParam);
                } else {
                    $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM CITA WHERE fecha = ? AND hora = ? AND estado IN ('pendiente','confirmada')");
                    $stmtCheck->bind_param("ss", $fecha, $hora);
                }
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result()->fetch_assoc();
                if ($resCheck['total'] > 0) {
                    $error = "Horario no disponible.";
                } else {
                    $doctorParam = $doctorId !== "" ? (int)$doctorId : null;
                    $stmt = $conn->prepare("INSERT INTO CITA (PACIENTE_id_paciente, DOCTOR_id_doctor, fecha, hora, tipo, estado, observaciones) VALUES (?, ?, ?, ?, ?, 'pendiente', ?)");
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

    // CANCELAR CITA
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

// Obtener listado de pacientes
$pacientes = [];
$resPac = $conn->query("SELECT P.id_paciente, U.nombre, U.correo FROM PACIENTE P JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario WHERE U.estado = 'activo' ORDER BY U.nombre");
if ($resPac) {
    while ($row = $resPac->fetch_assoc()) $pacientes[] = $row;
}

// Obtener listado de doctores activos
$doctores = [];
$resDoc = $conn->query("SELECT D.id_doctor, U.nombre, D.especialidad FROM DOCTOR D JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE D.estado = 'activo' ORDER BY U.nombre");
if ($resDoc) {
    while ($row = $resDoc->fetch_assoc()) $doctores[] = $row;
}

// Listado de todas las citas
$citas = [];
$stmtCitas = $conn->prepare("
    SELECT C.*, U_pac.nombre AS paciente_nombre, U_doc.nombre AS doctor_nombre, D.especialidad
    FROM CITA C
    JOIN PACIENTE P ON P.id_paciente = C.PACIENTE_id_paciente
    JOIN USUARIO U_pac ON U_pac.id_usuario = P.USUARIO_id_usuario
    LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor
    LEFT JOIN USUARIO U_doc ON U_doc.id_usuario = D.USUARIO_id_usuario
    ORDER BY C.fecha DESC, C.hora DESC
");
$stmtCitas->execute();
$citas = $stmtCitas->get_result()->fetch_all(MYSQLI_ASSOC);

// Búsqueda de recetas
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Dental Guru | Recepción</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
:root {
    --primary: #7c6fb0;
    --primary-light: #9b8fc9;
    --primary-dark: #5e5290;
    --secondary: #8cd4ae;
    --secondary-dark: #6ab88e;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #f5f7fb;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* HEADER */
.header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

/* MENU LATERAL */
.mobile-menu {
    position: fixed;
    top: 0;
    right: -300px;
    width: 280px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 200;
    padding: 24px 20px;
    box-shadow: -5px 0 30px rgba(0, 0, 0, 0.2);
}
.mobile-menu.active { right: 0; }

.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 150;
}
.overlay.active {
    opacity: 1;
    visibility: visible;
}

/* CARDS */
.card-ui {
    background: white;
    border-radius: 20px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    transition: all 0.25s ease;
    border: 1px solid rgba(124, 111, 176, 0.08);
}
.card-ui:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(124, 111, 176, 0.12);
}

/* INPUTS */
.input-ui {
    width: 100%;
    padding: 10px 14px;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 14px;
    transition: all 0.2s;
    background: #fafcff;
}
.input-ui:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(124, 111, 176, 0.1);
}

select.input-ui, textarea.input-ui {
    background: #fafcff;
}

/* BOTONES */
.btn-main {
    background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
    color: #1a2e2a;
    padding: 10px 20px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}
.btn-main:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(140, 212, 174, 0.35);
}

.btn-outline {
    background: transparent;
    border: 1.5px solid var(--primary-light);
    color: var(--primary-dark);
    padding: 8px 16px;
    border-radius: 40px;
    font-weight: 500;
    font-size: 12px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-outline:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-1px);
}

/* BADGES */
.badge-fix {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

/* RECETAS */
.recipe-toggle {
    background: var(--secondary);
    color: #1a2e2a;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 11px;
    font-weight: 700;
    transition: .25s ease;
}
.recipe-toggle:hover {
    background: var(--secondary-dark);
    transform: translateY(-1px);
}

/* FOOTER */
.footer-ui {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    margin-top: auto;
}

.team-container {
    position: relative;
    display: inline-block;
}
.team-tooltip {
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: white;
    color: #1e293b;
    padding: 12px 14px;
    border-radius: 14px;
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
    font-size: 12px;
    line-height: 1.4;
    opacity: 0;
    pointer-events: none;
    transition: all .25s ease;
    min-width: 280px;
    text-align: left;
    z-index: 20;
}
.team-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: white transparent transparent transparent;
}
.team-container:hover .team-tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

/* ANIMACIONES */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* SCROLLBAR */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 10px;
}
</style>
</head>

<body>

<!-- HEADER CON HAMBURGUESA -->
<header class="header px-5 md:px-8 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <img src="../assets/logo.png" class="w-28 rounded-xl shadow-sm" alt="Dental Guru" onerror="this.style.display='none'">
        <div class="hidden md:block">
            <p class="font-semibold text-white">Dental Guru</p>
            <p class="text-[11px] text-white/70">Area de recepcion</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-sm font-semibold text-white hidden md:block">
            <i class="fas fa-user-friends mr-1"></i><?php echo e($nombreRecepcion); ?>
        </div>
        <button onclick="openMenu()" class="text-white text-2xl focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<!-- OVERLAY Y MENU LATERAL -->
<div id="overlay" class="overlay" onclick="closeMenu()"></div>
<div id="menu" class="mobile-menu">
    <div class="flex justify-between items-center mb-6 pb-3 border-b border-white/20">
        <div class="flex items-center gap-2">
            <img src="../assets/logo.png" class="w-8 rounded-lg" alt="Logo">
            <p class="font-bold text-white">Dental Guru</p>
        </div>
        <button onclick="closeMenu()" class="text-white/80 text-xl hover:text-white">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="mb-6 p-3 bg-white/10 rounded-xl">
        <p class="text-sm font-medium text-white/90"><?php echo e($nombreRecepcion); ?></p>
        <p class="text-xs text-white/70 mt-1"><i class="fas fa-concierge-bell mr-1"></i>Recepcion</p>
    </div>
    <nav class="space-y-2">
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/20 transition"><i class="fas fa-home w-5"></i> Inicio</a>
        <a href="#crear" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition" onclick="closeMenu()"><i class="fas fa-user-plus w-5"></i> Nuevo paciente</a>
        <a href="#agendar" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition" onclick="closeMenu()"><i class="fas fa-calendar-plus w-5"></i> Agendar cita</a>
        <a href="#citas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition" onclick="closeMenu()"><i class="fas fa-calendar-alt w-5"></i> Todas las citas</a>
        <a href="#recetas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition" onclick="closeMenu()"><i class="fas fa-prescription-bottle w-5"></i> Buscar recetas</a>
    </nav>
    <hr class="my-4 border-white/20">
    <a href="../config/cerrar_sesion.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition"><i class="fas fa-sign-out-alt w-5"></i> Cerrar sesion</a>
</div>

<!-- MAIN CONTENT -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4 md:p-6 fade-in">

<div class="flex flex-col md:flex-row md:justify-between gap-3 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Panel de Recepcion</h2>
        <p class="text-sm text-gray-500">Gestion de pacientes, citas y consulta de recetas</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="#crear" class="btn-main text-sm"><i class="fas fa-user-plus mr-1"></i> Nuevo Paciente</a>
        <a href="#agendar" class="btn-main text-sm"><i class="fas fa-calendar-plus mr-1"></i> Agendar Cita</a>
        <a href="#recetas" class="btn-outline text-sm"><i class="fas fa-search mr-1"></i> Buscar Recetas</a>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-3 rounded-xl bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-sm flex items-center gap-2">
    <i class="fas fa-check-circle"></i> <?php echo e($mensaje); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 p-3 rounded-xl bg-rose-50 border-l-4 border-rose-500 text-rose-700 text-sm flex items-center gap-2">
    <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-5">
    <!-- COLUMNA IZQUIERDA: FORMULARIOS -->
    <div class="space-y-5">
        <!-- Formulario crear paciente -->
        <div id="crear" class="card-ui p-5">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-user-plus text-[#7c6fb0]"></i> Registrar nuevo paciente
            </h3>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_paciente">
                <div class="space-y-3">
                    <input type="text" name="nombre" placeholder="Nombre completo *" class="input-ui" required>
                    <input type="email" name="correo" placeholder="Correo electronico *" class="input-ui" required>
                    <input type="tel" name="telefono" placeholder="Telefono" class="input-ui">
                    <input type="number" name="edad" placeholder="Edad" class="input-ui">
                    <input type="text" name="tipo_sangre" placeholder="Tipo de sangre" class="input-ui">
                    <input type="text" name="direccion" placeholder="Direccion" class="input-ui">
                    <textarea name="alergias" rows="2" placeholder="Alergias" class="input-ui"></textarea>
                    <button class="btn-main w-full"><i class="fas fa-save mr-1"></i> Crear paciente</button>
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-3"><i class="fas fa-info-circle mr-1"></i> La contraseña se genera automaticamente y se envia al correo</p>
        </div>

        <!-- Formulario agendar cita -->
        <div id="agendar" class="card-ui p-5">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-plus text-[#7c6fb0]"></i> Agendar cita
            </h3>
            <form method="POST">
                <input type="hidden" name="accion" value="agendar_cita">
                <div class="space-y-3">
                    <select name="paciente_id" class="input-ui" required>
                        <option value="">Seleccionar paciente</option>
                        <?php foreach ($pacientes as $pac): ?>
                            <option value="<?php echo $pac['id_paciente']; ?>"><?php echo e($pac['nombre']); ?> (<?php echo e($pac['correo']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="fecha" class="input-ui" required min="<?php echo date("Y-m-d"); ?>">
                    <input type="time" name="hora" class="input-ui" required step="1800">
                    <select name="tipo" class="input-ui" required>
                        <option value="">Tipo de cita</option>
                        <option value="limpieza">Limpieza dental</option>
                        <option value="revision">Revision general</option>
                        <option value="emergencia">Emergencia</option>
                        <option value="otros">Otros</option>
                    </select>
                    <select name="doctor" class="input-ui">
                        <option value="">Cualquier doctor (opcional)</option>
                        <?php foreach ($doctores as $doc): ?>
                            <option value="<?php echo $doc['id_doctor']; ?>"><?php echo e($doc['nombre']); ?> (<?php echo e($doc['especialidad']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <textarea name="observaciones" rows="2" placeholder="Observaciones" class="input-ui"></textarea>
                    <button class="btn-main w-full"><i class="fas fa-calendar-check mr-1"></i> Agendar cita</button>
                </div>
            </form>
        </div>
    </div>

    <!-- COLUMNA DERECHA: CITAS Y RECETAS -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Listado de citas -->
        <div id="citas" class="card-ui p-5">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-[#7c6fb0]"></i> Todas las citas
                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full text-gray-500"><?php echo count($citas); ?></span>
            </h3>
            <div class="relative mb-3">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="searchAppointment" class="input-ui pl-9 py-2 text-sm" placeholder="Buscar por paciente, doctor, tipo o estado">
            </div>
            <div class="space-y-3" id="appointmentList">
                <?php if (empty($citas)): ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>No hay citas registradas</div>
                <?php endif; ?>
                <?php foreach ($citas as $cita): 
                    $searchText = strtolower(($cita['paciente_nombre']??'') . ' ' . ($cita['doctor_nombre']??'') . ' ' . ($cita['tipo']??'') . ' ' . ($cita['estado']??''));
                    $puedeCancelar = in_array($cita['estado'], ['pendiente','confirmada']);
                ?>
                <details class="appointment-row border rounded-xl overflow-hidden bg-white" data-search="<?php echo e($searchText); ?>">
                    <summary class="p-4 cursor-pointer hover:bg-gray-50 flex flex-wrap justify-between items-center gap-3">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 text-sm">
                                <i class="far fa-calendar-alt mr-1 text-[#7c6fb0]"></i><?php echo e(date("d/m/Y", strtotime($cita['fecha']))); ?> - <?php echo e(substr($cita['hora'],0,5)); ?>
                            </h4>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-user mr-1"></i>Paciente: <?php echo e($cita['paciente_nombre'] ?? 'N/A'); ?> | 
                                <i class="fas fa-stethoscope mr-1"></i>Doctor: <?php echo e($cita['doctor_nombre'] ?? 'No asignado'); ?>
                            </p>
                        </div>
                        <span class="badge-fix <?php echo estadoBadge($cita['estado']); ?>"><?php echo e($cita['estado']); ?></span>
                    </summary>
                    <div class="p-4 bg-gray-50 text-sm space-y-3">
                        <div class="grid md:grid-cols-2 gap-3">
                            <p><i class="fas fa-tooth text-[#7c6fb0] w-5"></i> <strong>Tipo:</strong> <?php echo e(tipoTexto($cita['tipo'])); ?></p>
                            <p><i class="fas fa-stethoscope text-[#7c6fb0] w-5"></i> <strong>Especialidad:</strong> <?php echo e($cita['especialidad'] ?? 'General'); ?></p>
                            <p class="md:col-span-2"><i class="fas fa-comment text-[#7c6fb0] w-5"></i> <strong>Observaciones:</strong> <?php echo e($cita['observaciones'] ?: 'Sin observaciones'); ?></p>
                        </div>
                        <?php if ($puedeCancelar): ?>
                        <form method="POST" onsubmit="return confirm('Cancelar esta cita?');" class="mt-2">
                            <input type="hidden" name="accion" value="cancelar_cita">
                            <input type="hidden" name="id_cita" value="<?php echo $cita['id_cita']; ?>">
                            <button class="btn-outline text-rose-600 border-rose-200 hover:bg-rose-600 hover:text-white text-sm"><i class="fas fa-times mr-1"></i>Cancelar cita</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Busqueda de recetas -->
        <div id="recetas" class="card-ui p-5">
            <div class="flex flex-col md:flex-row md:justify-between gap-3 mb-4">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-prescription-bottle text-[#7c6fb0]"></i> Recetas / Expedientes
                    <span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full text-gray-500"><?php echo count($recetas); ?></span>
                </h3>
                <form method="GET" class="flex gap-2">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="buscar_receta" value="<?php echo e($busquedaReceta); ?>" class="input-ui pl-9 py-2 text-sm w-full md:w-64" placeholder="Medicamento, dosis o diagnostico">
                    </div>
                    <button class="btn-main text-sm"><i class="fas fa-search"></i> Buscar</button>
                </form>
            </div>
            <div class="space-y-3">
                <?php if (empty($recetas)): ?>
                    <div class="text-center py-8 text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>No hay recetas registradas</div>
                <?php endif; ?>
                <?php foreach ($recetas as $receta): ?>
                <details class="border rounded-xl overflow-hidden bg-white">
                    <summary class="p-4 cursor-pointer hover:bg-gray-50 flex flex-wrap justify-between items-center gap-3">
                        <div>
                            <span class="font-semibold text-gray-800 text-sm"><i class="fas fa-capsules mr-1 text-[#7c6fb0]"></i><?php echo e($receta['medicamento'] ?: 'Medicamento sin nombre'); ?></span>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-user mr-1"></i><?php echo e($receta['paciente_nombre']); ?> | 
                                <i class="fas fa-stethoscope mr-1"></i><?php echo e($receta['doctor_nombre'] ?: 'No asignado'); ?> | 
                                <i class="far fa-calendar-alt mr-1"></i><?php echo e(date("d/m/Y", strtotime($receta['fecha']))); ?>
                            </p>
                        </div>
                        <span class="recipe-toggle text-xs px-3 py-1">Ver receta completa</span>
                    </summary>
                    <div class="p-4 bg-gray-50 border-t border-gray-100">
                        <div class="grid md:grid-cols-2 gap-3 text-sm">
                            <div class="bg-white p-3 rounded-xl"><strong class="text-[#7c6fb0]">Medicamento:</strong><br><?php echo e($receta['medicamento'] ?: 'N/A'); ?></div>
                            <div class="bg-white p-3 rounded-xl"><strong class="text-[#7c6fb0]">Dosis:</strong><br><?php echo e($receta['dosis'] ?: 'N/A'); ?></div>
                            <div class="md:col-span-2 bg-white p-3 rounded-xl"><strong class="text-[#7c6fb0]">Indicaciones:</strong><br><?php echo e($receta['indicaciones'] ?: 'N/A'); ?></div>
                            <div class="bg-white p-3 rounded-xl"><strong class="text-[#7c6fb0]">Diagnostico:</strong><br><?php echo e($receta['diagnostico'] ?: 'N/A'); ?></div>
                            <div class="bg-white p-3 rounded-xl"><strong class="text-[#7c6fb0]">Tratamiento:</strong><br><?php echo e($receta['tratamiento'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</main>

<!-- FOOTER -->
<footer class="footer-ui p-5 text-center text-sm text-gray-800">
    <div class="team-container cursor-pointer font-semibold">
        <span><i class="fas fa-code-branch mr-1"></i> Error 404: Members not found</span>
        <div class="team-tooltip">
            <p>SHIRLEY ESTEFANIA SALAZAR MORALES</p>
            <p>KEVIN ALEJANDRO LEMUS TEJADA</p>
            <p>MARCOS ANTONIO QUINTANILLA VALLE</p>
            <p>MILTON ALEXIS GUTIERREZ RODRIGUEZ</p>
            <p>OTTO FERNANDO SANCHEZ CENTENO</p>
        </div>
    </div>
    <p class="mt-2 text-xs opacity-70">Sistema clinico Dental Guru</p>
</footer>

<script>
function openMenu() {
    document.getElementById("menu").classList.add("active");
    document.getElementById("overlay").classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeMenu() {
    document.getElementById("menu").classList.remove("active");
    document.getElementById("overlay").classList.remove("active");
    document.body.style.overflow = "";
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenu();
});

// Filtro para citas
const searchInput = document.getElementById('searchAppointment');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('.appointment-row');
        rows.forEach(row => {
            const data = row.getAttribute('data-search') || '';
            row.style.display = data.includes(term) ? '' : 'none';
        });
    });
}
</script>

</body>
</html>