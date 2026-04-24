<?php

define("BASE_URL", "/System_CD/");
// Configuración de la conexión a la base de datos
$host = "localhost"; // Dirección del servidor de la base de datos
$username = "root"; // Nombre de usuario para la conexión
$password = ""; // Contraseña para la conexión
$database = "clinica"; // Nombre de la base de datos

// Crear una nueva instancia de mysqli para conectar a la base de datos
$conn = new mysqli($host, $username, $password, $database);

// Verificar si la conexión falló
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error); // Terminar el script y mostrar el error
}
?>  