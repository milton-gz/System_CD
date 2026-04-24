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
// 2. VALIDAR ROL (SOLO DOCTOR)
// =========================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'doctor') {

    switch ($_SESSION['rol']) {

        case "admin":
            header("Location: admin.php");
            break;

        case "paciente":
            header("Location: paciente.php");
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

// ===============================
// PREPARADO PARA BACKEND
// ===============================

// session_start();
// if(!isset($_SESSION["usuario"]) || $_SESSION["rol"] != "doctor"){
//     header("Location: ../index.php");
//     exit;
// }

$doctor = "Dr Kevin Lemus";

/*
Aquí luego puedes traer datos reales:

include "../config.php";
$query = "SELECT * FROM citas WHERE fecha = CURDATE()";
$result = mysqli_query($conn, $query);

y recorrer con foreach / while
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Gurú | Doctor</title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<script src="../js/app.js" defer></script>

<style>
:root{
--primary:#b18ddd;
--secondary-soft:#e4f3ea;
}

/* Layout base */
html,body{height:100%;margin:0;}
body{background:var(--secondary-soft);display:flex;flex-direction:column;}

/* Header */
.header-ui{background:var(--primary);box-shadow:0 6px 18px rgba(0,0,0,.08);}

/* Cards */
.card-ui{
background:white;border-radius:20px;
box-shadow:0 8px 18px rgba(0,0,0,.06);
transition:.25s;
}
.card-ui:hover{transform:translateY(-2px);}

/* Badge */
.badge-fix{
display:inline-flex;align-items:center;justify-content:center;
padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;
}

/* Inputs */
.input-ui{background:#f9fafb;}

/* Footer */
.footer-ui{background:var(--primary);margin-top:auto;}

/* Tooltip equipo */
.team-container{position:relative;display:inline-block;}
.team-tooltip{
position:absolute;bottom:120%;left:50%;
transform:translateX(-50%) translateY(10px);
background:white;padding:12px;border-radius:14px;
box-shadow:0 12px 25px rgba(0,0,0,.12);
font-size:12px;opacity:0;transition:.25s;min-width:300px;
}
.team-tooltip::after{
content:"";position:absolute;top:100%;left:50%;
transform:translateX(-50%);
border:6px solid transparent;border-top-color:white;
}
.team-container:hover .team-tooltip{
opacity:1;transform:translateX(-50%) translateY(0);
}

/* Filtros activos */
.filter-btn.active{
background:#6FAE84;color:white;
}
</style>

</head>

<body>

<!-- HEADER -->
<header class="header-ui px-6 py-3 flex items-center justify-between">
<img src="../assets/logo.png" class="w-28 rounded-xl shadow">
<div class="text-sm font-semibold text-gray-900">
<?php echo $doctor; ?>
</div>
</header>

<!-- CONTENIDO -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4 md:p-6">

<h2 class="text-2xl font-bold mb-4">Panel del Doctor</h2>

<!-- KPIs -->
<section class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
<div class="card-ui p-4"><p class="text-xs text-gray-500">Citas hoy</p><h3 class="text-xl font-bold">10</h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Atendidas</p><h3 class="text-xl font-bold text-green-600">6</h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Pendientes</p><h3 class="text-xl font-bold text-yellow-600">4</h3></div>
<div class="card-ui p-4"><p class="text-xs text-gray-500">Emergencias</p><h3 class="text-xl font-bold text-red-600">1</h3></div>
</section>

<!-- FILTROS + BUSCADOR -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">

<div class="flex gap-2 flex-wrap">
<button class="filter-btn active px-3 py-1 rounded-full text-xs border" data-filter="all">Todos</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="pendiente">Pendientes</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="atendida">Atendidos</button>
<button class="filter-btn px-3 py-1 rounded-full text-xs border" data-filter="emergencia">Emergencias</button>
</div>

<input type="text" id="search" placeholder="Buscar paciente..."
class="input-ui px-3 py-2 rounded-xl text-sm w-full md:w-64">

</div>

<!-- LISTA -->
<section class="card-ui p-5">

<h3 class="font-bold mb-4">Pacientes del día</h3>

<div id="listaPacientes" class="space-y-3">

<!-- PACIENTE -->
<details class="paciente border rounded-2xl overflow-hidden" data-estado="pendiente" data-nombre="juan perez">

<summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center">

<div>
<h4 class="font-semibold text-sm">Juan Pérez</h4>
<p class="text-xs text-gray-500">8:00 AM - Limpieza</p>
</div>

<span class="badge-fix bg-yellow-100 text-yellow-700">Pendiente</span>

</summary>

<div class="p-4 bg-gray-50 text-sm space-y-3">

<p><strong>Edad:</strong> 30</p>
<p><strong>Historial:</strong> Caries previa</p>

<!-- FORM -->
<form method="POST">
<!-- Aquí luego envías diagnóstico y receta -->

<textarea name="diagnostico" class="input-ui w-full" placeholder="Diagnóstico..."></textarea>
<textarea name="receta" class="input-ui w-full" placeholder="Tratamiento / receta..."></textarea>

<div class="flex gap-2 mt-2">
<button class="btn-main">Guardar</button>
<button type="button" class="px-3 py-2 text-xs border rounded-xl">Marcar atendido</button>
</div>

</form>

</div>

</details>

<!-- PACIENTE -->
<details class="paciente border rounded-2xl overflow-hidden" data-estado="atendida" data-nombre="ana martinez">

<summary class="p-4 cursor-pointer hover:bg-gray-50 flex justify-between items-center">

<div>
<h4 class="font-semibold text-sm">Ana Martínez</h4>
<p class="text-xs text-gray-500">9:30 AM - Revisión</p>
</div>

<span class="badge-fix bg-green-100 text-green-700">Atendida</span>

</summary>

<div class="p-4 bg-gray-50 text-sm">
<p>Sin problemas detectados.</p>
</div>

</details>

</div>

</section>

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

<p class="mt-2 text-xs">Sistema clínico Dental Gurú</p>

</footer>

<!-- ===============================
JS FUNCIONAL (FILTRO + BUSCADOR)
================================ -->
<script>
// Filtro
const botones = document.querySelectorAll(".filter-btn");
const pacientes = document.querySelectorAll(".paciente");

botones.forEach(btn=>{
btn.addEventListener("click",()=>{
botones.forEach(b=>b.classList.remove("active"));
btn.classList.add("active");

let filtro = btn.dataset.filter;

pacientes.forEach(p=>{
if(filtro === "all" || p.dataset.estado === filtro){
p.style.display = "block";
}else{
p.style.display = "none";
}
});
});
});

// Buscador
document.getElementById("search").addEventListener("input", function(){
let valor = this.value.toLowerCase();

pacientes.forEach(p=>{
let nombre = p.dataset.nombre;
p.style.display = nombre.includes(valor) ? "block" : "none";
});
});
</script>

</body>
</html>