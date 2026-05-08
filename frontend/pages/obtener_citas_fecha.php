<?php
session_start();
require_once "../config/conexion.php";
header('Content-Type: application/json');

if (!isset($_GET['fecha']) || empty($_GET['fecha'])) {
    echo json_encode(['ocupadas' => []]);
    exit();
}
$fecha = $_GET['fecha'];
$stmt = $conn->prepare("SELECT hora FROM CITA WHERE fecha = ? AND estado IN ('pendiente','confirmada')");
$stmt->bind_param("s", $fecha);
$stmt->execute();
$res = $stmt->get_result();
$ocupadas = [];
while ($row = $res->fetch_assoc()) {
    $ocupadas[] = $row['hora'];
}
echo json_encode(['ocupadas' => $ocupadas]);
?>