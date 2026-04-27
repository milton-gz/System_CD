<?php
// Configuración de la conexión a la base de datos en Clever Cloud
$host = "bdfaie2b9mu0vhhk0rxv-mysql.services.clever-cloud.com"; // Host remoto
$username = "uyiktzva7pwy8etd"; // Usuario
$password = "buESWSI3tphIHx6SKMEG"; // Contraseña
$database = "bdfaie2b9mu0vhhk0rxv"; // Nombre de la BD
$port = 3306; // Puerto (importante)

// Crear conexión
$conn = new mysqli($host, $username, $password, $database, $port);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Opcional: establecer charset (recomendado)
$conn->set_charset("utf8");
?>