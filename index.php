
<?php

// el index.php y el archivo validacion son los mismo por el echo que si el usuario tiene
// una sesión activa lo redirige el navegador busca el index.php 
//  y este a su vez lo redirige a su página según su rol
// el index.php es el punto de entrada para validar la sesión y redirigir al usuario a su página correspondiente
// si no tiene contraseña activa lo redirige al login.php y el login redirige a validacion.php
//  para validar la sesión y redirigir al usuario a su página correspondiente
session_start();

// Validar sesión REAL
if (!isset($_SESSION['rol'])) {
    header("Location: frontend/login.php");
    exit();
}

// Obtener rol
$rol = $_SESSION['rol'];

// Redirección según rol
switch ($rol) {

    case "admin":
        header("Location: frontend/pages/admin.php");
        exit();

    case "doctor":
        header("Location: frontend/pages/doctor.php");
        exit();

    case "paciente":
        header("Location: frontend/pages/paciente.php");
        exit();

    case "recepcion":
        header("Location: frontend/pages/recepcion.php");
        exit();

    default:
        echo "⚠️ Rol no válido";
        exit();
}
?>