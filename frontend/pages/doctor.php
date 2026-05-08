<?php
session_start();
require_once "../config/conexion.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'doctor') {
    switch ($_SESSION['rol'] ?? '') {
        case "admin": header("Location: admin.php"); break;
        case "paciente": header("Location: paciente.php"); break;
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

$idUsuario = (int) $_SESSION["id"];
$nombreDoctor = $_SESSION["nombre"];
$mensaje = "";
$error = "";

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
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

function badgeEstado($estado) {
    $map = [
        "pendiente" => "bg-amber-100 text-amber-700",
        "confirmada" => "bg-emerald-100 text-emerald-700",
        "atendida" => "bg-sky-100 text-sky-700",
        "cancelada" => "bg-rose-100 text-rose-700"
    ];
    return $map[$estado] ?? "bg-gray-100 text-gray-700";
}

function registrarHistorial($conn, $idCita, $anterior, $nuevo) {
    if ($anterior === $nuevo) return;
    $stmt = $conn->prepare("INSERT INTO HISTORIAL_CITA (CITA_id_cita, estado_anterior, estado_nuevo) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $idCita, $anterior, $nuevo);
    $stmt->execute();
}

$stmt = $conn->prepare("SELECT * FROM DOCTOR WHERE USUARIO_id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    $especialidad = "Odontologia general";
    $stmt = $conn->prepare("INSERT INTO DOCTOR (USUARIO_id_usuario, especialidad, estado) VALUES (?, ?, 'activo')");
    $stmt->bind_param("is", $idUsuario, $especialidad);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT * FROM DOCTOR WHERE USUARIO_id_usuario = ?");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

$idDoctor = (int) $doctor["id_doctor"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";
    $idCita = (int) ($_POST["id_cita"] ?? 0);

    $stmt = $conn->prepare("SELECT C.* FROM CITA C WHERE C.id_cita = ? AND (C.DOCTOR_id_doctor = ? OR C.DOCTOR_id_doctor IS NULL)");
    $stmt->bind_param("ii", $idCita, $idDoctor);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();

    if (!$cita) {
        $error = "No se encontro la cita o ya fue tomada por otro doctor.";
    } elseif ($accion === "tomar" || $accion === "confirmar") {
        if (!in_array($cita["estado"], ["pendiente", "confirmada"], true)) {
            $error = "Solo puedes tomar o confirmar citas pendientes.";
        } else {
            $estadoAnterior = $cita["estado"];
            $estadoNuevo = "confirmada";
            $stmt = $conn->prepare("UPDATE CITA SET DOCTOR_id_doctor = ?, estado = ? WHERE id_cita = ?");
            $stmt->bind_param("isi", $idDoctor, $estadoNuevo, $idCita);
            if ($stmt->execute()) {
                registrarHistorial($conn, $idCita, $estadoAnterior, $estadoNuevo);
                $mensaje = "Cita confirmada y asignada a tu agenda.";
            } else {
                $error = "No se pudo confirmar la cita.";
            }
        }
    } elseif ($accion === "atender") {
        if (in_array($cita["estado"], ["cancelada", "atendida"], true)) {
            $error = "Esta cita no puede atenderse.";
        } else {
            $diagnostico = trim($_POST["diagnostico"] ?? "");
            $tratamiento = trim($_POST["tratamiento"] ?? "");
            $notas = trim($_POST["notas"] ?? "");
            $medicamento = trim($_POST["medicamento"] ?? "");
            $dosis = trim($_POST["dosis"] ?? "");
            $indicaciones = trim($_POST["indicaciones"] ?? "");

            if ($diagnostico === "" || $tratamiento === "") {
                $error = "Agrega diagnostico y tratamiento para marcar la cita como atendida.";
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE CITA SET DOCTOR_id_doctor = ?, estado = 'atendida' WHERE id_cita = ?");
                    $stmt->bind_param("ii", $idDoctor, $idCita);
                    $stmt->execute();

                    $estadoExpediente = "cerrado";
                    $idPacienteCita = (int) $cita["PACIENTE_id_paciente"];

                    $stmt = $conn->prepare("INSERT INTO EXPEDIENTE (PACIENTE_id_paciente, DOCTOR_id_doctor, CITA_id_cita, diagnostico, tratamiento, notas, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiissss", $idPacienteCita, $idDoctor, $idCita, $diagnostico, $tratamiento, $notas, $estadoExpediente);
                    $stmt->execute();
                    $idExpediente = $stmt->insert_id;

                    if ($medicamento !== "" || $dosis !== "" || $indicaciones !== "") {
                        $stmt = $conn->prepare("INSERT INTO RECETA (EXPEDIENTE_id_expediente, medicamento, dosis, indicaciones) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("isss", $idExpediente, $medicamento, $dosis, $indicaciones);
                        $stmt->execute();
                    }

                    registrarHistorial($conn, $idCita, $cita["estado"], "atendida");
                    $conn->commit();
                    $mensaje = "Consulta atendida. El expediente y la receta quedaron guardados.";
                } catch (Throwable $ex) {
                    $conn->rollback();
                    $error = "No se pudo guardar la atencion: " . $ex->getMessage();
                }
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(C.fecha = CURDATE() AND C.estado IN ('pendiente','confirmada')) AS hoy,
        SUM(C.estado = 'atendida') AS atendidas,
        SUM(C.estado IN ('pendiente','confirmada')) AS pendientes,
        SUM(C.tipo = 'emergencia' AND C.estado IN ('pendiente','confirmada')) AS emergencias
    FROM CITA C
    WHERE C.DOCTOR_id_doctor = ? OR (C.DOCTOR_id_doctor IS NULL AND C.estado = 'pendiente')
");
$stmt->bind_param("i", $idDoctor);
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT
        C.*,
        U.nombre AS paciente_nombre,
        U.correo AS paciente_correo,
        P.edad,
        P.telefono,
        P.tipo_sangre,
        P.alergias,
        P.enfermedades_previas,
        CASE WHEN C.DOCTOR_id_doctor IS NULL THEN 1 ELSE 0 END AS sin_asignar
    FROM CITA C
    JOIN PACIENTE P ON P.id_paciente = C.PACIENTE_id_paciente
    JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario
    WHERE C.DOCTOR_id_doctor = ? OR (C.DOCTOR_id_doctor IS NULL AND C.estado = 'pendiente')
    ORDER BY FIELD(C.estado, 'pendiente', 'confirmada', 'atendida', 'cancelada'), C.fecha ASC, C.hora ASC
");
$stmt->bind_param("i", $idDoctor);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Dental Guru | Doctor</title>

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

/* FILTROS */
.filter-btn {
    padding: 6px 16px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    background: #f1f5f9;
    color: #475569;
    border: none;
}
.filter-btn.active {
    background: var(--secondary);
    color: #1a2e2a;
}
.filter-btn:hover:not(.active) {
    background: #e2e8f0;
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
            <p class="text-[11px] text-white/70">Area del doctor</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <div class="text-sm font-semibold text-white hidden md:block"><?php echo e($nombreDoctor); ?></div>
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
        <p class="text-sm font-medium text-white/90"><?php echo e($nombreDoctor); ?></p>
        <p class="text-xs text-white/70 mt-1"><i class="fas fa-stethoscope mr-1"></i><?php echo e($doctor["especialidad"] ?? "Odontologia general"); ?></p>
    </div>
    <nav class="space-y-2">
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/20 transition"><i class="fas fa-home w-5"></i> Inicio</a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-calendar-alt w-5"></i> Mis citas</a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-history w-5"></i> Historial</a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-file-prescription w-5"></i> Mis recetas</a>
    </nav>
    <hr class="my-4 border-white/20">
    <a href="../config/cerrar_sesion.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition"><i class="fas fa-sign-out-alt w-5"></i> Cerrar sesion</a>
</div>

<!-- MAIN CONTENT -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4 md:p-6 fade-in">

<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Panel del Doctor</h2>
        <p class="text-sm text-gray-500"><?php echo e($doctor["especialidad"] ?? "Odontologia general"); ?> - solicitudes y citas asignadas</p>
    </div>
    <a href="#listaPacientes" class="btn-main text-sm"><i class="fas fa-calendar-check mr-1"></i> Ver citas</a>
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

<!-- TARJETAS RESUMEN -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="card-ui p-4 text-center">
        <i class="fas fa-calendar-day text-2xl text-[#7c6fb0]/40 mb-1"></i>
        <p class="text-xs text-gray-500 uppercase tracking-wide">Citas hoy</p>
        <h3 class="text-2xl font-bold text-gray-800"><?php echo (int) ($resumen["hoy"] ?? 0); ?></h3>
    </div>
    <div class="card-ui p-4 text-center">
        <i class="fas fa-check-circle text-2xl text-emerald-400 mb-1"></i>
        <p class="text-xs text-gray-500 uppercase tracking-wide">Atendidas</p>
        <h3 class="text-2xl font-bold text-emerald-600"><?php echo (int) ($resumen["atendidas"] ?? 0); ?></h3>
    </div>
    <div class="card-ui p-4 text-center">
        <i class="fas fa-clock text-2xl text-amber-400 mb-1"></i>
        <p class="text-xs text-gray-500 uppercase tracking-wide">Pendientes</p>
        <h3 class="text-2xl font-bold text-amber-600"><?php echo (int) ($resumen["pendientes"] ?? 0); ?></h3>
    </div>
    <div class="card-ui p-4 text-center">
        <i class="fas fa-ambulance text-2xl text-rose-400 mb-1"></i>
        <p class="text-xs text-gray-500 uppercase tracking-wide">Emergencias</p>
        <h3 class="text-2xl font-bold text-rose-600"><?php echo (int) ($resumen["emergencias"] ?? 0); ?></h3>
    </div>
</section>

<!-- FILTROS Y BUSCADOR -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <div class="flex gap-2 flex-wrap">
        <button class="filter-btn active" data-filter="all">Todos</button>
        <button class="filter-btn" data-filter="pendiente">Pendientes</button>
        <button class="filter-btn" data-filter="confirmada">Confirmadas</button>
        <button class="filter-btn" data-filter="atendida">Atendidas</button>
        <button class="filter-btn" data-filter="emergencia">Emergencias</button>
    </div>
    <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="search" placeholder="Buscar paciente..." class="input-ui pl-9 py-2 text-sm w-full md:w-64">
    </div>
</div>

<!-- LISTA DE CITAS -->
<section class="card-ui p-5">
    <h3 class="font-bold text-gray-800 mb-4"><i class="fas fa-list mr-2 text-[#7c6fb0]"></i>Citas por atender</h3>

    <div id="listaPacientes" class="space-y-3">
        <?php if (!$citas): ?>
            <div class="text-center py-8 text-gray-400"><i class="fas fa-inbox text-3xl mb-2 block"></i>No hay citas pendientes o asignadas</div>
        <?php endif; ?>

        <?php foreach ($citas as $cita): ?>
        <?php
        $textoBusqueda = strtolower(($cita["paciente_nombre"] ?? "") . " " . ($cita["tipo"] ?? "") . " " . ($cita["estado"] ?? ""));
        $puedeTomar = (int) $cita["sin_asignar"] === 1 && $cita["estado"] === "pendiente";
        $puedeAtender = in_array($cita["estado"], ["pendiente", "confirmada"], true);
        $esEmergencia = $cita["tipo"] === "emergencia";
        ?>
        <details class="paciente border rounded-xl overflow-hidden bg-white" data-estado="<?php echo e($cita["estado"]); ?>" data-tipo="<?php echo e($cita["tipo"]); ?>" data-nombre="<?php echo e($textoBusqueda); ?>">
            <summary class="p-4 cursor-pointer hover:bg-gray-50 flex flex-wrap justify-between items-center gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="font-semibold text-gray-800 text-sm"><?php echo e($cita["paciente_nombre"]); ?></h4>
                        <?php if ($esEmergencia): ?>
                            <span class="text-[10px] bg-rose-100 text-rose-600 px-2 py-0.5 rounded-full"><i class="fas fa-exclamation-circle"></i> Urgente</span>
                        <?php endif; ?>
                        <?php if ($puedeTomar): ?>
                            <span class="text-[10px] bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">Sin asignar</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="far fa-calendar-alt mr-1"></i><?php echo e(date("d/m/Y", strtotime($cita["fecha"]))); ?> - <?php echo e(substr($cita["hora"], 0, 5)); ?> - <?php echo e(tipoTexto($cita["tipo"])); ?>
                    </p>
                </div>
                <span class="badge-fix <?php echo badgeEstado($cita["estado"]); ?>"><?php echo e($cita["estado"]); ?></span>
            </summary>

            <div class="p-4 bg-gray-50 text-sm space-y-4">
                <!-- DATOS DEL PACIENTE -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-white p-3 rounded-xl"><i class="fas fa-envelope text-[#7c6fb0] w-5"></i> <?php echo e($cita["paciente_correo"]); ?></div>
                    <div class="bg-white p-3 rounded-xl"><i class="fas fa-phone text-[#7c6fb0] w-5"></i> <?php echo e($cita["telefono"] ?: "No registrado"); ?></div>
                    <div class="bg-white p-3 rounded-xl"><i class="fas fa-calendar-alt text-[#7c6fb0] w-5"></i> Edad: <?php echo e($cita["edad"] ?: "No registrada"); ?></div>
                    <div class="bg-white p-3 rounded-xl"><i class="fas fa-tint text-[#7c6fb0] w-5"></i> Tipo sangre: <?php echo e($cita["tipo_sangre"] ?: "No registrado"); ?></div>
                    <div class="bg-white p-3 rounded-xl md:col-span-2"><i class="fas fa-allergies text-[#7c6fb0] w-5"></i> Alergias: <?php echo e($cita["alergias"] ?: "Ninguna"); ?></div>
                    <div class="bg-white p-3 rounded-xl md:col-span-2"><i class="fas fa-notes-medical text-[#7c6fb0] w-5"></i> Enfermedades previas: <?php echo e($cita["enfermedades_previas"] ?: "Ninguna"); ?></div>
                </div>

                <div class="bg-amber-50 p-3 rounded-xl">
                    <p class="text-xs text-amber-600 mb-1"><i class="fas fa-comment mr-1"></i>Observaciones del paciente</p>
                    <p class="text-sm"><?php echo e($cita["observaciones"] ?: "Sin observaciones"); ?></p>
                </div>

                <?php if ($puedeTomar): ?>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="accion" value="tomar">
                    <input type="hidden" name="id_cita" value="<?php echo (int) $cita["id_cita"]; ?>">
                    <button class="btn-main"><i class="fas fa-hand-paper"></i> Tomar y confirmar cita</button>
                </form>
                <?php endif; ?>

                <?php if ($puedeAtender): ?>
                <form method="POST" class="space-y-3 mt-3">
                    <input type="hidden" name="accion" value="atender">
                    <input type="hidden" name="id_cita" value="<?php echo (int) $cita["id_cita"]; ?>">

                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Diagnostico *</label>
                            <textarea name="diagnostico" class="input-ui" rows="3" placeholder="Diagnostico..." required></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tratamiento *</label>
                            <textarea name="tratamiento" class="input-ui" rows="3" placeholder="Tratamiento..." required></textarea>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notas internas</label>
                        <textarea name="notas" class="input-ui" rows="2" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <div class="grid md:grid-cols-3 gap-3">
                        <input type="text" name="medicamento" class="input-ui" placeholder="Medicamento">
                        <input type="text" name="dosis" class="input-ui" placeholder="Dosis">
                        <input type="text" name="indicaciones" class="input-ui" placeholder="Indicaciones">
                    </div>

                    <div class="flex gap-2 flex-wrap">
                        <button type="submit" class="btn-main"><i class="fas fa-save"></i> Guardar y marcar atendida</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</section>

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

// FILTROS Y BUSCADOR
const botones = document.querySelectorAll(".filter-btn");
const pacientes = document.querySelectorAll(".paciente");
const search = document.getElementById("search");
let filtroActivo = "all";

function filtrarPacientes() {
    const valor = search ? search.value.toLowerCase() : "";

    pacientes.forEach(p => {
        const coincideFiltro = filtroActivo === "all" ||
            p.dataset.estado === filtroActivo ||
            p.dataset.tipo === filtroActivo;
        const coincideBusqueda = p.dataset.nombre.includes(valor);
        p.style.display = (coincideFiltro && coincideBusqueda) ? "" : "none";
    });
}

botones.forEach(btn => {
    btn.addEventListener("click", () => {
        botones.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        filtroActivo = btn.dataset.filter;
        filtrarPacientes();
    });
});

if (search) {
    search.addEventListener("input", filtrarPacientes);
}
</script>

</body>
</html>