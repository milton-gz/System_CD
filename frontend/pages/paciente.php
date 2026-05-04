<?php
session_start();
require_once "../config/conexion.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {
    switch ($_SESSION['rol'] ?? '') {
        case "admin":
            header("Location: admin.php");
            break;
        case "doctor":
            header("Location: doctor.php");
            break;
        case "recepcion":
            header("Location: recepcion.php");
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

$idUsuario = (int) $_SESSION['id'];
$nombre = $_SESSION['nombre'];
$mensaje = "";
$error = "";
$busquedaReceta = trim($_GET['buscar_receta'] ?? "");

// Escapa cualquier texto antes de imprimirlo para evitar HTML accidental en datos de BD.
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

$stmt = $conn->prepare("
    SELECT P.*, U.nombre, U.correo
    FROM PACIENTE P
    JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario
    WHERE P.USUARIO_id_usuario = ?
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$paciente = $stmt->get_result()->fetch_assoc();

if (!$paciente) {
    $stmt = $conn->prepare("INSERT INTO PACIENTE (USUARIO_id_usuario) VALUES (?)");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();

    $stmt = $conn->prepare("
        SELECT P.*, U.nombre, U.correo
        FROM PACIENTE P
        JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario
        WHERE P.USUARIO_id_usuario = ?
    ");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $paciente = $stmt->get_result()->fetch_assoc();
}

$idPaciente = (int) $paciente['id_paciente'];

// Todas las acciones del paciente entran por este bloque: agendar o cancelar una cita propia.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    if ($accion === "agendar") {
        $fecha = trim($_POST["fecha"] ?? "");
        $hora = trim($_POST["hora"] ?? "");
        $tipo = trim($_POST["tipo"] ?? "");
        $doctorId = trim($_POST["doctor"] ?? "");
        $observaciones = trim($_POST["observaciones"] ?? "");
        $tiposValidos = ["limpieza", "revision", "emergencia", "otros"];

        if ($fecha === "" || $hora === "" || !in_array($tipo, $tiposValidos, true)) {
            $error = "Completa fecha, hora y tipo de cita.";
        } elseif ($fecha < date("Y-m-d")) {
            $error = "La fecha de la cita no puede ser anterior a hoy.";
        } else {
            $doctorParam = $doctorId !== "" ? (int) $doctorId : null;
            $stmt = $conn->prepare("
                INSERT INTO CITA (PACIENTE_id_paciente, DOCTOR_id_doctor, fecha, hora, tipo, estado, observaciones)
                VALUES (?, ?, ?, ?, ?, 'pendiente', ?)
            ");
            $stmt->bind_param("iissss", $idPaciente, $doctorParam, $fecha, $hora, $tipo, $observaciones);

            if ($stmt->execute()) {
                $mensaje = "Cita solicitada correctamente. Queda pendiente de confirmacion.";
            } else {
                $error = "No se pudo solicitar la cita.";
            }
        }
    }

    if ($accion === "cancelar") {
        $idCita = (int) ($_POST["id_cita"] ?? 0);

        $stmt = $conn->prepare("
            SELECT estado
            FROM CITA
            WHERE id_cita = ? AND PACIENTE_id_paciente = ? AND estado IN ('pendiente','confirmada')
        ");
        $stmt->bind_param("ii", $idCita, $idPaciente);
        $stmt->execute();
        $citaActual = $stmt->get_result()->fetch_assoc();

        if ($citaActual) {
            $stmt = $conn->prepare("
                UPDATE CITA
                SET estado = 'cancelada'
                WHERE id_cita = ? AND PACIENTE_id_paciente = ?
            ");
            $stmt->bind_param("ii", $idCita, $idPaciente);

            if ($stmt->execute()) {
                $anterior = $citaActual["estado"];
                $nuevo = "cancelada";
                $historial = $conn->prepare("
                    INSERT INTO HISTORIAL_CITA (CITA_id_cita, estado_anterior, estado_nuevo)
                    VALUES (?, ?, ?)
                ");
                $historial->bind_param("iss", $idCita, $anterior, $nuevo);
                $historial->execute();
                $mensaje = "Cita cancelada correctamente.";
            } else {
                $error = "No se pudo cancelar la cita.";
            }
        } else {
            $error = "Solo puedes cancelar citas pendientes o confirmadas.";
        }
    }

    $stmt = $conn->prepare("
        SELECT P.*, U.nombre, U.correo
        FROM PACIENTE P
        JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario
        WHERE P.id_paciente = ?
    ");
    $stmt->bind_param("i", $idPaciente);
    $stmt->execute();
    $paciente = $stmt->get_result()->fetch_assoc();
}

$doctores = [];
$res = $conn->query("
    SELECT D.id_doctor, U.nombre, D.especialidad
    FROM DOCTOR D
    JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
    WHERE D.estado = 'activo'
    ORDER BY U.nombre
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $doctores[] = $row;
    }
}

$stmt = $conn->prepare("
    SELECT C.*, U.nombre AS doctor_nombre, D.especialidad
    FROM CITA C
    LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor
    LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
    WHERE C.PACIENTE_id_paciente = ?
    ORDER BY C.fecha DESC, C.hora DESC
");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT COUNT(*) total,
           SUM(estado IN ('pendiente','confirmada')) proximas,
           SUM(estado = 'atendida') atendidas,
           SUM(estado = 'cancelada') canceladas
    FROM CITA
    WHERE PACIENTE_id_paciente = ?
");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT C.*, U.nombre AS doctor_nombre
    FROM CITA C
    LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor
    LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
    WHERE C.PACIENTE_id_paciente = ?
      AND C.estado IN ('pendiente','confirmada')
      AND CONCAT(C.fecha, ' ', C.hora) >= NOW()
    ORDER BY C.fecha ASC, C.hora ASC
    LIMIT 1
");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$proximaCita = $stmt->get_result()->fetch_assoc();

// La busqueda revisa tanto la receta como el expediente relacionado para encontrar resultados utiles.
if ($busquedaReceta !== "") {
    $like = "%" . $busquedaReceta . "%";
    $stmt = $conn->prepare("
        SELECT R.*, E.diagnostico, E.tratamiento, U.nombre AS doctor_nombre
        FROM RECETA R
        JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente
        LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor
        LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
        WHERE E.PACIENTE_id_paciente = ?
          AND (R.medicamento LIKE ? OR R.dosis LIKE ? OR R.indicaciones LIKE ? OR E.diagnostico LIKE ?)
        ORDER BY R.fecha DESC
    ");
    $stmt->bind_param("issss", $idPaciente, $like, $like, $like, $like);
} else {
    $stmt = $conn->prepare("
        SELECT R.*, E.diagnostico, E.tratamiento, U.nombre AS doctor_nombre
        FROM RECETA R
        JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente
        LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor
        LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario
        WHERE E.PACIENTE_id_paciente = ?
        ORDER BY R.fecha DESC
    ");
    $stmt->bind_param("i", $idPaciente);
}
$stmt->execute();
$recetas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Guru | Paciente</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>

<style>
:root{
--primary:#b18ddd;
--secondary-soft:#e4f3ea;
}

html,body{height:100%;margin:0;}
body{background:var(--secondary-soft);display:flex;flex-direction:column;}
.header-ui{background:var(--primary);box-shadow:0 6px 18px rgba(0,0,0,.08);}
.main-wrapper{flex:1;}
.card-ui{background:white;border-radius:20px;box-shadow:0 8px 18px rgba(0,0,0,.06);transition:.25s;}
.card-ui:hover{transform:translateY(-2px);}
.badge-fix{display:inline-flex;align-items:center;justify-content:center;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;}
.footer-ui{background:var(--primary);margin-top:auto;}
.team-container{position:relative;display:inline-block;}
.team-tooltip{position:absolute;bottom:120%;left:50%;transform:translateX(-50%) translateY(10px);background:white;color:#333;padding:12px 14px;border-radius:14px;box-shadow:0 12px 25px rgba(0,0,0,.12);font-size:12px;line-height:1.4;opacity:0;pointer-events:none;transition:all .25s ease;min-width:280px;text-align:left;}
.team-tooltip::after{content:"";position:absolute;top:100%;left:50%;transform:translateX(-50%);border-width:6px;border-style:solid;border-color:white transparent transparent transparent;}
.team-container:hover .team-tooltip{opacity:1;transform:translateX(-50%) translateY(0);}
.tab-btn.active{background:#6FAE84;color:white;border-color:#6FAE84;}
.appointment-row[hidden], .recipe-row[hidden]{display:none;}
.recipe-summary::-webkit-details-marker{display:none;}
.recipe-summary{list-style:none;}
.recipe-sheet{background:linear-gradient(180deg,#ffffff 0%,#f8fcfa 100%);border:1px solid #dbeee2;border-radius:18px;overflow:hidden;}
.recipe-sheet-header{background:#eef9f1;border-bottom:1px solid #dbeee2;}
.recipe-field{background:white;border:1px solid #e5eee8;border-radius:14px;padding:12px;}
.recipe-field span{display:block;font-size:11px;font-weight:700;color:#5F9A73;text-transform:uppercase;margin-bottom:4px;}
.recipe-field p{font-size:14px;color:#111827;line-height:1.5;white-space:pre-wrap;}
.recipe-toggle{background:#6FAE84;color:white;border-radius:999px;padding:8px 14px;font-size:12px;font-weight:700;white-space:nowrap;transition:.25s ease;}
.recipe-toggle:hover{background:#5F9A73;transform:translateY(-1px);}
details[open] .recipe-toggle::after{content:" abierta";}
</style>
</head>

<body>
<header class="header-ui px-6 py-3 flex items-center justify-between">
<img src="../assets/logo.png" class="w-28 rounded-xl shadow" alt="Dental Guru">
<div class="flex items-center gap-4">
<div class="text-sm font-semibold text-gray-900"><?php echo e($nombre); ?></div>
<a href="../config/cerrar_sesion.php" class="text-xs font-semibold text-gray-900 hover:underline">Cerrar sesion</a>
</div>
</header>

<div class="main-wrapper">
<main class="max-w-7xl mx-auto w-full p-4 md:p-6">

<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
<div>
<h2 class="text-2xl font-bold text-gray-900">Panel del Paciente</h2>
<p class="text-sm text-gray-600">Consulta tus recetas, agenda citas y revisa tu historial clinico.</p>
</div>
<div class="flex gap-2 flex-wrap">
<a href="#recetas" class="btn-outline text-sm">Buscar recetas</a>
<a href="#agendar" class="btn-main text-sm">Agendar cita</a>
</div>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-green-50 text-green-700 border border-green-200"><?php echo e($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-red-50 text-red-700 border border-red-200"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
<div class="card-ui p-4">
<p class="text-xs text-gray-500">Proxima cita</p>
<?php if ($proximaCita): ?>
<h3 class="font-bold"><?php echo e(date("d/m/Y", strtotime($proximaCita["fecha"]))); ?></h3>
<p class="text-xs text-gray-500"><?php echo e(substr($proximaCita["hora"], 0, 5)); ?> - <?php echo e(tipoTexto($proximaCita["tipo"])); ?></p>
<?php else: ?>
<h3 class="font-bold">Sin cita</h3>
<p class="text-xs text-gray-500">Agenda una nueva visita</p>
<?php endif; ?>
</div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Atendidas</p><h3 class="text-xl font-bold text-green-600"><?php echo (int) ($resumen["atendidas"] ?? 0); ?></h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Pendientes</p><h3 class="text-xl font-bold text-yellow-600"><?php echo (int) ($resumen["proximas"] ?? 0); ?></h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Recetas</p><h3 class="text-xl font-bold"><?php echo count($recetas); ?></h3></div>
</section>

<div class="grid lg:grid-cols-3 gap-5">
<section class="space-y-5">
<div id="agendar" class="card-ui p-5">
<h3 class="font-bold mb-4">Agendar cita</h3>
<form method="POST" class="space-y-3">
<input type="hidden" name="accion" value="agendar">
<input type="date" name="fecha" min="<?php echo date("Y-m-d"); ?>" class="input-ui" required>
<input type="time" name="hora" min="08:00" max="18:00" class="input-ui" required>
<select name="tipo" class="input-ui" required>
<option value="">Tipo de cita</option>
<option value="limpieza">Limpieza dental</option>
<option value="revision">Revision general</option>
<option value="emergencia">Emergencia</option>
<option value="otros">Otros</option>
</select>
<select name="doctor" class="input-ui">
<option value="">Cualquier doctor disponible</option>
<?php foreach ($doctores as $doctor): ?>
<option value="<?php echo (int) $doctor["id_doctor"]; ?>">
<?php echo e($doctor["nombre"] . ($doctor["especialidad"] ? " - " . $doctor["especialidad"] : "")); ?>
</option>
<?php endforeach; ?>
</select>
<textarea name="observaciones" class="input-ui" rows="3" placeholder="Motivo o comentario para recepcion"></textarea>
<button class="btn-main w-full">Solicitar cita</button>
</form>
</div>

<div class="card-ui p-5">
<h3 class="font-bold mb-4">Mi expediente</h3>
<div class="space-y-2 text-sm">
<p><strong>Nombre:</strong> <?php echo e($paciente["nombre"]); ?></p>
<p><strong>Correo:</strong> <?php echo e($paciente["correo"]); ?></p>
<p><strong>Telefono:</strong> <?php echo e($paciente["telefono"] ?: "Sin registrar"); ?></p>
<p><strong>Edad:</strong> <?php echo e($paciente["edad"] ?: "Sin registrar"); ?></p>
<p><strong>Tipo sangre:</strong> <?php echo e($paciente["tipo_sangre"] ?: "Sin registrar"); ?></p>
<p><strong>Alergias:</strong> <?php echo e($paciente["alergias"] ?: "Sin registrar"); ?></p>
</div>
</div>
</section>

<section class="lg:col-span-2 space-y-5">
<div id="recetas" class="card-ui p-5">
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
<h3 class="font-bold">Mis recetas</h3>
<form method="GET" class="flex gap-2">
<input type="text" name="buscar_receta" value="<?php echo e($busquedaReceta); ?>" class="input-ui text-sm" placeholder="Medicamento, dosis o diagnostico">
<button class="btn-main text-sm">Buscar</button>
</form>
</div>

<div class="space-y-3">
<?php if (!$recetas): ?>
<div class="border rounded-2xl p-4 text-sm text-gray-500">No hay recetas registradas para mostrar.</div>
<?php endif; ?>

<?php foreach ($recetas as $receta): ?>
<!-- Cada receta se puede desplegar como una ficha completa para que el paciente lea todo lo escrito por el doctor. -->
<details class="recipe-row border rounded-2xl overflow-hidden bg-white">
<summary class="recipe-summary p-4 cursor-pointer hover:bg-gray-50 flex flex-col md:flex-row md:justify-between md:items-center gap-3">
<div>
<div class="flex items-center gap-2 flex-wrap mb-1">
<h4 class="font-semibold text-sm"><?php echo e($receta["medicamento"] ?: "Medicamento sin nombre"); ?></h4>
<span class="badge-fix bg-green-100 text-green-700">Receta</span>
</div>
<p class="text-xs text-gray-500">
<?php echo e(date("d/m/Y", strtotime($receta["fecha"]))); ?>
<?php echo $receta["doctor_nombre"] ? " - " . e($receta["doctor_nombre"]) : " - Doctor no asignado"; ?>
</p>
</div>
<span class="recipe-toggle">Mostrar receta completa</span>
</summary>

<div class="p-4 bg-gray-50">
<article class="recipe-sheet">
<div class="recipe-sheet-header p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
<div>
<p class="text-xs font-bold text-[#5F9A73] uppercase">Dental Guru</p>
<h4 class="text-lg font-extrabold text-gray-900">Receta medica</h4>
<p class="text-xs text-gray-600">Folio #<?php echo (int) $receta["id_receta"]; ?></p>
</div>
<div class="text-left md:text-right text-xs text-gray-600">
<p><strong>Fecha:</strong> <?php echo e(date("d/m/Y H:i", strtotime($receta["fecha"]))); ?></p>
<p><strong>Paciente:</strong> <?php echo e($paciente["nombre"]); ?></p>
<p><strong>Doctor:</strong> <?php echo e($receta["doctor_nombre"] ?: "No asignado"); ?></p>
</div>
</div>

<div class="p-4 grid md:grid-cols-2 gap-3">
<div class="recipe-field">
<span>Medicamento</span>
<p><?php echo e($receta["medicamento"] ?: "No indicado"); ?></p>
</div>
<div class="recipe-field">
<span>Dosis</span>
<p><?php echo e($receta["dosis"] ?: "No indicada"); ?></p>
</div>
<div class="recipe-field md:col-span-2">
<span>Indicaciones completas</span>
<p><?php echo e($receta["indicaciones"] ?: "Sin indicaciones registradas"); ?></p>
</div>
<div class="recipe-field">
<span>Diagnostico</span>
<p><?php echo e($receta["diagnostico"] ?: "Sin diagnostico registrado"); ?></p>
</div>
<div class="recipe-field">
<span>Tratamiento</span>
<p><?php echo e($receta["tratamiento"] ?: "Sin tratamiento registrado"); ?></p>
</div>
</div>

<div class="px-4 pb-4">
<div class="border-t border-dashed border-[#b7dfc4] pt-3 text-xs text-gray-500 flex flex-col md:flex-row md:justify-between gap-2">
<span>Consulta esta informacion antes de tomar cualquier medicamento.</span>
<span>Sistema clinico Dental Guru</span>
</div>
</div>
</article>
</div>
</details>
<?php endforeach; ?>
</div>
</div>

<div class="card-ui p-5">
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
<h3 class="font-bold">Historial de citas</h3>
<input type="text" id="searchAppointment" class="input-ui text-sm md:w-72" placeholder="Buscar por tipo, doctor o estado">
</div>

<div class="space-y-3" id="appointmentList">
<?php if (!$citas): ?>
<div class="border rounded-2xl p-4 text-sm text-gray-500">Aun no tienes citas registradas.</div>
<?php endif; ?>

<?php foreach ($citas as $cita): ?>
<?php
$textoBusqueda = strtolower(($cita["tipo"] ?? "") . " " . ($cita["estado"] ?? "") . " " . ($cita["doctor_nombre"] ?? ""));
$puedeCancelar = in_array($cita["estado"], ["pendiente", "confirmada"], true);
?>
<details class="appointment-row border rounded-2xl overflow-hidden" data-search="<?php echo e($textoBusqueda); ?>">
<summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center gap-3">
<div>
<h4 class="font-semibold text-sm"><?php echo e(date("d/m/Y", strtotime($cita["fecha"]))); ?> - <?php echo e(substr($cita["hora"], 0, 5)); ?></h4>
<p class="text-xs text-gray-500"><?php echo e(tipoTexto($cita["tipo"])); ?><?php echo $cita["doctor_nombre"] ? " - " . e($cita["doctor_nombre"]) : ""; ?></p>
</div>
<span class="badge-fix <?php echo estadoBadge($cita["estado"]); ?>"><?php echo e($cita["estado"]); ?></span>
</summary>
<div class="p-4 bg-gray-50 text-sm space-y-3">
<p><strong>Observaciones:</strong> <?php echo e($cita["observaciones"] ?: "Sin observaciones"); ?></p>
<?php if ($puedeCancelar): ?>
<form method="POST" onsubmit="return confirm('Seguro que deseas cancelar esta cita?');">
<input type="hidden" name="accion" value="cancelar">
<input type="hidden" name="id_cita" value="<?php echo (int) $cita["id_cita"]; ?>">
<button class="btn-outline text-sm text-red-700">Cancelar cita</button>
</form>
<?php endif; ?>
</div>
</details>
<?php endforeach; ?>
</div>
</div>
</section>
</div>
</main>
</div>

<footer class="footer-ui p-5 text-center text-sm text-gray-800">
<div class="team-container cursor-pointer font-semibold">
<span>Error 404: Members not found</span>
<div class="team-tooltip">
<p>SHIRLEY ESTEFANIA SALAZAR MORALES</p>
<p>KEVIN ALEJANDRO LEMUS TEJADA</p>
<p>MARCOS ANTONIO QUINTANILLA VALLE</p>
<p>MILTON ALEXIS GUTIERREZ RODRIGUEZ</p>
<p>OTTO FERNANDO SANCHEZ CENTENO</p>
</div>
</div>
<p class="mt-2 text-xs">Sistema clinico Dental Guru</p>
</footer>

<script>
const searchAppointment = document.getElementById("searchAppointment");
const appointmentRows = document.querySelectorAll(".appointment-row");

if (searchAppointment) {
    searchAppointment.addEventListener("input", function () {
        const value = this.value.toLowerCase();
        appointmentRows.forEach(row => {
            row.hidden = !row.dataset.search.includes(value);
        });
    });
}
</script>
</body>
</html>
