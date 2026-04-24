<?php
session_start();

// Validar sesión REAL
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// Obtener rol
$rol = $_SESSION['rol'];

// Redirección según rol
switch ($rol) {

    case "admin":
        header("Location: pages/admin.php");
        exit();

    case "doctor":
        header("Location: pages/doctor.php");
        exit();

    case "paciente":
        header("Location: pages/paciente.php");
        exit();

    case "recepcion":
        header("Location: pages/recepcion.php");
        exit();

    default:
        echo "⚠️ Rol no válido";
        exit();
}
?>