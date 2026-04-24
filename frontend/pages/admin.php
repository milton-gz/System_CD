<?php
if (!isset($_SESSION)) session_start();
require_once "../config/conexion.php";


// =========================
// 1. VALIDAR SESIÓN
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../../login.php");
    exit();
}

// =========================
// 2. VALIDAR ROL (SOLO ADMIN)
// =========================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {

    // 🔥 Redirigir según rol
    switch ($_SESSION['rol']) {

        case "doctor":
            header("Location: doctor.php");
            break;

        case "paciente":
            header("Location: paciente.php");
            break;

        case "recepcion":
            header("Location: recepcion.php");
            break;

        default:
            // Si no tiene rol válido → fuera
            session_destroy();
            header("Location: ../../login.php");
            break;
    }

    exit();
}

// =========================
// 3. SEGURIDAD EXTRA (opcional)
// =========================
if (!isset($_SESSION['nombre'])) {
    session_destroy();
    header("Location: ../../login.php");
    exit();
}


// Usuarios
$totalUsuarios = $conn->query("SELECT COUNT(*) as total FROM USUARIO")->fetch_assoc()['total'];

// Citas hoy
$totalCitasHoy = $conn->query("
SELECT COUNT(*) as total 
FROM CITA 
WHERE fecha = CURDATE()
")->fetch_assoc()['total'];

// Productos críticos (bajo o agotado)
$totalProductosCriticos = $conn->query("
SELECT COUNT(*) as total 
FROM PRODUCTO 
WHERE estado IN ('bajo','agotado')
")->fetch_assoc()['total'];
// =========================
// OBTENER USUARIOS
// =========================
$usuarios = [];

$sql = "SELECT U.nombre, U.correo, R.nombre AS rol, U.estado, U.ultimo_acceso
        FROM USUARIO U
        JOIN ROL R ON U.ROL_id_rol = R.id_rol";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// =========================
// OBTENER CITAS
// =========================
$citas = [];

$usuarios = $conn->query("
SELECT 
    U.id_usuario,
    U.nombre,
    U.correo,
    R.nombre AS rol,
    U.estado,
    U.ultimo_acceso
FROM USUARIO U
JOIN ROL R ON U.ROL_id_rol = R.id_rol
");

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $citas[] = $row;
    }
}

// =========================
// INVENTARIO
// =========================
$inventario = [];

$sql = "SELECT nombre, categoria, stock, fecha_reposicion FROM PRODUCTO";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventario[] = $row;
    }
}

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
<div class="text-sm font-semibold text-white"><?php echo $_SESSION['nombre']; ?></div>
</header>

<!-- MAIN -->
<main class="flex-1 max-w-7xl mx-auto w-full p-4">

<h2 class="text-2xl font-bold mb-4">Panel Administrativo</h2>
<a href="../config/cerrar_sesion.php" class="btn-logout">Cerrar sesión</a>
<!-- KPIs REALES -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

<div class="card p-4">
<p class="text-xs text-gray-500">Usuarios registrados</p>
<h3 class="text-xl font-bold"><?= $totalUsuarios ?></h3>
</div>

<div class="card p-4">
<p class="text-xs text-gray-500">Citas hoy</p>
<h3 class="text-xl font-bold"><?= $totalCitasHoy ?></h3>
</div>

<div class="card p-4">
<p class="text-xs text-gray-500">Productos críticos</p>
<h3 class="text-xl font-bold text-red-600"><?= $totalProductosCriticos ?></h3>
</div>

<div class="card p-4">
<p class="text-xs text-gray-500">⚙️ Panel Ejecutivo CRUD</h3>

<p class="text-sm text-gray-600 mb-3">
Acceso rápido a gestión del sistema (usuarios, citas e inventario)
</p>
<a href="crud_admin.php"
class="bg-[#6FAE84] text-white px-4 py-2 rounded-lg text-sm inline-block">
Acceder al CRUD
</a>
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
<?php foreach ($usuarios as $u): ?>
<tr>
<td><?= htmlspecialchars($u["nombre"]) ?></td>
<td><?= htmlspecialchars($u["correo"]) ?></td>
<td><?= htmlspecialchars($u["rol"]) ?></td>

<td>
<span class="badge 
<?= $u["estado"] == "activo" ? "bg-green-100 text-green-700" : "bg-gray-200 text-gray-700" ?>">
<?= $u["estado"] ?>
</span>
</td>

<td><?= $u["ultimo_acceso"] ?? "—" ?></td>

<td>
<button class="text-blue-500 text-xs">Editar</button>
<button class="text-red-500 text-xs ml-2">Eliminar</button>
</td>
</tr>
<?php endforeach; ?>
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
<?php foreach ($inventario as $p): ?>
<tr>
    <td><?= htmlspecialchars($p["nombre"]) ?></td>
    <td><?= htmlspecialchars($p["categoria"]) ?></td>
    <td><?= $p["stock"] ?></td>
    <td><?= $p["fecha_reposicion"] ?></td>

    <td>
        <span class="badge
        <?=
            $p["estado"] == "disponible" ? "bg-green-100 text-green-700" :
            ($p["estado"] == "bajo" ? "bg-yellow-100 text-yellow-700" : "bg-red-100 text-red-700")
        ?>">
            <?= $p["estado"] ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</section>

</main>


<div id="authBox" class="hidden mt-4">

<?php if(isset($error_crud)): ?>
<p class="text-red-500 text-sm mb-2"><?= $error_crud ?></p>
<?php endif; ?>

<form method="POST" class="space-y-2">

<input type="hidden" name="auth_admin" value="1">

<input 
type="email" 
name="correo" 
placeholder="Correo admin"
class=" "
required
>

<input 
type="password" 
name="clave" 
placeholder="Contraseña"
class=""
required
>

<button class="">
Validar acceso
</button>

</form>
</div>

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