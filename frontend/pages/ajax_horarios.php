<?php
session_start();
require_once "../config/conexion.php";
header('Content-Type: application/json');

$fecha = $_GET['fecha'] ?? '';
$doctor = $_GET['doctor'] ?? '';

if (!$fecha) {
    echo json_encode(['manana' => [], 'tarde' => []]);
    exit;
}

date_default_timezone_set('America/El_Salvador');
$hoy = date("Y-m-d");
$horaActual = date("H:i");

$horarios = [];
for ($h = 8; $h <= 17; $h++) {
    $horarios[] = sprintf("%02d:00", $h);
    $horarios[] = sprintf("%02d:30", $h);
}

// Obtener citas ocupadas en esa fecha (estado pendiente o confirmada)
if ($doctor !== "") {
    $stmt = $conn->prepare("SELECT hora FROM CITA WHERE fecha = ? AND DOCTOR_id_doctor = ? AND estado IN ('pendiente','confirmada')");
    $stmt->bind_param("si", $fecha, $doctor);
} else {
    $stmt = $conn->prepare("SELECT hora FROM CITA WHERE fecha = ? AND estado IN ('pendiente','confirmada')");
    $stmt->bind_param("s", $fecha);
}
$stmt->execute();
$result = $stmt->get_result();
$ocupadas = [];
while ($row = $result->fetch_assoc()) {
    $ocupadas[] = $row['hora'];
}

$manana = [];
$tarde = [];
foreach ($horarios as $hora) {
    $estado = 'disponible';
    if (in_array($hora, $ocupadas)) {
        $estado = 'ocupado';
    } elseif ($fecha == $hoy && $hora <= $horaActual) {
        $estado = 'pasado';
    }
    $slot = ['hora' => $hora, 'estado' => $estado];
    if ($hora < "12:00") $manana[] = $slot;
    else $tarde[] = $slot;
}

echo json_encode(['manana' => $manana, 'tarde' => $tarde]);
?>