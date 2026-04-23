<?php
// ===============================
// PREPARADO PARA BACKEND
// ===============================

// session_start();

// if(!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "recepcion"){
//     header("Location: ../index.php");
//     exit;
// }

$usuario = "Recepción";
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Gurú | Recepción</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>

<style>
:root{
--primary:#b18ddd;
--secondary-soft:#e4f3ea;
}

/* Layout */
html,body{
height:100%;
margin:0;
}

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
padding:4px 10px;
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
.team-container{ position:relative; display:inline-block; }

.team-tooltip{
position:absolute;
bottom:120%;
left:50%;
transform:translateX(-50%) translateY(10px);
background:white;
padding:12px;
border-radius:14px;
box-shadow:0 12px 25px rgba(0,0,0,.12);
font-size:12px;
opacity:0;
transition:.25s;
min-width:300px;
}

.team-tooltip::after{
content:"";
position:absolute;
top:100%;
left:50%;
transform:translateX(-50%);
border:6px solid transparent;
border-top-color:white;
}

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
<?php echo $usuario; ?>
</div>

</header>

<!-- CONTENIDO -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4 md:p-6">

<h2 class="text-2xl font-bold mb-4">
Panel de Recepción
</h2>

<!-- RESUMEN -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Citas hoy</p>
<h3 class="text-xl font-bold">12</h3>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Confirmadas</p>
<h3 class="text-xl font-bold text-green-600">8</h3>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Pendientes</p>
<h3 class="text-xl font-bold text-yellow-600">4</h3>
</div>

<div class="card-ui p-4">
<p class="text-xs text-gray-500">Canceladas</p>
<h3 class="text-xl font-bold text-red-600">1</h3>
</div>

</section>

<div class="grid lg:grid-cols-3 gap-5">

<!-- FORMULARIO -->
<section class="space-y-5">

<div class="card-ui p-5">

<h3 class="font-bold mb-4">Registrar nueva cita</h3>

<form method="POST">
<!-- 
Aquí luego insertas en BD:

$_POST["nombre"]
$_POST["fecha"]
$_POST["hora"]
$_POST["tipo"]
-->

<input type="text" name="nombre" placeholder="Nombre del paciente" class="input-ui mb-3">

<input type="date" name="fecha" class="input-ui mb-3">

<select name="hora" class="input-ui mb-3">
<option>8:00 AM</option>
<option>9:30 AM</option>
<option>11:00 AM</option>
</select>

<select name="tipo" class="input-ui mb-3">
<option>Limpieza</option>
<option>Revisión</option>
<option>Emergencia</option>
</select>

<button class="btn-main w-full">
Guardar cita
</button>

</form>

</div>

</section>

<!-- LISTADO -->
<section class="lg:col-span-2">

<div class="card-ui p-5">

<h3 class="font-bold mb-4">Citas de hoy</h3>

<?php
// Aquí luego haces:
// foreach($citas as $cita){}
?>

<div class="space-y-3">

<div class="flex justify-between items-center border rounded-xl p-3">

<div>
<p class="font-semibold text-sm">Juan Pérez</p>
<p class="text-xs text-gray-500">8:00 AM - Limpieza</p>
</div>

<span class="badge-fix bg-green-100 text-green-700">
Confirmada
</span>

</div>

<div class="flex justify-between items-center border rounded-xl p-3">

<div>
<p class="font-semibold text-sm">Ana Martínez</p>
<p class="text-xs text-gray-500">9:30 AM - Revisión</p>
</div>

<span class="badge-fix bg-yellow-100 text-yellow-700">
Pendiente
</span>

</div>

<div class="flex justify-between items-center border rounded-xl p-3">

<div>
<p class="font-semibold text-sm">Luis Gómez</p>
<p class="text-xs text-gray-500">11:00 AM - Emergencia</p>
</div>

<span class="badge-fix bg-red-100 text-red-700">
Cancelada
</span>

</div>

</div>

</div>

</section>

</div>

</main>

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