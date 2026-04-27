<?php
session_start();
ob_start();
require_once "../frontend/config/conexion.php"; // la conexion $conn

$mensaje = "";

// =========================
// VALIDAR REQUEST
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    // =====================================================
    //  REGISTRO (ROL = 4 POR DEFECTO MASH AUTO LOGIN)
    // =====================================================
    if ($action === "register") {

        $nombre = trim($_POST["nombre"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $clave  = trim($_POST["clave"] ?? "");
$confirmar = trim($_POST["confirmar_clave"] ?? "");
        $rol_id = 4; //  paciente por defecto

        // VALIDACIONES
     if ($nombre === "" || $correo === "" || $clave === "" || $confirmar === "") {
    $mensaje = "Todos los campos son obligatorios.";
} 
elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $mensaje = "Correo inválido.";
} 
elseif (strlen($clave) < 6) {
    $mensaje = "La contraseña debe tener mínimo 6 caracteres.";
}
elseif ($clave !== $confirmar) {
    $mensaje = "Las contraseñas no coinciden.";
}
        else {

            // Verificar si correo ya existe
            $stmt = $conn->prepare("SELECT id_usuario FROM USUARIO WHERE correo = ?");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {

                $mensaje = "Este correo ya está registrado.";

            } else {

                // Encriptar contraseña
                $hash = password_hash($clave, PASSWORD_DEFAULT);

                // Insertar usuario
             $stmt = $conn->prepare("
                    INSERT INTO USUARIO (ROL_id_rol, nombre, correo, password, estado)
                    VALUES (?, ?, ?, ?, 'activo')");
                $stmt->bind_param("isss", $rol_id, $nombre, $correo, $hash);

                if ($stmt->execute()) {

                    $id_usuario = $stmt->insert_id;

                    // Crear paciente automáticamente
                    $stmt2 = $conn->prepare("
                        INSERT INTO PACIENTE (USUARIO_id_usuario)
                        VALUES (?)
                    ");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();

                    //  AUTO LOGIN
                    $_SESSION["id"] = $id_usuario;
                    $_SESSION["nombre"] = $nombre;
                    $_SESSION["rol"] = "paciente";

                    // Guardar último acceso
                    $update = $conn->prepare("
                        UPDATE USUARIO 
                        SET ultimo_acceso = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $update->bind_param("i", $id_usuario);
                    $update->execute();

                    //  REDIRECCIÓN DIRECTA
                    echo "<script>window.location='index.php'</script>";
                    exit;

                } else {
                    $mensaje = "Error al registrar.";
                }
            }
        }
    }

    // =====================================================
    // LOGIN
    // =====================================================
    if ($action === "login") {

        $correo = trim($_POST["correo"] ?? "");
        $clave  = trim($_POST["clave"] ?? "");

        if ($correo === "" || $clave === "") {
            $mensaje = "Complete todos los campos.";
        } 
        else {

            $stmt = $conn->prepare("
                SELECT U.*, R.nombre AS rol
                FROM USUARIO U
                JOIN ROL R ON U.ROL_id_rol = R.id_rol
                WHERE U.correo = ? AND U.estado = 'activo'
            ");

            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {

                $user = $res->fetch_assoc();

                if (password_verify($clave, $user["password"])) {

                    // Crear sesión
                    $_SESSION["id"] = $user["id_usuario"];
                    $_SESSION["nombre"] = $user["nombre"];
                    $_SESSION["rol"] = $user["rol"];

                    // Actualizar último acceso
                    $update = $conn->prepare("
                        UPDATE USUARIO 
                        SET ultimo_acceso = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $update->bind_param("i", $user["id_usuario"]);
                    $update->execute();

                    // Redirección según rol
                   echo "<script>window.location='index.php'</script>";
exit;

                } else {
                    $mensaje = "Contraseña incorrecta.";
                }

            } else {
                $mensaje = "Usuario no encontrado o inactivo.";
            }
        }
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Gurú | Iniciar Sesión</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="css/styles.css">
<script src="js/app.js" defer></script>

<style>
.login-bg{
background:
radial-gradient(circle at top right, rgba(255,255,255,.14), transparent 22%),
radial-gradient(circle at bottom left, rgba(255,255,255,.10), transparent 18%),
#bea0e3;
}

.glass-box{
background:rgba(255,255,255,.78);
backdrop-filter:blur(10px);
}

.role-option input:checked + div{
border-color:#6FAE84;
background:#EEF9F1;
box-shadow:0 10px 18px rgba(111,174,132,.14);
}

.role-option div{
transition:.22s ease;
}

.role-option div:hover{
transform:translateY(-2px);
}

.compact-card{
padding:.75rem;
}

@media (min-width:1024px){
body{overflow:hidden;}
main{height:min(94vh,820px);}
}

@media (max-width:1023px){
main{height:auto;}
}
</style>
</head>

<body class="min-h-screen login-bg flex items-center justify-center p-3">

<main class="w-full max-w-6xl bg-white rounded-[28px] shadow-2xl overflow-hidden grid lg:grid-cols-2 animate-fade-up">

<!-- ===================================================
ZONA INFORMATIVA
=================================================== -->
<section class="px-5 py-6 lg:px-8 lg:py-6 flex items-center justify-center">

<div class="w-full max-w-md text-center">

<img src="assets/logo.png"
alt="Dental Gurú"
class="w-56 sm:w-64 lg:w-72 mx-auto rounded-3xl shadow-xl animate-float mb-4">

<div class="inline-block bg-white px-4 py-1.5 rounded-full shadow text-[#4F8C63] font-semibold text-xs mb-3">
Sistema en línea
</div>

<h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold text-gray-900 leading-tight mb-3">
Bienvenido a Dental Gurú
</h1>

<p class="text-gray-700 text-sm leading-relaxed mb-4 max-w-md mx-auto">
Plataforma operativa para la gestión diaria de citas, pacientes, expedientes clínicos y procesos administrativos.
</p>

<div class="grid gap-2.5 text-left">

<div class="card-ui compact-card flex gap-3 items-start reveal">
<div class="text-lg">📅</div>
<div>
<h3 class="font-semibold text-sm text-gray-900">Agenda de Hoy</h3>
<p class="text-xs text-gray-600">12 citas registradas y 8 confirmadas.</p>
</div>
</div>

<div class="card-ui compact-card flex gap-3 items-start reveal">
<div class="text-lg">📁</div>
<div>
<h3 class="font-semibold text-sm text-gray-900">Pacientes Activos</h3>
<p class="text-xs text-gray-600">245 expedientes disponibles en sistema.</p>
</div>
</div>

<div class="card-ui compact-card flex gap-3 items-start reveal">
<div class="text-lg">🦷</div>
<div>
<h3 class="font-semibold text-sm text-gray-900">Operación Estable</h3>
<p class="text-xs text-gray-600">Servicios funcionando con normalidad.</p>
</div>
</div>

</div>

</div>
</section>


<!-- ===================================================
FORMULARIO LOGIN
=================================================== -->

<div id="loginBox">
<section class="px-5 py-6 lg:px-8 lg:py-6 bg-white flex items-center justify-center">

<div class="w-full max-w-md glass-box rounded-3xl p-5 shadow-xl">

<h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">
Iniciar Sesión
</h2>

<p class="text-gray-600 text-sm mb-4">
Ingrese sus credenciales para continuar
</p>

<?php if($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-[#EEF9F1] text-[#2f6a44] border border-[#b7dfc4]">
<?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-3.5">


<!--  IMPORTANTE -->
<input type="hidden" name="action" value="login">

<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Correo electrónico
</label>
<input 
type="email" 
name="correo" 
class="input-ui" 
placeholder="correo@ejemplo.com"
required
>
</div>

<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Contraseña
</label>
<input 
type="password" 
name="clave" 
class="input-ui" 
placeholder="••••••••"
required
>
</div>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">

<label class="flex items-center gap-2 text-gray-700">
<input type="checkbox" name="remember" class="accent-[#6FAE84]">
Mantener sesión iniciada
</label>

<a href="#" class="text-[#4F8C63] font-semibold hover:underline">
Recuperar acceso
</a>

</div>

<button type="submit" class="btn-main w-full py-3">
Entrar al sistema
</button>

<div class="mt-4 text-center text-sm text-gray-600">
¿No tienes cuenta?
<button type="button" onclick="mostrarRegistro()" class="text-[#4F8C63] font-semibold hover:underline">
Crear cuenta
</button>
</div>

</form>

<div class="mt-4 pt-3 border-t text-center text-xs text-gray-500">
© 2026 Dental Gurú · Plataforma Clínica
</div>

</div>

</section>

</div>

</div>


<!-- ===================================================
FORMULARIO REGISTRO
=================================================== -->
<div id="registerBox" class="hidden">
<section class="px-5 py-6 lg:px-8 lg:py-6 bg-white flex items-center justify-center">

<div class="w-full max-w-md glass-box rounded-3xl p-5 shadow-xl">

<h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-1">
Crear Cuenta
</h2>

<p class="text-gray-600 text-sm mb-4">
Complete los datos para registrarse
</p>

<?php if($mensaje): ?>
<div class="mb-4 p-3 rounded-2xl text-sm bg-[#EEF9F1] text-[#2f6a44] border border-[#b7dfc4]">
<?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-3.5">

<!-- 🔥 IMPORTANTE -->
<input type="hidden" name="action" value="register">

<!-- NOMBRE -->
<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Nombre completo
</label>
<input 
type="text" 
name="nombre" 
class="input-ui" 
placeholder="Juan Pérez"
required
>
</div>

<!-- CORREO -->
<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Correo electrónico
</label>
<input 
type="email" 
name="correo" 
class="input-ui" 
placeholder="correo@ejemplo.com"
required
>
</div>

<!-- CONTRASEÑA -->
<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Contraseña
</label>
<input 
type="password" 
name="clave" 
class="input-ui" 
placeholder="••••••••"
required
minlength="6"
>
</div>

<!-- CONFIRMAR CONTRASEÑA -->
<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Confirmar contraseña
</label>
<input 
type="password" 
name="confirmar_clave" 
class="input-ui" 
placeholder="••••••••"
required
minlength="6"
>
</div>

<!-- CHECK -->
<div class="flex items-center gap-2 text-xs text-gray-700">
<input type="checkbox" required class="accent-[#6FAE84]">
Acepto los términos y condiciones
</div>

<!-- BOTÓN -->
<button type="submit" class="btn-main w-full py-3">
Crear cuenta
</button>

</form>

<!-- CAMBIO A LOGIN -->
<div class="mt-4 text-center text-sm text-gray-600">
¿Ya tienes cuenta?
<a href="login.php" class="text-[#4F8C63] font-semibold hover:underline">
Iniciar sesión
</a>
</div>

<div class="mt-4 pt-3 border-t text-center text-xs text-gray-500">
© 2026 Dental Gurú · Plataforma Clínica
</div>


</div>
</section>
</div>
<div class="mt-4 pt-3 border-t text-center text-xs text-gray-500">
© 2026 Dental Gurú · Plataforma Clínica
</div>


</div>
</section>
</div>
<div class="mt-4 pt-3 border-t text-center text-xs text-gray-500">
© 2026 

</main>

</body>
</html>



<script>
    // esto debes pasarlo al archivo @milton
function mostrarRegistro() {
    document.getElementById("loginBox").classList.add("hidden");
    document.getElementById("registerBox").classList.remove("hidden");
}

function mostrarLogin() {
    document.getElementById("registerBox").classList.add("hidden");
    document.getElementById("loginBox").classList.remove("hidden");
}
</script>