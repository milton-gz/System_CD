<?php

define("BASE_URL", "/System_CD/");

$host = "sql300.infinityfree.com";
$username = "if0_40501117";
$password = "R8m&in7-d(jDi/6";
$database = "if0_40501117_clinica";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>