<?php
session_start();
require_once "../config/conexion.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {
    switch ($_SESSION['rol'] ?? '') {
        case "admin": header("Location: admin.php"); break;
        case "doctor": header("Location: doctor.php"); break;
        case "recepcion": header("Location: recepcion.php"); break;
        default: session_destroy(); header("Location: ../login.php"); break;
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

   if ($accion === "agendar") {
    date_default_timezone_set('America/El_Salvador');
    $fecha = trim($_POST["fecha"] ?? "");
    $hora = trim($_POST["hora"] ?? "");
    $tipo = trim($_POST["tipo"] ?? "");
    $doctorId = trim($_POST["doctor"] ?? "");
    $observaciones = trim($_POST["observaciones"] ?? "");
    $tiposValidos = ["limpieza", "revision", "emergencia", "otros"];

    if ($fecha === "" || $hora === "" || !in_array($tipo, $tiposValidos, true)) {
        $error = "Completa fecha, hora y tipo de cita.";
    } elseif ($fecha < date("Y-m-d")) {
        $error = "La fecha no puede ser anterior a hoy.";
    } else {
        $fechaHoraSeleccionada = strtotime("$fecha $hora");
        $fechaHoraActual = time();
        if ($fechaHoraSeleccionada <= $fechaHoraActual) {
            $error = "No puedes agendar en una hora pasada.";
        } else {
            if ($doctorId !== "") {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM CITA WHERE fecha = ? AND hora = ? AND DOCTOR_id_doctor = ? AND estado IN ('pendiente','confirmada')");
                $doctorParam = (int) $doctorId;
                $stmtCheck->bind_param("ssi", $fecha, $hora, $doctorParam);
            } else {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) as total FROM CITA WHERE fecha = ? AND hora = ? AND estado IN ('pendiente','confirmada')");
                $stmtCheck->bind_param("ss", $fecha, $hora);
            }
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result()->fetch_assoc();

            if ($resCheck['total'] > 0) {
                $error = "Ese horario ya no está disponible.";
            } else {
                $doctorParam = $doctorId !== "" ? (int) $doctorId : null;
                $stmt = $conn->prepare("INSERT INTO CITA (PACIENTE_id_paciente, DOCTOR_id_doctor, fecha, hora, tipo, estado, observaciones) VALUES (?, ?, ?, ?, ?, 'pendiente', ?)");
                $stmt->bind_param("iissss", $idPaciente, $doctorParam, $fecha, $hora, $tipo, $observaciones);
                if ($stmt->execute()) {
                    $mensaje = "Cita solicitada correctamente. Queda pendiente de confirmacion.";
                } else {
                    $error = "No se pudo solicitar la cita.";
                }
            }
        }
    }
}
    if ($accion === "cancelar") {
        $idCita = (int) ($_POST["id_cita"] ?? 0);
        $stmt = $conn->prepare("SELECT estado FROM CITA WHERE id_cita = ? AND PACIENTE_id_paciente = ? AND estado IN ('pendiente','confirmada')");
        $stmt->bind_param("ii", $idCita, $idPaciente);
        $stmt->execute();
        $citaActual = $stmt->get_result()->fetch_assoc();

        if ($citaActual) {
            $stmt = $conn->prepare("UPDATE CITA SET estado = 'cancelada' WHERE id_cita = ? AND PACIENTE_id_paciente = ?");
            $stmt->bind_param("ii", $idCita, $idPaciente);
            if ($stmt->execute()) {
                $mensaje = "Cita cancelada correctamente.";
            } else {
                $error = "No se pudo cancelar la cita.";
            }
        } else {
            $error = "Solo puedes cancelar citas pendientes o confirmadas.";
        }
    }
}

$doctores = [];
$res = $conn->query("SELECT D.id_doctor, U.nombre, D.especialidad FROM DOCTOR D JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE D.estado = 'activo' ORDER BY U.nombre");
if ($res) {
    while ($row = $res->fetch_assoc()) $doctores[] = $row;
}

$stmt = $conn->prepare("SELECT C.*, U.nombre AS doctor_nombre, D.especialidad FROM CITA C LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE C.PACIENTE_id_paciente = ? ORDER BY C.fecha DESC, C.hora DESC");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) total, SUM(estado IN ('pendiente','confirmada')) proximas, SUM(estado = 'atendida') atendidas, SUM(estado = 'cancelada') canceladas FROM CITA WHERE PACIENTE_id_paciente = ?");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT C.*, U.nombre AS doctor_nombre FROM CITA C LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE C.PACIENTE_id_paciente = ? AND C.estado IN ('pendiente','confirmada') AND CONCAT(C.fecha, ' ', C.hora) >= NOW() ORDER BY C.fecha ASC, C.hora ASC LIMIT 1");
$stmt->bind_param("i", $idPaciente);
$stmt->execute();
$proximaCita = $stmt->get_result()->fetch_assoc();

if ($busquedaReceta !== "") {
    $like = "%" . $busquedaReceta . "%";
    $stmt = $conn->prepare("SELECT R.*, E.diagnostico, E.tratamiento, U.nombre AS doctor_nombre FROM RECETA R JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE E.PACIENTE_id_paciente = ? AND (R.medicamento LIKE ? OR R.dosis LIKE ? OR R.indicaciones LIKE ? OR E.diagnostico LIKE ?) ORDER BY R.fecha DESC");
    $stmt->bind_param("issss", $idPaciente, $like, $like, $like, $like);
} else {
    $stmt = $conn->prepare("SELECT R.*, E.diagnostico, E.tratamiento, U.nombre AS doctor_nombre FROM RECETA R JOIN EXPEDIENTE E ON E.id_expediente = R.EXPEDIENTE_id_expediente LEFT JOIN DOCTOR D ON D.id_doctor = E.DOCTOR_id_doctor LEFT JOIN USUARIO U ON U.id_usuario = D.USUARIO_id_usuario WHERE E.PACIENTE_id_paciente = ? ORDER BY R.fecha DESC");
    $stmt->bind_param("i", $idPaciente);
}
$stmt->execute();
$recetas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Dental Guru | Paciente</title>

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

/* RECETAS - ESTILO ORIGINAL */
.recipe-summary::-webkit-details-marker { display: none; }
.recipe-summary { list-style: none; }
.recipe-sheet {
    background: linear-gradient(180deg, #ffffff 0%, #f8fcfa 100%);
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    overflow: hidden;
}
.recipe-sheet-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.recipe-field {
    background: white;
    border: 1px solid #eef2ff;
    border-radius: 14px;
    padding: 12px;
}
.recipe-field span {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: var(--primary);
    text-transform: uppercase;
    margin-bottom: 4px;
}
.recipe-field p {
    font-size: 14px;
    color: #1e293b;
    line-height: 1.5;
    white-space: pre-wrap;
}
.recipe-toggle {
    background: var(--secondary);
    color: #1a2e2a;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    transition: .25s ease;
}
.recipe-toggle:hover {
    background: var(--secondary-dark);
    transform: translateY(-1px);
}

/* HORARIOS */
.horario-slot {
    padding: 8px;
    text-align: center;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
}
.horario-disponible {
    background: #eef2ff;
    color: #4338ca;
}
.horario-disponible:hover {
    background: var(--primary);
    color: white;
}
.horario-ocupado {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}
.horario-pasado {
    background: #fef2f2;
    color: #f87171;
    cursor: not-allowed;
    text-decoration: line-through;
}
.horario-seleccionado {
    background: var(--primary);
    color: white;
    outline: 2px solid var(--secondary);
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

/* ANIMACION */
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
            <p class="text-[11px] text-white/70">Area del paciente</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-sm font-semibold text-white hidden md:block"><?php echo e($nombre); ?></div>
        <button onclick="openMenu()" class="text-white text-2xl focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<!-- OVERLAY Y MENU -->
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
        <p class="text-sm font-medium text-white/90"><?php echo e($nombre); ?></p>
        <p class="text-xs text-white/70 mt-1"><i class="fas fa-user mr-1"></i>Paciente</p>
    </div>
    <nav class="space-y-2">
        <a href="?seccion=citas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-calendar-alt w-5"></i> Mis citas</a>
        <a href="?seccion=recetas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-prescription-bottle w-5"></i> Mis recetas</a>
        <a href="?seccion=expediente" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-folder-open w-5"></i> Mi expediente</a>
        <a href="?seccion=agendar" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-plus-circle w-5"></i> Agendar cita</a>
    </nav>
    <hr class="my-4 border-white/20">
    <a href="mi_perfil.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-user-edit w-5"></i> Mi perfil</a>
    <a href="../config/cerrar_sesion.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition"><i class="fas fa-sign-out-alt w-5"></i> Cerrar sesion</a>
</div>

<div class="main-wrapper">
<main class="max-w-7xl mx-auto w-full p-4 md:p-6 fade-in">

<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Panel del Paciente</h2>
        <p class="text-sm text-gray-600">Consulta tus recetas, agenda citas y revisa tu historial clinico.</p>
    </div>
    <div class="flex gap-2 flex-wrap items-center">
        <a href="mi_perfil.php" class="btn-outline text-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.879 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Mi perfil
        </a>
        <a href="#recetas" class="btn-outline text-sm" onclick="document.querySelector('#recetas').scrollIntoView({behavior:'smooth'})">Buscar recetas</a>
        <a href="#agendar" class="btn-main text-sm" onclick="document.querySelector('#agendar').scrollIntoView({behavior:'smooth'})">Agendar cita</a>
    </div>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-emerald-50 text-emerald-700 border border-emerald-200"><?php echo e($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-rose-50 text-rose-700 border border-rose-200"><?php echo e($error); ?></div>
<?php endif; ?>

<!-- TARJETAS RESUMEN -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="card-ui p-4 text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Proxima cita</p>
        <?php if ($proximaCita): ?>
            <h3 class="font-bold text-gray-800 mt-1"><?php echo e(date("d/m/Y", strtotime($proximaCita["fecha"]))); ?></h3>
            <p class="text-xs text-gray-500"><?php echo e(substr($proximaCita["hora"], 0, 5)); ?> - <?php echo e(tipoTexto($proximaCita["tipo"])); ?></p>
        <?php else: ?>
            <h3 class="font-bold text-gray-400 mt-1">Sin cita</h3>
            <p class="text-xs text-gray-400">Agenda una nueva visita</p>
        <?php endif; ?>
    </div>
    <div class="card-ui p-4 text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Atendidas</p>
        <h3 class="text-2xl font-bold text-emerald-600 mt-1"><?php echo (int) ($resumen["atendidas"] ?? 0); ?></h3>
    </div>
    <div class="card-ui p-4 text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Pendientes</p>
        <h3 class="text-2xl font-bold text-amber-600 mt-1"><?php echo (int) ($resumen["proximas"] ?? 0); ?></h3>
    </div>
    <div class="card-ui p-4 text-center">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Recetas</p>
        <h3 class="text-2xl font-bold text-slate-700 mt-1"><?php echo count($recetas); ?></h3>
    </div>
</section>

<div class="grid lg:grid-cols-3 gap-5">
    <!-- COLUMNA IZQUIERDA: AGENDAR + EXPEDIENTE -->
    <section class="space-y-5">
        <div id="agendar" class="card-ui p-5">
            <?php
            date_default_timezone_set('America/El_Salvador');
            $fechaSeleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date("Y-m-d");
            $horaActual = date("H:i");
            $fechaHoy = date("Y-m-d");
            $horarios = [];
            for ($h = 8; $h <= 17; $h++) {
                $horarios[] = sprintf("%02d:00", $h);
                $horarios[] = sprintf("%02d:30", $h);
            }
            $horasOcupadas = [];
            $stmt = $conn->prepare("SELECT hora, estado FROM CITA WHERE fecha = ?");
            $stmt->bind_param("s", $fechaSeleccionada);
            $stmt->execute();
            $resultado = $stmt->get_result();
            while ($fila = $resultado->fetch_assoc()) {
                $horasOcupadas[$fila["hora"]] = $fila["estado"];
            }
            ?>

            <h3 class="font-bold mb-4 text-gray-800">Agendar cita</h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="accion" value="agendar">
                <input type="date" name="fecha" value="<?php echo $fechaSeleccionada; ?>" min="<?php echo date("Y-m-d"); ?>" class="input-ui" required onchange="this.form.submit()">
                <input type="hidden" name="hora" id="horaSeleccionada" required>

                <!-- MAÑANA -->
                <div>
                    <h4 class="font-semibold mb-2 text-gray-700">Mañana</h4>
                    <div class="grid grid-cols-4 gap-2">
                        <?php foreach ($horarios as $hora): if ($hora < "12:00"): 
                            $clase = "horario-disponible";
                            $estadoFinal = "disponible";
                            if ($fechaSeleccionada === $fechaHoy && $hora <= $horaActual) {
                                $clase = "horario-pasado";
                                $estadoFinal = "pasada";
                            }
                            if (isset($horasOcupadas[$hora])) {
                                if ($horasOcupadas[$hora] === "confirmada") {
                                    $clase = "horario-ocupado";
                                    $estadoFinal = "confirmada";
                                } else {
                                    $clase = "horario-ocupado";
                                    $estadoFinal = "pendiente";
                                }
                            }
                        ?>
                        <div class="horario-slot <?php echo $clase; ?>" onclick="seleccionarHora('<?php echo $hora; ?>', this)" data-estado="<?php echo $estadoFinal; ?>"><?php echo $hora; ?></div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <!-- TARDE -->
                <div>
                    <h4 class="font-semibold mb-2 mt-4 text-gray-700">Tarde</h4>
                    <div class="grid grid-cols-4 gap-2">
                        <?php foreach ($horarios as $hora): if ($hora >= "13:00"): 
                            $clase = "horario-disponible";
                            $estadoFinal = "disponible";
                            if ($fechaSeleccionada === $fechaHoy && $hora <= $horaActual) {
                                $clase = "horario-pasado";
                                $estadoFinal = "pasada";
                            }
                            if (isset($horasOcupadas[$hora])) {
                                if ($horasOcupadas[$hora] === "confirmada") {
                                    $clase = "horario-ocupado";
                                    $estadoFinal = "confirmada";
                                } else {
                                    $clase = "horario-ocupado";
                                    $estadoFinal = "pendiente";
                                }
                            }
                        ?>
                        <div class="horario-slot <?php echo $clase; ?>" onclick="seleccionarHora('<?php echo $hora; ?>', this)" data-estado="<?php echo $estadoFinal; ?>"><?php echo $hora; ?></div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <select name="doctor" class="input-ui">
                    <option value="">Cualquier doctor disponible</option>
                    <?php foreach ($doctores as $doc): ?>
                        <option value="<?php echo $doc["id_doctor"]; ?>"><?php echo e($doc["nombre"]); ?> - <?php echo e($doc["especialidad"]); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="tipo" class="input-ui" required>
                    <option value="">Tipo de cita</option>
                    <option value="limpieza">Limpieza dental</option>
                    <option value="revision">Revisión general</option>
                    <option value="emergencia">Emergencia</option>
                    <option value="otros">Otros</option>
                </select>

                <textarea name="observaciones" class="input-ui" rows="3" placeholder="Motivo o comentario para recepción"></textarea>

                <button class="btn-main w-full justify-center">Solicitar cita</button>
            </form>

            <div class="card-ui p-5 mt-5">
                <h3 class="font-bold mb-4 text-gray-800">Mi expediente</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>Nombre:</strong> <?php echo e($paciente["nombre"]); ?></p>
                    <p><strong>Correo:</strong> <?php echo e($paciente["correo"]); ?></p>
                    <p><strong>Telefono:</strong> <?php echo e($paciente["telefono"] ?: "Sin registrar"); ?></p>
                    <p><strong>Edad:</strong> <?php echo e($paciente["edad"] ?: "Sin registrar"); ?></p>
                    <p><strong>Tipo sangre:</strong> <?php echo e($paciente["tipo_sangre"] ?: "Sin registrar"); ?></p>
                    <p><strong>Alergias:</strong> <?php echo e($paciente["alergias"] ?: "Sin registrar"); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- COLUMNA DERECHA: RECETAS + HISTORIAL -->
    <section class="lg:col-span-2 space-y-5">
        <div id="recetas" class="card-ui p-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <h3 class="font-bold text-gray-800">Mis recetas</h3>
                <form method="GET" class="flex gap-2">
                    <input type="text" name="buscar_receta" value="<?php echo e($busquedaReceta); ?>" class="input-ui text-sm" placeholder="Medicamento, dosis o diagnostico">
                    <button type="submit" class="btn-main text-sm">Buscar</button>
                </form>
            </div>

            <div class="space-y-3">
                <?php if (!$recetas): ?>
                    <div class="border rounded-2xl p-4 text-sm text-gray-500">No hay recetas registradas para mostrar.</div>
                <?php endif; ?>

                <?php foreach ($recetas as $receta): ?>
                <details class="recipe-row border rounded-2xl overflow-hidden bg-white">
                    <summary class="recipe-summary p-4 cursor-pointer hover:bg-gray-50 flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <h4 class="font-semibold text-sm"><?php echo e($receta["medicamento"] ?: "Medicamento sin nombre"); ?></h4>
                                <span class="badge-fix bg-emerald-100 text-emerald-700">Receta</span>
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
                                    <p class="text-xs font-bold text-[#7c6fb0] uppercase">Dental Guru</p>
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
                                <div class="border-t border-dashed border-[#e2e8f0] pt-3 text-xs text-gray-500 flex flex-col md:flex-row md:justify-between gap-2">
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

        <!-- HISTORIAL DE CITAS -->
        <div class="card-ui p-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <h3 class="font-bold text-gray-800">Historial de citas</h3>
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
                                <button class="btn-outline text-sm text-rose-600 border-rose-200 hover:bg-rose-600 hover:text-white"><i class="fas fa-times mr-1"></i>Cancelar cita</button>
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
    <p class="mt-2 text-xs">Sistema clinico Dental Guru</p>
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

function seleccionarHora(hora, elemento) {
    let estado = elemento.getAttribute("data-estado");
    if (estado !== "disponible") return;
    document.querySelectorAll("[data-estado='disponible']").forEach(e => e.classList.remove("ring-4", "ring-blue-900"));
    elemento.classList.add("ring-4", "ring-blue-900");
    document.getElementById("horaSeleccionada").value = hora;
}

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