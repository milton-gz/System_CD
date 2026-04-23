<?php
$admin = "Administrador General";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Gurú | Admin</title>

<script src="https://cdn.tailwindcss.com"></script>

<style>
:root{
--primary:#a774d6;
--secondary-soft:#dff1e6;
}

/* Layout */
html,body{height:100%;margin:0;}
body{background:var(--secondary-soft);display:flex;flex-direction:column;font-family:sans-serif;}

/* Header */
.header-ui{
background:var(--primary);
box-shadow:0 4px 14px rgba(0,0,0,.08);
}

/* Card */
.card{
background:#fff;
border-radius:14px;
box-shadow:0 6px 14px rgba(0,0,0,.05);
}

/* Tabs */
.tab{
padding:6px 14px;
border-radius:999px;
font-size:12px;
font-weight:600;
}
.tab.active{
background:#6FAE84;
color:#fff;
}

/* Tabla nueva */
.table{
width:100%;
border-collapse:collapse;
}

.table th{
font-size:11px;
color:#777;
text-align:left;
padding:10px;
}

.table td{
padding:12px 10px;
border-top:1px solid #eee;
font-size:13px;
}

.table tr:hover{
background:#f7faf9;
}

/* Badge */
.badge{
font-size:11px;
padding:3px 9px;
border-radius:999px;
font-weight:600;
}

/* Footer */
.footer-ui{
background:var(--primary);
margin-top:auto;
}

/* Tooltip */
.team-container{position:relative;}
.team-tooltip{
position:absolute;
bottom:120%;
left:50%;
transform:translateX(-50%) translateY(10px);
background:white;
padding:12px;
border-radius:10px;
box-shadow:0 10px 20px rgba(0,0,0,.15);
opacity:0;
transition:.25s;
min-width:280px;
}
.team-container:hover .team-tooltip{
opacity:1;
transform:translateX(-50%) translateY(0);
}
</style>
</head>

<body>

<!-- HEADER -->
<header class="header-ui px-6 py-3 flex justify-between items-center">
<img src="../assets/logo.png" class="w-28 rounded-lg">
<div class="text-sm font-semibold text-white"><?php echo $admin; ?></div>
</header>

<!-- MAIN -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4">

<h2 class="text-2xl font-bold mb-4">Panel Administrativo</h2>

<!-- KPIs REALES -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

<div class="card p-4">
<p class="text-xs text-gray-500">Usuarios registrados</p>
<h3 class="text-xl font-bold">124</h3>
</div>

<div class="card p-4">
<p class="text-xs text-gray-500">Citas hoy</p>
<h3 class="text-xl font-bold">18</h3>
</div>

<div class="card p-4">
<p class="text-xs text-gray-500">Productos críticos</p>
<h3 class="text-xl font-bold text-red-600">4</h3>
</div>

</div>

<!-- TABS -->
<div class="flex gap-2 mb-4">
<button class="tab active" data-tab="usuarios">Usuarios</button>
<button class="tab" data-tab="citas">Citas</button>
<button class="tab" data-tab="inventario">Inventario</button>
</div>

<!-- BUSCADOR -->
<input type="text" id="search" placeholder="Buscar registros..."
class="w-full md:w-80 px-3 py-2 text-sm rounded-xl border mb-4">

<!-- ================= USUARIOS ================= -->
<section id="usuarios" class="card p-4 tab-content">

<table class="table">
<thead>
<tr>
<th>Nombre</th>
<th>Correo</th>
<th>Rol</th>
<th>Estado</th>
<th>Último acceso</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>

<tr>
<td>Juan Pérez</td>
<td>juan@gmail.com</td>
<td>Paciente</td>
<td><span class="badge bg-green-100 text-green-700">Activo</span></td>
<td>Hoy 08:20 AM</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>Ana Martínez</td>
<td>ana@gmail.com</td>
<td>Doctor</td>
<td><span class="badge bg-green-100 text-green-700">Activo</span></td>
<td>Ayer</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>Carlos López</td>
<td>carlos@gmail.com</td>
<td>Recepción</td>
<td><span class="badge bg-gray-200 text-gray-700">Inactivo</span></td>
<td>Hace 3 días</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>María Gómez</td>
<td>maria@gmail.com</td>
<td>Paciente</td>
<td><span class="badge bg-green-100 text-green-700">Activo</span></td>
<td>Hoy 10:10 AM</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>Pedro Sánchez</td>
<td>pedro@gmail.com</td>
<td>Paciente</td>
<td><span class="badge bg-yellow-100 text-yellow-700">Pendiente</span></td>
<td>Hace 1 día</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>Laura Hernández</td>
<td>laura@gmail.com</td>
<td>Doctor</td>
<td><span class="badge bg-green-100 text-green-700">Activo</span></td>
<td>Hoy 07:45 AM</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

<tr>
<td>José Castillo</td>
<td>jose@gmail.com</td>
<td>Recepción</td>
<td><span class="badge bg-green-100 text-green-700">Activo</span></td>
<td>Hoy</td>
<td><button class="text-blue-500 text-xs">Editar</button><button class="text-red-500 text-xs ml-2">Eliminar</button></td>
</tr>

</tbody>
</table>

</section>

<!-- ================= CITAS ================= -->
<section id="citas" class="card p-4 tab-content hidden">

<table class="table">
<thead>
<tr>
<th>Paciente</th>
<th>Doctor</th>
<th>Fecha</th>
<th>Hora</th>
<th>Estado</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>

<tr>
<td>Juan Pérez</td>
<td>Dr. Ramírez</td>
<td>22/04/2026</td>
<td>08:00 AM</td>
<td><span class="badge bg-yellow-100 text-yellow-700">Pendiente</span></td>
<td><button class="text-blue-500 text-xs">Reprogramar</button></td>
</tr>

<tr>
<td>Ana Martínez</td>
<td>Dr. Ramírez</td>
<td>22/04/2026</td>
<td>09:30 AM</td>
<td><span class="badge bg-green-100 text-green-700">Confirmada</span></td>
<td><button class="text-red-500 text-xs">Cancelar</button></td>
</tr>

<tr>
<td>Luis Gómez</td>
<td>Dr. Pérez</td>
<td>22/04/2026</td>
<td>11:00 AM</td>
<td><span class="badge bg-red-100 text-red-700">Urgente</span></td>
<td><button class="text-red-600 text-xs">Atender</button></td>
</tr>

<tr>
<td>María Gómez</td>
<td>Dr. Hernández</td>
<td>22/04/2026</td>
<td>01:00 PM</td>
<td><span class="badge bg-green-100 text-green-700">Confirmada</span></td>
<td><button class="text-red-500 text-xs">Cancelar</button></td>
</tr>

<tr>
<td>Pedro Sánchez</td>
<td>Dr. Ramírez</td>
<td>22/04/2026</td>
<td>02:30 PM</td>
<td><span class="badge bg-yellow-100 text-yellow-700">Pendiente</span></td>
<td><button class="text-blue-500 text-xs">Reprogramar</button></td>
</tr>

<tr>
<td>Laura Hernández</td>
<td>Dr. López</td>
<td>22/04/2026</td>
<td>03:00 PM</td>
<td><span class="badge bg-green-100 text-green-700">Confirmada</span></td>
<td><button class="text-red-500 text-xs">Cancelar</button></td>
</tr>

<tr>
<td>José Castillo</td>
<td>Dr. Pérez</td>
<td>22/04/2026</td>
<td>04:00 PM</td>
<td><span class="badge bg-red-100 text-red-700">Urgente</span></td>
<td><button class="text-red-600 text-xs">Atender</button></td>
</tr>

</tbody>
</table>

</section>

<!-- ================= INVENTARIO ================= -->
<section id="inventario" class="card p-4 tab-content hidden">

<table class="table">
<thead>
<tr>
<th>Producto</th>
<th>Categoría</th>
<th>Stock</th>
<th>Última reposición</th>
<th>Estado</th>
</tr>
</thead>

<tbody>

<tr>
<td>Anestesia</td>
<td>Medicamentos</td>
<td>25</td>
<td>20/04/2026</td>
<td><span class="badge bg-green-100 text-green-700">Disponible</span></td>
</tr>

<tr>
<td>Guantes</td>
<td>Insumos</td>
<td>5</td>
<td>18/04/2026</td>
<td><span class="badge bg-yellow-100 text-yellow-700">Bajo</span></td>
</tr>

<tr>
<td>Mascarillas</td>
<td>Insumos</td>
<td>0</td>
<td>15/04/2026</td>
<td><span class="badge bg-red-100 text-red-700">Agotado</span></td>
</tr>

<tr>
<td>Jeringas</td>
<td>Insumos</td>
<td>40</td>
<td>21/04/2026</td>
<td><span class="badge bg-green-100 text-green-700">Disponible</span></td>
</tr>

<tr>
<td>Alcohol</td>
<td>Desinfección</td>
<td>12</td>
<td>19/04/2026</td>
<td><span class="badge bg-green-100 text-green-700">Disponible</span></td>
</tr>

<tr>
<td>Algodón</td>
<td>Insumos</td>
<td>3</td>
<td>17/04/2026</td>
<td><span class="badge bg-yellow-100 text-yellow-700">Bajo</span></td>
</tr>

<tr>
<td>Enjuague bucal</td>
<td>Productos</td>
<td>0</td>
<td>10/04/2026</td>
<td><span class="badge bg-red-100 text-red-700">Agotado</span></td>
</tr>

</tbody>
</table>

</section>

</main>

<!-- FOOTER -->
<footer class="footer-ui p-5 text-center text-sm text-white">

<div class="team-container cursor-pointer font-semibold">
Error 404: Members not found

<div class="team-tooltip">
<p>SHIRLEY ESTEFANÍA SALAZAR MORALES</p>
<p>KEVIN ALEJANDRO LEMUS TEJADA</p>
<p>MARCOS ANTONIO QUINTANILLA VALLE</p>
<p>MILTON ALEXIS GUTIÉRREZ RODRÍGUEZ</p>
<p>OTTO FERNANDO SÁNCHEZ CENTENO</p>
</div>

</div>

</footer>

<script>
// Tabs
document.querySelectorAll(".tab").forEach(btn=>{
btn.onclick=()=>{
document.querySelectorAll(".tab").forEach(b=>b.classList.remove("active"));
btn.classList.add("active");

document.querySelectorAll(".tab-content").forEach(c=>c.classList.add("hidden"));
document.getElementById(btn.dataset.tab).classList.remove("hidden");
};
});

// Buscador
document.getElementById("search").addEventListener("input",function(){
let val=this.value.toLowerCase();
document.querySelectorAll("tbody tr").forEach(row=>{
row.style.display=row.innerText.toLowerCase().includes(val)?"":"none";
});
});
</script>

</body>
</html>