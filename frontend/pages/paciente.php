<?php
session_start();
require_once "../config/conexion.php";

// =========================
// 1. VALIDAR SESIÓN
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../../login.php");
    exit();
}

// =========================
// 2. VALIDAR ROL (SOLO PACIENTE)
// =========================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'paciente') {

    switch ($_SESSION['rol']) {

        case "admin":
            header("Location: admin.php");
            break;

        case "doctor":
            header("Location: doctor.php");
            break;

        case "recepcion":
            header("Location: recepcion.php");
            break;

        default:
            session_destroy();
            header("Location: ../../login.php");
            break;
    }

    exit();
}

// =========================
// 3. SEGURIDAD EXTRA
// =========================
if (!isset($_SESSION['nombre'])) {
    session_destroy();
    header("Location: ../../login.php");
    exit();
}

$nombre = "María López";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Gurú | Paciente</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>

<style>
:root{
--primary:#b18ddd;
--secondary-soft:#e4f3ea; 
}

/* Layout base sin espacios */
html,body{
height:100%;
margin:0;
}

/* Fondo general */
body{
background:var(--secondary-soft);
display:flex;
flex-direction:column;
}

/* Header */
.header-ui{
background:var(--primary);
box-shadow:0 6px 18px rgba(0,0,0,.08);
}

/* Contenido flexible */
.main-wrapper{
flex:1;
}

/* Cards */
.card-ui{
background:white;
border-radius:20px;
box-shadow:0 8px 18px rgba(0,0,0,.06);
transition:.25s;
}

.card-ui:hover{
transform:translateY(-2px);
}

/* Badge */
.badge-fix{
display:inline-flex;
align-items:center;
justify-content:center;
padding:4px 12px;
border-radius:999px;
font-size:11px;
font-weight:700;
}

/* Footer */
.footer-ui{
background:var(--primary);
margin-top:auto;
}

/* Tooltip equipo */
.team-container{
position:relative;
display:inline-block;
}

.team-tooltip{
position:absolute;
bottom:120%;
left:50%;
transform:translateX(-50%) translateY(10px);

background:white;
color:#333;
padding:12px 14px;
border-radius:14px;
box-shadow:0 12px 25px rgba(0,0,0,.12);

font-size:12px;
line-height:1.4;

opacity:0;
pointer-events:none;

transition:all .25s ease;
min-width:280px;
text-align:left;
}

/* Flecha */
.team-tooltip::after{
content:"";
position:absolute;
top:100%;
left:50%;
transform:translateX(-50%);
border-width:6px;
border-style:solid;
border-color:white transparent transparent transparent;
}

/* Hover */
.team-container:hover .team-tooltip{
opacity:1;
transform:translateX(-50%) translateY(0);
}
</style>

</head>

<body>

<!-- HEADER -->
<header class="header-ui px-6 py-3 flex items-center justify-between">

<img src="../assets/logo.png" class="w-28 rounded-xl shadow">

<div class="text-sm font-semibold text-gray-900">
<?php echo $nombre; ?>
</div>

</header>

<!-- CONTENIDO -->
<div class="main-wrapper">

<main class="max-w-7xl mx-auto w-full p-4 md:p-6">

<h2 class="text-2xl font-bold mb-4 text-gray-900">
Panel del Paciente
</h2>

<!-- RESUMEN -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Próxima cita</p>
<h3 class="font-bold">19 Abril</h3>
<p class="text-xs text-gray-500">8:00 AM</p>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Realizadas</p>
<h3 class="text-xl font-bold text-green-600">6</h3>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Pendientes</p>
<h3 class="text-xl font-bold text-yellow-600">1</h3>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Recetas</p>
<h3 class="text-xl font-bold">2</h3>
</div>

</section>

<div class="grid lg:grid-cols-3 gap-5">

<!-- IZQUIERDA -->
<section class="space-y-5">

<div class="card-ui p-5">

<h3 class="font-bold mb-4">Agendar cita</h3>

<form method="POST">
<!-- Aquí luego guardas en BD -->
<input type="date" name="fecha" class="input-ui mb-3">

<select name="hora" class="input-ui mb-3">
<option>8:00 AM</option>
<option>9:30 AM</option>
<option>11:00 AM</option>
</select>

<select name="tipo" class="input-ui mb-3">
<option>Limpieza dental</option>
<option>Revisión general</option>
</select>

<button class="btn-main w-full">
Solicitar cita
</button>
</form>

</div>

<div class="card-ui p-5">

<h3 class="font-bold mb-4">Mi expediente</h3>

<p class="text-sm">Nombre: María López</p>
<p class="text-sm">Edad: 28</p>
<p class="text-sm">Tipo sangre: O+</p>
<p class="text-sm">Diagnóstico: Sarro leve</p>

</div>

</section>

<!-- DERECHA -->
<section class="lg:col-span-2">

<div class="card-ui p-5">

<h3 class="font-bold mb-4">Historial de citas</h3>

<div class="space-y-3">

<details class="border rounded-2xl overflow-hidden">
<summary class="p-3 cursor-pointer hover:bg-gray-50 flex justify-between items-center">

<div>
<h4 class="font-semibold text-sm">10 Abril 2026</h4>
<p class="text-xs text-gray-500">Limpieza dental</p>
</div>

<span class="badge-fix bg-green-100 text-green-700">
Realizada
</span>

</summary>

<div class="p-3 bg-gray-50 text-sm">
Tratamiento completado correctamente.
</div>
</details>

<details class="border rounded-2xl overflow-hidden">
<summary class="p-3 cursor-pointer hover:bg-gray-50 flex justify-between items-center">

<div>
<h4 class="font-semibold text-sm">19 Abril 2026</h4>
<p class="text-xs text-gray-500">Revisión general</p>
</div>

<span class="badge-fix bg-yellow-100 text-yellow-700">
Pendiente
</span>

</summary>

<div class="p-3 bg-gray-50 text-sm">
Cita programada a las 8:00 AM.
</div>
</details>

</div>

</div>

</section>

</div>

</main>

</div>

<!-- FOOTER -->
<footer class="footer-ui p-5 text-center text-sm text-gray-800">

<div class="team-container cursor-pointer font-semibold">

<span>Error 404: Members not found</span>

<div class="team-tooltip">

<p>SHIRLEY ESTEFANÍA SALAZAR MORALES</p>
<p>KEVIN ALEJANDRO LEMUS TEJADA</p>
<p>MARCOS ANTONIO QUINTANILLA VALLE</p>
<p>MILTON ALEXIS GUTIÉRREZ RODRÍGUEZ</p>
<p>OTTO FERNANDO SÁNCHEZ CENTENO</p>

</div>

</div>

<p class="mt-2 text-xs">
Sistema clínico Dental Gurú
</p>

</footer>

</body>
</html>