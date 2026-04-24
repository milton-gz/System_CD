<?php
session_start();
require_once "../config/conexion.php";


/* =========================
   TABLAS
========================= */
$tablas = [];
$res = $conn->query("SHOW TABLES");

while ($row = $res->fetch_array()) {
    $tablas[] = $row[0];
}

/* =========================
   TABLA SELECCIONADA
========================= */
$tabla = $_GET['tabla'] ?? $tablas[0];

/* =========================
   INSERT DINÁMICO
========================= */
if (isset($_POST['insert'])) {

    $cols = [];
    $vals = [];

    foreach ($_POST as $key => $value) {
        if ($key !== "insert" && $key !== "tabla") {
            $cols[] = $key;
            $vals[] = "'" . $conn->real_escape_string($value) . "'";
        }
    }

    $sql = "INSERT INTO `$tabla` (" . implode(",", $cols) . ")
            VALUES (" . implode(",", $vals) . ")";

    $conn->query($sql);
}

/* =========================
   DELETE
========================= */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    $conn->query("DELETE FROM `$tabla` WHERE 1 LIMIT 1"); 
}

/* =========================
   DATA
========================= */
$data = $conn->query("SELECT * FROM `$tabla` LIMIT 100");
$columns = $data ? $data->fetch_fields() : [];
?>

<!DOCTYPE html>
<html>
<head>
<title>CRUD DINÁMICO</title>

<style>
body{
font-family:Arial;
background:#f2f4f7;
margin:0;
}

.container{
padding:20px;
}

.card{
background:white;
padding:15px;
border-radius:12px;
box-shadow:0 5px 15px rgba(0,0,0,.1);
margin-bottom:15px;
}

select,input,button{
padding:10px;
border-radius:8px;
border:1px solid #ddd;
margin:5px;
}

button{
background:#6FAE84;
color:white;
border:none;
cursor:pointer;
}

table{
width:100%;
border-collapse:collapse;
background:white;
border-radius:10px;
overflow:hidden;
}

th,td{
padding:10px;
border-bottom:1px solid #eee;
text-align:left;
font-size:13px;
}

th{
background:#a774d6;
color:white;
}

.actions a{
color:red;
text-decoration:none;
font-weight:bold;
}
</style>
</head>

<body>

<div class="container">

<!-- ================= SELECT TABLA ================= -->
<div class="card">
<h2>📊 CRUD DINÁMICO ADMIN</h2>

<form method="GET">
<select name="tabla">
<?php foreach ($tablas as $t): ?>
<option value="<?= $t ?>" <?= $tabla == $t ? 'selected' : '' ?>>
<?= $t ?>
</option>
<?php endforeach; ?>
</select>
<button type="submit">Cargar</button>
</form>

</div>

<!-- ================= INSERT ================= -->
<div class="card">
<h3>➕ Insertar en: <?= $tabla ?></h3>

<form method="POST">

<input type="hidden" name="tabla" value="<?= $tabla ?>">

<?php foreach ($columns as $col): ?>
<?php if ($col->name != "id"): ?>
<input type="text" name="<?= $col->name ?>" placeholder="<?= $col->name ?>">
<?php endif; ?>
<?php endforeach; ?>

<button type="submit" name="insert">Guardar</button>

</form>
</div>

<!-- ================= TABLA ================= -->
<div class="card">

<h3>📁 Datos de <?= $tabla ?></h3>

<table>
<tr>
<?php foreach ($columns as $col): ?>
<th><?= $col->name ?></th>
<?php endforeach; ?>
<th>Acciones</th>
</tr>

<?php while ($row = $data->fetch_assoc()): ?>
<tr>

<?php foreach ($row as $value): ?>
<td><?= $value ?></td>
<?php endforeach; ?>

<td class="actions">
<a href="?tabla=<?= $tabla ?>&delete=<?= $row[array_key_first($row)] ?>">Eliminar</a>
</td>

</tr>
<?php endwhile; ?>

</table>

</div>

</div>

</body>
</html>