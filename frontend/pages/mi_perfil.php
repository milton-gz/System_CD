<?php
session_start();
require_once "../config/conexion.php";

require_once "../../PHPMailer/src/Exception.php";
require_once "../../PHPMailer/src/PHPMailer.php";
require_once "../../PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['rol'] !== "paciente") {
    header("Location: ../login.php");
    exit();
}

$idUsuario = (int) $_SESSION['id'];

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// 🔹 OBTENER PERFIL
$stmt = $conn->prepare("
    SELECT 
        U.nombre,
        U.correo,
        U.fecha_registro,
        P.id_paciente,
        P.telefono,
        P.direccion,
        P.edad,
        P.sexo,
        P.tipo_sangre,
        P.alergias,
        P.enfermedades_previas
    FROM USUARIO U
    LEFT JOIN PACIENTE P ON P.USUARIO_id_usuario = U.id_usuario
    WHERE U.id_usuario = ?
");

$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();

// 🔴 VALIDACIÓN CRÍTICA
if (!$perfil) {
    die("Error: usuario no encontrado.");
}

// 🔴 SI NO EXISTE PACIENTE
if (!$perfil["id_paciente"]) {
    $conn->query("INSERT INTO PACIENTE (USUARIO_id_usuario) VALUES ($idUsuario)");
    header("Location: mi_perfil.php");
    exit();
}

$idPaciente = (int)$perfil["id_paciente"];

$mensaje = "";
$error = "";

// 🔥 ACTUALIZAR PERFIL
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $telefono = trim($_POST["telefono"] ?? '');
    $direccion = trim($_POST["direccion"] ?? '');
    $edad = (int)($_POST["edad"] ?? 0);
    $sexo = trim($_POST["sexo"] ?? '');
    $tipo_sangre = trim($_POST["tipo_sangre"] ?? '');
    $alergias = trim($_POST["alergias"] ?? '');
    $enfermedades = trim($_POST["enfermedades_previas"] ?? '');

    if ($edad < 0 || $edad > 120) {
        $error = "Edad inválida";
    } else {

        $stmt = $conn->prepare("
            UPDATE PACIENTE
            SET telefono=?, direccion=?, edad=?, sexo=?, tipo_sangre=?, alergias=?, enfermedades_previas=?
            WHERE id_paciente=?
        ");

        $stmt->bind_param(
            "ssissssi",
            $telefono,
            $direccion,
            $edad,
            $sexo,
            $tipo_sangre,
            $alergias,
            $enfermedades,
            $idPaciente
        );

        if ($stmt->execute()) {

            $mensaje = "Perfil actualizado correctamente.";

            //  CORREO
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'quintanillamarcos468@gmail.com';
                $mail->Password = 'atepdkhwmjalmyvm';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('quintanillamarcos468@gmail.com', 'Dental Guru');
                $mail->addAddress($perfil["correo"], $perfil["nombre"]);

                $mail->isHTML(true);
                $mail->Subject = "Perfil actualizado - Dental Guru";

                $mail->Body = "
                    <div style='font-family:Arial;padding:20px'>
                        <h2>Hola {$perfil['nombre']}</h2>
                        <p>Tu perfil fue actualizado correctamente.</p>

                        <hr>

                        <p><strong>📞 Teléfono:</strong> {$telefono}</p>
                        <p><strong>📍 Dirección:</strong> {$direccion}</p>
                        <p><strong>🎂 Edad:</strong> {$edad}</p>
                        <p><strong>⚧ Sexo:</strong> {$sexo}</p>
                        <p><strong>🩸 Tipo sangre:</strong> {$tipo_sangre}</p>

                        <br>
                        <small>Dental Guru © 2026</small>
                    </div>
                ";

                $mail->send();

            } catch (Exception $e) {}

            header("Location: mi_perfil.php?ok=1");
            exit();

        } else {
            $error = "No se pudo actualizar el perfil.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mi Perfil</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex justify-center items-center">

<div class="bg-white shadow-xl rounded-2xl p-8 w-full max-w-xl">

    <div class="flex items-center gap-4 mb-6">
        <div class="bg-blue-500 text-white w-16 h-16 flex items-center justify-center rounded-full text-2xl">
            👤
        </div>
        <div>
            <h2 class="text-2xl font-bold"><?= e($perfil['nombre']) ?></h2>
            <p class="text-gray-500"><?= e($perfil['correo']) ?></p>
        </div>
    </div>

    <?php if (isset($_GET["ok"])): ?>
        <div class="bg-green-100 text-green-700 p-2 rounded mb-4">
            Perfil actualizado y correo enviado ✔
        </div>
       <?php if (isset($_GET["ok"])): ?>
<script>
    setTimeout(() => {
        window.location.href = "../pages/paciente.php";
    }, 4000);
</script>
<?php endif; ?>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <input type="text" name="telefono" value="<?= e($perfil['telefono'] ?? '') ?>" class="w-full border p-2 rounded" placeholder="Teléfono">

        <input type="text" name="direccion" value="<?= e($perfil['direccion'] ?? '') ?>" class="w-full border p-2 rounded" placeholder="Dirección">

        <input type="number" name="edad" value="<?= e($perfil['edad'] ?? '') ?>" class="w-full border p-2 rounded" placeholder="Edad">

        <select name="sexo" class="w-full border p-2 rounded">
            <option value="">Sexo</option>
            <option value="Masculino" <?= ($perfil['sexo']=="Masculino")?"selected":"" ?>>Masculino</option>
            <option value="Femenino" <?= ($perfil['sexo']=="Femenino")?"selected":"" ?>>Femenino</option>
        </select>

        <input type="text" name="tipo_sangre" value="<?= e($perfil['tipo_sangre'] ?? '') ?>" class="w-full border p-2 rounded" placeholder="Tipo de sangre">

        <textarea name="alergias" class="w-full border p-2 rounded" placeholder="Alergias"><?= e($perfil['alergias'] ?? '') ?></textarea>

        <textarea name="enfermedades_previas" class="w-full border p-2 rounded" placeholder="Enfermedades previas"><?= e($perfil['enfermedades_previas'] ?? '') ?></textarea>

        <button class="w-full bg-blue-600 text-white py-2 rounded-lg">
            Guardar cambios
        </button>

    </form>

</div>

</body>
</html>