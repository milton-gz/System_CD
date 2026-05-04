<?php
session_start();
require_once "../config/conexion.php";

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'doctor') {
    switch ($_SESSION['rol'] ?? '') {
        case "admin":
            header("Location: admin.php");
            break;
        case "paciente":
            header("Location: paciente.php");
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

$idUsuario = (int) $_SESSION["id"];
$nombreDoctor = $_SESSION["nombre"];
$mensaje = "";
$error = "";

// Pequeño helper para imprimir datos de pacientes, citas y recetas sin exponer HTML.
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
        "pendiente" => "bg-yellow-100 text-yellow-700",
        "confirmada" => "bg-green-100 text-green-700",
        "atendida" => "bg-blue-100 text-blue-700",
        "cancelada" => "bg-red-100 text-red-700"
    ];
    return $map[$estado] ?? "bg-gray-100 text-gray-700";
}

// Guarda cambios de estado para que la cita tenga trazabilidad basica.
function registrarHistorial($conn, $idCita, $anterior, $nuevo) {
    if ($anterior === $nuevo) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO HISTORIAL_CITA (CITA_id_cita, estado_anterior, estado_nuevo)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $idCita, $anterior, $nuevo);
    $stmt->execute();
}

$stmt = $conn->prepare("
    SELECT *
    FROM DOCTOR
    WHERE USUARIO_id_usuario = ?
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    $especialidad = "Odontologia general";
    $stmt = $conn->prepare("
        INSERT INTO DOCTOR (USUARIO_id_usuario, especialidad, estado)
        VALUES (?, ?, 'activo')
    ");
    $stmt->bind_param("is", $idUsuario, $especialidad);
    $stmt->execute();

    $stmt = $conn->prepare("
        SELECT *
        FROM DOCTOR
        WHERE USUARIO_id_usuario = ?
    ");
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

$idDoctor = (int) $doctor["id_doctor"];

// Acciones principales del doctor: tomar/confirmar citas y cerrar la atencion con expediente y receta.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";
    $idCita = (int) ($_POST["id_cita"] ?? 0);

    $stmt = $conn->prepare("
        SELECT C.*
        FROM CITA C
        WHERE C.id_cita = ?
          AND (C.DOCTOR_id_doctor = ? OR C.DOCTOR_id_doctor IS NULL)
    ");
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
            $stmt = $conn->prepare("
                UPDATE CITA
                SET DOCTOR_id_doctor = ?, estado = ?
                WHERE id_cita = ?
            ");
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
                // Expediente, receta y estado de cita se guardan juntos para no dejar una consulta a medias.
                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare("
                        UPDATE CITA
                        SET DOCTOR_id_doctor = ?, estado = 'atendida'
                        WHERE id_cita = ?
                    ");
                    $stmt->bind_param("ii", $idDoctor, $idCita);
                    $stmt->execute();

                    $estadoExpediente = "cerrado";
                    $idPacienteCita = (int) $cita["PACIENTE_id_paciente"];

                    $stmt = $conn->prepare("
                        INSERT INTO EXPEDIENTE
                            (PACIENTE_id_paciente, DOCTOR_id_doctor, CITA_id_cita, diagnostico, tratamiento, notas, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "iiissss",
                        $idPacienteCita,
                        $idDoctor,
                        $idCita,
                        $diagnostico,
                        $tratamiento,
                        $notas,
                        $estadoExpediente
                    );
                    $stmt->execute();
                    $idExpediente = $stmt->insert_id;

                    if ($medicamento !== "" || $dosis !== "" || $indicaciones !== "") {
                        $stmt = $conn->prepare("
                            INSERT INTO RECETA (EXPEDIENTE_id_expediente, medicamento, dosis, indicaciones)
                            VALUES (?, ?, ?, ?)
                        ");
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

// El doctor ve sus citas asignadas y tambien solicitudes pendientes sin doctor.
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(C.fecha = CURDATE() AND C.estado IN ('pendiente','confirmada')) AS hoy,
        SUM(C.estado = 'atendida') AS atendidas,
        SUM(C.estado IN ('pendiente','confirmada')) AS pendientes,
        SUM(C.tipo = 'emergencia' AND C.estado IN ('pendiente','confirmada')) AS emergencias
    FROM CITA C
    WHERE C.DOCTOR_id_doctor = ?
       OR (C.DOCTOR_id_doctor IS NULL AND C.estado = 'pendiente')
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
    WHERE C.DOCTOR_id_doctor = ?
       OR (C.DOCTOR_id_doctor IS NULL AND C.estado = 'pendiente')
    ORDER BY
        FIELD(C.estado, 'pendiente', 'confirmada', 'atendida', 'cancelada'),
        C.fecha ASC,
        C.hora ASC
");
$stmt->bind_param("i", $idDoctor);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Guru | Doctor</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>

<style>
:root{--primary:#b18ddd;--secondary-soft:#e4f3ea;}
html,body{height:100%;margin:0;}
body{background:var(--secondary-soft);display:flex;flex-direction:column;}
.header-ui{background:var(--primary);box-shadow:0 6px 18px rgba(0,0,0,.08);}
.card-ui{background:white;border-radius:20px;box-shadow:0 8px 18px rgba(0,0,0,.06);transition:.25s;}
.card-ui:hover{transform:translateY(-2px);}
.badge-fix{display:inline-flex;align-items:center;justify-content:center;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;}
.footer-ui{background:var(--primary);margin-top:auto;}
.team-container{position:relative;display:inline-block;}
.team-tooltip{position:absolute;bottom:120%;left:50%;transform:translateX(-50%) translateY(10px);background:white;padding:12px;border-radius:14px;box-shadow:0 12px 25px rgba(0,0,0,.12);font-size:12px;opacity:0;transition:.25s;min-width:300px;text-align:left;}
.team-tooltip::after{content:"";position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:white;}
.team-container:hover .team-tooltip{opacity:1;transform:translateX(-50%) translateY(0);}
.filter-btn.active{background:#6FAE84;color:white;border-color:#6FAE84;}
.paciente[hidden]{display:none;}
</style>
</head>

<body>
<header class="header-ui px-6 py-3 flex items-center justify-between">
<img src="../assets/logo.png" class="w-28 rounded-xl shadow" alt="Dental Guru">
<div class="flex items-center gap-4">
<div class="text-sm font-semibold text-gray-900"><?php echo e($nombreDoctor); ?></div>
<a href="../config/cerrar_sesion.php" class="text-xs font-semibold text-gray-900 hover:underline">Cerrar sesion</a>
</div>
</header>

<main class="flex-1 max-w-7xl mx-auto w-full p-4 md:p-6">
<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-4">
<div>
<h2 class="text-2xl font-bold">Panel del Doctor</h2>
<p class="text-sm text-gray-600"><?php echo e($doctor["especialidad"] ?? "Odontologia general"); ?> - solicitudes y citas asignadas.</p>
</div>
<a href="#listaPacientes" class="btn-main text-sm">Ver citas</a>
</div>

<?php if ($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-green-50 text-green-700 border border-green-200"><?php echo e($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-red-50 text-red-700 border border-red-200"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
<div class="card-ui p-4"><p class="text-xs text-gray-500">Citas hoy</p><h3 class="text-xl font-bold"><?php echo (int) ($resumen["hoy"] ?? 0); ?></h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Atendidas</p><h3 class="text-xl font-bold text-green-600"><?php echo (int) ($resumen["atendidas"] ?? 0); ?></h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Pendientes</p><h3 class="text-xl font-bold text-yellow-600"><?php echo (int) ($resumen["pendientes"] ?? 0); ?></h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Emergencias</p><h3 class="text-xl font-bold text-red-600"><?php echo (int) ($resumen["emergencias"] ?? 0); ?></h3></div>
</section>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
<div class="flex gap-2 flex-wrap">
<button class="filter-btn active px-3 py-1 rounded-full text-xs border" data-filter="all">Todos</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="pendiente">Pendientes</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="confirmada">Confirmadas</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="atendida">Atendidas</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="emergencia">Emergencias</button>
</div>

<input type="text" id="search" placeholder="Buscar paciente..." class="input-ui px-3 py-2 rounded-xl text-sm w-full md:w-64">
</div>

<section class="card-ui p-5">
<h3 class="font-bold mb-4">Citas por atender</h3>

<div id="listaPacientes" class="space-y-3">
<?php if (!$citas): ?>
<div class="border rounded-2xl p-4 text-sm text-gray-500">No hay citas pendientes o asignadas para mostrar.</div>
<?php endif; ?>

<?php foreach ($citas as $cita): ?>
<?php
$textoBusqueda = strtolower(($cita["paciente_nombre"] ?? "") . " " . ($cita["tipo"] ?? "") . " " . ($cita["estado"] ?? ""));
$puedeTomar = (int) $cita["sin_asignar"] === 1 && $cita["estado"] === "pendiente";
$puedeAtender = in_array($cita["estado"], ["pendiente", "confirmada"], true);
?>
<details class="paciente border rounded-2xl overflow-hidden" data-estado="<?php echo e($cita["estado"]); ?>" data-tipo="<?php echo e($cita["tipo"]); ?>" data-nombre="<?php echo e($textoBusqueda); ?>">
<summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center gap-3">
<div>
<h4 class="font-semibold text-sm"><?php echo e($cita["paciente_nombre"]); ?></h4>
<p class="text-xs text-gray-500">
<?php echo e(date("d/m/Y", strtotime($cita["fecha"]))); ?> - <?php echo e(substr($cita["hora"], 0, 5)); ?> - <?php echo e(tipoTexto($cita["tipo"])); ?>
<?php echo $puedeTomar ? " - Sin asignar" : ""; ?>
</p>
</div>
<span class="badge-fix <?php echo badgeEstado($cita["estado"]); ?>"><?php echo e($cita["estado"]); ?></span>
</summary>

<div class="p-4 bg-gray-50 text-sm space-y-4">
<div class="grid md:grid-cols-2 gap-2">
<p><strong>Correo:</strong> <?php echo e($cita["paciente_correo"]); ?></p>
<p><strong>Telefono:</strong> <?php echo e($cita["telefono"] ?: "Sin registrar"); ?></p>
<p><strong>Edad:</strong> <?php echo e($cita["edad"] ?: "Sin registrar"); ?></p>
<p><strong>Tipo sangre:</strong> <?php echo e($cita["tipo_sangre"] ?: "Sin registrar"); ?></p>
<p><strong>Alergias:</strong> <?php echo e($cita["alergias"] ?: "Sin registrar"); ?></p>
<p><strong>Previas:</strong> <?php echo e($cita["enfermedades_previas"] ?: "Sin registrar"); ?></p>
</div>

<p><strong>Observaciones del paciente:</strong> <?php echo e($cita["observaciones"] ?: "Sin observaciones"); ?></p>

<?php if ($puedeTomar): ?>
<form method="POST">
<input type="hidden" name="accion" value="tomar">
<input type="hidden" name="id_cita" value="<?php echo (int) $cita["id_cita"]; ?>">
<button class="btn-main">Tomar y confirmar cita</button>
</form>
<?php endif; ?>

<?php if ($puedeAtender): ?>
<form method="POST" class="space-y-3">
<input type="hidden" name="accion" value="atender">
<input type="hidden" name="id_cita" value="<?php echo (int) $cita["id_cita"]; ?>">

<div class="grid md:grid-cols-2 gap-3">
<textarea name="diagnostico" class="input-ui" rows="3" placeholder="Diagnostico..." required></textarea>
<textarea name="tratamiento" class="input-ui" rows="3" placeholder="Tratamiento..." required></textarea>
</div>

<textarea name="notas" class="input-ui" rows="2" placeholder="Notas internas..."></textarea>

<div class="grid md:grid-cols-3 gap-3">
<input type="text" name="medicamento" class="input-ui" placeholder="Medicamento">
<input type="text" name="dosis" class="input-ui" placeholder="Dosis">
<input type="text" name="indicaciones" class="input-ui" placeholder="Indicaciones">
</div>

<div class="flex gap-2 flex-wrap">
<?php if ($cita["estado"] === "pendiente" && !$puedeTomar): ?>
<button type="submit" name="accion" value="confirmar" class="btn-outline" formnovalidate>Solo confirmar</button>
<?php endif; ?>
<button class="btn-main">Guardar y marcar atendida</button>
</div>
</form>
<?php endif; ?>
</div>
</details>
<?php endforeach; ?>
</div>
</section>
</main>

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
const botones = document.querySelectorAll(".filter-btn");
const pacientes = document.querySelectorAll(".paciente");
const search = document.getElementById("search");
let filtroActivo = "all";

function filtrarPacientes() {
    const valor = search ? search.value.toLowerCase() : "";

    pacientes.forEach(p => {
        const coincideFiltro =
            filtroActivo === "all" ||
            p.dataset.estado === filtroActivo ||
            p.dataset.tipo === filtroActivo;
        const coincideBusqueda = p.dataset.nombre.includes(valor);
        p.hidden = !(coincideFiltro && coincideBusqueda);
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
