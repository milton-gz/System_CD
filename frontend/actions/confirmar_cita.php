<?php
session_start();

require_once "../config/conexion.php";

// PHPMailer
require_once "../../PHPMailer/src/Exception.php";
require_once "../../PHPMailer/src/PHPMailer.php";
require_once "../../PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 🔒 Validar sesión
if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

// 🔒 Validar método
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../pages/doctor.php");
    exit();
}

// 🔒 Validar datos
if (
    !isset($_POST["accion"], $_POST["id_cita"]) ||
    !in_array($_POST["accion"], ["tomar", "confirmar"])
) {
    header("Location: ../pages/doctor.php");
    exit();
}

$idCita = (int) $_POST["id_cita"];

// 🔹 Iniciar transacción
$conn->begin_transaction();

try {

    // 1️⃣ Confirmar cita
    $stmt = $conn->prepare("
        UPDATE CITA 
        SET estado = 'confirmada' 
        WHERE id_cita = ?
    ");
    $stmt->bind_param("i", $idCita);

    if (!$stmt->execute()) {
        throw new Exception("Error al confirmar cita.");
    }

    // 2️⃣ Obtener datos del paciente
    $stmtDatos = $conn->prepare("
        SELECT U.nombre, U.correo, C.fecha, C.hora, C.tipo
        FROM CITA C
        JOIN PACIENTE P ON P.id_paciente = C.PACIENTE_id_paciente
        JOIN USUARIO U ON U.id_usuario = P.USUARIO_id_usuario
        WHERE C.id_cita = ?
    ");
    $stmtDatos->bind_param("i", $idCita);
    $stmtDatos->execute();
    $datos = $stmtDatos->get_result()->fetch_assoc();

    if (!$datos) {
        throw new Exception("No se encontraron datos.");
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../pages/doctor.php?error=bd");
    exit();
}

//
// ✉️ ENVIAR CORREO
//

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'quintanillamarcos468@gmail.com';

    // 🔥 SIN ESPACIOS
    $mail->Password   = 'atepdkhwmjalmyvm';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';

    $mail->setFrom('quintanillamarcos468@gmail.com', 'Dental Guru');
    $mail->addAddress($datos["correo"], $datos["nombre"]);

    $mail->isHTML(true);
    $mail->Subject = "🦷 Confirmación de Cita - Dental Guru";

    $mail->Body = "
        <div style='font-family: Arial; padding:20px'>
            <h2>Hola {$datos['nombre']},</h2>
            <p>Tu cita ha sido <strong>confirmada</strong> correctamente.</p>
            <hr>
            <p><strong>📅 Fecha:</strong> {$datos['fecha']}</p>
            <p><strong>⏰ Hora:</strong> {$datos['hora']}</p>
            <p><strong>🦷 Tipo:</strong> {$datos['tipo']}</p>
            <br>
            <p>Por favor llegar 10 minutos antes.</p>
            <br>
            <small>Dental Guru © 2026</small>
        </div>
    ";

    $mail->send();

} catch (Exception $e) {
    // Si falla el correo no rompemos el sistema
    // Puedes activar debug temporalmente:
    // echo $mail->ErrorInfo;
}

// 🔄 Redirigir
header("Location: ../pages/doctor.php?confirmada=1");
exit();
?>