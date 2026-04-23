<?php


$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");
    $clave  = trim($_POST["clave"] ?? "");
    $rol    = trim($_POST["rol"] ?? "");

    if ($correo === "" || $clave === "" || $rol === "") {
        $mensaje = "Complete todos los campos.";
    } else {

        /*
        Aquí luego puedes hacer:
        include 'config.php';
        consultar base de datos
        validar contraseña
        crear sesión
        */

        if ($rol === "staff") {
            $mensaje = "Acceso correcto. Aquí puedes redirigir a admin, doctor o recepción.";
            // header("Location: pages/admin.php");
            // exit;
        }

        if ($rol === "paciente") {
            $mensaje = "Acceso correcto. Aquí puedes redirigir a paciente.";
            // header("Location: pages/paciente.php");
            // exit;
        }
    }
}
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

<div>
<label class="block text-sm font-semibold text-gray-800 mb-2">
Tipo de acceso
</label>

<div class="space-y-2">

<label class="role-option block cursor-pointer">
<input type="radio" name="rol" value="staff" class="hidden" checked>
<div class="border-2 border-gray-200 rounded-2xl p-2.5">
<div class="flex gap-3 items-center">
<div class="w-9 h-9 rounded-xl bg-[#EEF9F1] flex items-center justify-center text-sm">🏥</div>
<div>
<p class="font-semibold text-sm text-gray-900">Personal Clínico</p>
<p class="text-xs text-gray-500">Administrador, Doctor o Recepción</p>
</div>
</div>
</div>
</label>

<label class="role-option block cursor-pointer">
<input type="radio" name="rol" value="paciente" class="hidden">
<div class="border-2 border-gray-200 rounded-2xl p-2.5">
<div class="flex gap-3 items-center">
<div class="w-9 h-9 rounded-xl bg-[#EEF9F1] flex items-center justify-center text-sm">🙂</div>
<div>
<p class="font-semibold text-sm text-gray-900">Paciente</p>
<p class="text-xs text-gray-500">Consulta de citas e historial</p>
</div>
</div>
</div>
</label>

</div>
</div>

<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Correo electrónico
</label>
<input type="email" name="correo" class="input-ui" placeholder="correo@ejemplo.com">
</div>

<div>
<label class="block text-sm font-semibold text-gray-800 mb-1.5">
Contraseña
</label>
<input type="password" name="clave" class="input-ui" placeholder="••••••••">
</div>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">

<label class="flex items-center gap-2 text-gray-700">
<input type="checkbox" class="accent-[#6FAE84]">
Mantener sesión iniciada
</label>

<a href="#" class="text-[#4F8C63] font-semibold hover:underline">
Recuperar acceso
</a>

</div>

<button type="submit" class="btn-main w-full py-3">
Entrar al sistema
</button>

</form>

<div class="mt-4 pt-3 border-t text-center text-xs text-gray-500">
© 2026 Dental Gurú · Plataforma Clínica
</div>

</div>

</section>

</main>

</body>
</html>