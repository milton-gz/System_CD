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
$nombreUsuario = $_SESSION['nombre'] ?? 'Paciente';

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// OBTENER PERFIL
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

if (!$perfil) {
    die("Error: usuario no encontrado.");
}

if (!$perfil["id_paciente"]) {
    $conn->query("INSERT INTO PACIENTE (USUARIO_id_usuario) VALUES ($idUsuario)");
    header("Location: mi_perfil.php");
    exit();
}

$idPaciente = (int)$perfil["id_paciente"];
$mensaje = "";
$error = "";

// ACTUALIZAR PERFIL
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
        $stmt->bind_param("ssissssi", $telefono, $direccion, $edad, $sexo, $tipo_sangre, $alergias, $enfermedades, $idPaciente);

        if ($stmt->execute()) {
            $mensaje = "Perfil actualizado correctamente.";

            // ENVIAR CORREO
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
                    <div style='font-family:Arial, sans-serif;padding:20px;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;'>
                        <meta charset='UTF-8'>
                        <div style='text-align:center;margin-bottom:20px;'>
                            🦷 <span style='font-size:24px;font-weight:bold;color:#7c6fb0;'>Dental Guru</span>
                        </div>
                        <h2 style='color:#7c6fb0;'>👋 Hola {$perfil['nombre']}</h2>
                        <p>✅ Tu perfil se ha <strong>actualizado correctamente</strong>.</p>
                        <hr style='margin:20px 0;'>
                        <p>📞 <strong>Teléfono:</strong> {$telefono}</p>
                        <p>🏠 <strong>Dirección:</strong> {$direccion}</p>
                        <p>🎂 <strong>Edad:</strong> {$edad} años</p>
                        <p>⚧ <strong>Sexo:</strong> {$sexo}</p>
                        <p>🩸 <strong>Tipo de sangre:</strong> {$tipo_sangre}</p>
                        " . (!empty($alergias) ? "<p>⚠️ <strong>Alergias:</strong> {$alergias}</p>" : "") . "
                        " . (!empty($enfermedades) ? "<p>📋 <strong>Enfermedades previas:</strong> {$enfermedades}</p>" : "") . "
                        <br>
                        <small style='color:#999;'>✉️ Este es un correo automático, no respondas a esta dirección.<br>🦷 Dental Guru © 2026</small>
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Dental Guru | Mi Perfil</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
:root {
    --primary: #7c6fb0;
    --primary-light: #9b8fc9;
    --primary-dark: #5e5290;
    --secondary: #8cd4ae;
    --secondary-dark: #6ab88e;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #f5f7fb;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* HEADER */
.header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

/* MENU LATERAL */
.mobile-menu {
    position: fixed;
    top: 0;
    right: -300px;
    width: 280px;
    height: 100%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 200;
    padding: 24px 20px;
    box-shadow: -5px 0 30px rgba(0, 0, 0, 0.2);
}
.mobile-menu.active { right: 0; }

.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 150;
}
.overlay.active {
    opacity: 1;
    visibility: visible;
}

/* CARDS */
.card-perfil {
    background: white;
    border-radius: 24px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
}

/* INPUTS */
.input-modern {
    width: 100%;
    padding: 12px 16px;
    border-radius: 14px;
    border: 1.5px solid #e2e8f0;
    font-size: 14px;
    transition: all 0.2s;
    background: #fafcff;
}
.input-modern:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(124, 111, 176, 0.1);
}

select.input-modern {
    cursor: pointer;
    background: #fafcff;
}

textarea.input-modern {
    resize: vertical;
    min-height: 80px;
}

/* BOTONES */
.btn-primary {
    background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
    color: #1a2e2a;
    padding: 12px 24px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    width: 100%;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(140, 212, 174, 0.35);
}

.btn-outline {
    background: transparent;
    border: 1.5px solid var(--primary-light);
    color: var(--primary-dark);
    padding: 8px 20px;
    border-radius: 40px;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-outline:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: translateY(-1px);
}

/* FOOTER */
.footer {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    text-align: center;
    padding: 25px 20px;
    margin-top: auto;
}

/* TOOLTIP */
.team-container {
    position: relative;
    display: inline-block;
}
.team-tooltip {
    position: absolute;
    bottom: 130%;
    left: 50%;
    transform: translateX(-50%) scale(0.95);
    background: white;
    color: #1e293b;
    padding: 14px 18px;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transition: all 0.25s;
    min-width: 260px;
    pointer-events: none;
}
.team-container:hover .team-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) scale(1);
}
.team-tooltip p {
    font-size: 12px;
    margin: 6px 0;
}

/* ANIMACIONES */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* SCROLLBAR */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 10px;
}
</style>
</head>

<body>

<!-- HEADER CON HAMBURGUESA -->
<header class="header flex justify-between items-center px-5 md:px-8 py-3">
    <div class="flex items-center gap-3">
        <img src="../assets/logo.png" class="w-28 rounded-xl shadow-sm" alt="Dental Guru" onerror="this.style.display='none'">
        <div class="hidden md:block">
            <p class="font-semibold text-white">Dental Guru</p>
            <p class="text-[11px] text-white/70">Mi perfil</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-white text-sm hidden md:inline"><?php echo e($nombreUsuario); ?></span>
        <button onclick="openMenu()" class="text-white text-2xl focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<!-- OVERLAY Y MENU LATERAL -->
<div id="overlay" class="overlay" onclick="closeMenu()"></div>
<div id="menu" class="mobile-menu">
    <div class="flex justify-between items-center mb-6 pb-3 border-b border-white/20">
        <div class="flex items-center gap-2">
            <img src="../assets/logo.png" class="w-8 rounded-lg" alt="Logo">
            <p class="font-bold text-white">Dental Guru</p>
        </div>
        <button onclick="closeMenu()" class="text-white/80 text-xl hover:text-white">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="mb-6 p-3 bg-white/10 rounded-xl">
        <p class="text-sm font-medium text-white/90"><?php echo e($nombreUsuario); ?></p>
        <p class="text-xs text-white/70 mt-1"><i class="fas fa-user mr-1"></i>Paciente</p>
    </div>
    <nav class="space-y-2">
        <a href="paciente.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-home w-5"></i> Inicio</a>
        <a href="paciente.php?seccion=citas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-calendar-alt w-5"></i> Mis citas</a>
        <a href="paciente.php?seccion=recetas" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-prescription-bottle w-5"></i> Mis recetas</a>
        <a href="paciente.php?seccion=expediente" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-folder-open w-5"></i> Mi expediente</a>
        <a href="paciente.php?seccion=agendar" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition"><i class="fas fa-plus-circle w-5"></i> Agendar cita</a>
    </nav>
    <hr class="my-4 border-white/20">
    <a href="mi_perfil.php" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-white/20 transition"><i class="fas fa-user-edit w-5"></i> Mi perfil</a>
    <a href="../config/cerrar_sesion.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition"><i class="fas fa-sign-out-alt w-5"></i> Cerrar sesion</a>
</div>

<!-- MAIN CONTENT -->
<main class="flex-1 w-full px-4 md:px-8 py-8">
    <div class="max-w-2xl mx-auto fade-in">

        <!-- BOTON VOLVER -->
        <div class="mb-4">
            <a href="paciente.php" class="btn-outline text-sm">
                <i class="fas fa-arrow-left"></i> Volver al panel
            </a>
        </div>

        <!-- CARD PERFIL -->
        <div class="card-perfil p-6 md:p-8">
            <!-- CABECERA PERFIL -->
            <div class="flex items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[#7c6fb0] to-[#5e5290] flex items-center justify-center text-white text-2xl shadow-md">
                    <i class="fas fa-user-md"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800"><?php echo e($perfil['nombre']); ?></h2>
                    <p class="text-gray-500 text-sm"><i class="fas fa-envelope mr-1"></i> <?php echo e($perfil['correo']); ?></p>
                    <p class="text-gray-400 text-xs mt-1"><i class="fas fa-calendar-alt mr-1"></i> Miembro desde <?php echo date("d/m/Y", strtotime($perfil['fecha_registro'] ?? 'now')); ?></p>
                </div>
            </div>

            <!-- ALERTAS -->
            <?php if (isset($_GET["ok"])): ?>
                <div class="mb-5 p-3 rounded-xl bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Perfil actualizado correctamente. Redirigiendo al panel...
                </div>
                <script>
                    setTimeout(() => {
                        window.location.href = "paciente.php";
                    }, 3000);
                </script>
            <?php endif; ?>

            <?php if ($mensaje && !isset($_GET["ok"])): ?>
                <div class="mb-5 p-3 rounded-xl bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> <?php echo e($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-5 p-3 rounded-xl bg-rose-50 border-l-4 border-rose-500 text-rose-700 text-sm flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <!-- FORMULARIO -->
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                    <input type="text" name="telefono" value="<?php echo e($perfil['telefono'] ?? ''); ?>" class="input-modern" placeholder="Ej: 1234-5678">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Direccion</label>
                    <input type="text" name="direccion" value="<?php echo e($perfil['direccion'] ?? ''); ?>" class="input-modern" placeholder="Tu direccion completa">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Edad</label>
                        <input type="number" name="edad" value="<?php echo e($perfil['edad'] ?? ''); ?>" class="input-modern" placeholder="Años">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                        <select name="sexo" class="input-modern">
                            <option value="">Seleccionar</option>
                            <option value="Masculino" <?php echo ($perfil['sexo']=="Masculino")?"selected":""; ?>>Masculino</option>
                            <option value="Femenino" <?php echo ($perfil['sexo']=="Femenino")?"selected":""; ?>>Femenino</option>
                            <option value="Otro" <?php echo ($perfil['sexo']=="Otro")?"selected":""; ?>>Otro</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de sangre</label>
                    <select name="tipo_sangre" class="input-modern">
                        <option value="">Seleccionar</option>
                        <option value="A+" <?php echo ($perfil['tipo_sangre']=="A+")?"selected":""; ?>>A+</option>
                        <option value="A-" <?php echo ($perfil['tipo_sangre']=="A-")?"selected":""; ?>>A-</option>
                        <option value="B+" <?php echo ($perfil['tipo_sangre']=="B+")?"selected":""; ?>>B+</option>
                        <option value="B-" <?php echo ($perfil['tipo_sangre']=="B-")?"selected":""; ?>>B-</option>
                        <option value="O+" <?php echo ($perfil['tipo_sangre']=="O+")?"selected":""; ?>>O+</option>
                        <option value="O-" <?php echo ($perfil['tipo_sangre']=="O-")?"selected":""; ?>>O-</option>
                        <option value="AB+" <?php echo ($perfil['tipo_sangre']=="AB+")?"selected":""; ?>>AB+</option>
                        <option value="AB-" <?php echo ($perfil['tipo_sangre']=="AB-")?"selected":""; ?>>AB-</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alergias</label>
                    <textarea name="alergias" class="input-modern" placeholder="Ej: Penicilina, polen, mariscos..."><?php echo e($perfil['alergias'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enfermedades previas</label>
                    <textarea name="enfermedades_previas" class="input-modern" placeholder="Ej: Hipertension, diabetes, asma..."><?php echo e($perfil['enfermedades_previas'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-primary mt-4">
                    <i class="fas fa-save"></i> Guardar cambios
                </button>
            </form>

            <!-- INFORMACION ADICIONAL -->
            <div class="mt-6 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 text-center">
                    <i class="fas fa-shield-alt mr-1"></i> Tus datos estan seguros y seran utilizados unicamente para tu atencion medica.
                </p>
            </div>
        </div>
    </div>
</main>

<!-- FOOTER -->
<footer class="footer">
    <div class="team-container">
        <span class="font-semibold cursor-pointer hover:opacity-90 transition"><i class="fas fa-code-branch mr-1"></i> Error 404: Members not found</span>
        <div class="team-tooltip">
            <p>SHIRLEY ESTEFANIA SALAZAR MORALES</p>
            <p>KEVIN ALEJANDRO LEMUS TEJADA</p>
            <p>MARCOS ANTONIO QUINTANILLA VALLE</p>
            <p>MILTON ALEXIS GUTIERREZ RODRIGUEZ</p>
            <p>OTTO FERNANDO SANCHEZ CENTENO</p>
        </div>
    </div>
    <p class="text-xs mt-3 opacity-70">Sistema clinico Dental Guru © 2026</p>
</footer>

<script>
function openMenu() {
    document.getElementById("menu").classList.add("active");
    document.getElementById("overlay").classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeMenu() {
    document.getElementById("menu").classList.remove("active");
    document.getElementById("overlay").classList.remove("active");
    document.body.style.overflow = "";
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenu();
});
</script>

</body>
</html>
