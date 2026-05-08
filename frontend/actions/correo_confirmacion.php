<?php


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir las clases (ajusta las rutas)
require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';

function enviarConfirmacionCita($correoPaciente, $nombrePaciente, $fecha, $hora, $doctor, $especialidad, $tipoCita, $observaciones = '')
{
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP (cambia estos datos por los tuyos)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';          // Servidor SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tu_correo@gmail.com';     // Tu correo
        $mail->Password   = 'tu_contraseña_o_app_password'; // Usa contraseña de aplicación para Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Remitente y destinatario
        $mail->setFrom('tu_correo@gmail.com', 'Clínica Dental Guru');
        $mail->addAddress($correoPaciente, $nombrePaciente);

        // Contenido del correo (igual que antes)
        $fechaFormateada = date('d/m/Y', strtotime($fecha));
        $horaFormateada  = substr($hora, 0, 5);
        $asunto = '✅ Cita confirmada - Dental Guru';

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = "
        <html>
        <head><style>body{font-family:Arial;}</style></head>
        <body>
            <h2>Estimado/a $nombrePaciente</h2>
            <p>Su cita ha sido <strong style='color:green'>confirmada</strong> por el doctor <strong>$doctor</strong> ($especialidad).</p>
            <p><strong>Detalles:</strong><br>
            📅 Fecha: $fechaFormateada<br>
            ⏰ Hora: $horaFormateada<br>
            🦷 Tipo: $tipoCita<br>
            " . ($observaciones ? "📝 Observaciones: $observaciones<br>" : "") . "
            </p>
            <p>Por favor, llegue con 10 minutos de anticipación.</p>
            <p>Gracias por confiar en nosotros.</p>
        </body>
        </html>
        ";

        $mail->AltBody = "Estimado/a $nombrePaciente, su cita ha sido confirmada por el doctor $doctor ($especialidad) para el $fechaFormateada a las $horaFormateada. Tipo: $tipoCita.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Registrar error (opcional)
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        return false;
    }
}