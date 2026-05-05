<?php
session_start();
require_once "../config/conexion.php";

if (!isset($_SESSION["id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "admin") {
    header("Location: ../index.php");
    exit();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$tablas = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $tablas[] = $row[0];
    }
}

$tabla = $_GET["tabla"] ?? ($tablas[0] ?? "");
if (!in_array($tabla, $tablas, true)) {
    $tabla = $tablas[0] ?? "";
}

$mensaje = "";
$error = "";
$columns = [];
$primaryKey = null;

if ($tabla !== "") {
    $dataColumns = $conn->query("SHOW COLUMNS FROM `$tabla`");
    if ($dataColumns) {
        while ($col = $dataColumns->fetch_assoc()) {
            $columns[] = $col;
            if ($col["Key"] === "PRI") {
                $primaryKey = $col["Field"];
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["insert"]) && $tabla !== "") {
    $cols = [];
    $placeholders = [];
    $values = [];
    $types = "";

    foreach ($columns as $col) {
        $name = $col["Field"];
        $isAutoIncrement = str_contains($col["Extra"], "auto_increment");

        if ($isAutoIncrement || !array_key_exists($name, $_POST)) {
            continue;
        }

        $value = trim($_POST[$name]);
        if ($value === "") {
            $value = null;
        }

        $cols[] = "`$name`";
        $placeholders[] = "?";
        $values[] = $value;
        $types .= "s";
    }

    if ($cols) {
        $sql = "INSERT INTO `$tabla` (" . implode(",", $cols) . ") VALUES (" . implode(",", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $mensaje = "Registro guardado correctamente.";
        } else {
            $error = "No se pudo guardar: " . $stmt->error;
        }
    } else {
        $error = "No hay datos para insertar.";
    }
}

if (isset($_GET["delete"]) && $tabla !== "" && $primaryKey) {
    $id = $_GET["delete"];
    $stmt = $conn->prepare("DELETE FROM `$tabla` WHERE `$primaryKey` = ? LIMIT 1");
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        header("Location: crud_admin.php?tabla=" . urlencode($tabla));
        exit();
    }

    $error = "No se pudo eliminar: " . $stmt->error;
}

$data = $tabla !== "" ? $conn->query("SELECT * FROM `$tabla` LIMIT 100") : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dental Guru | CRUD Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="../css/styles.css">
<style>
:root{--primary:#a774d6;--secondary-soft:#dff1e6;}
html,body{min-height:100%;margin:0;}
body{font-family:Inter,Arial,sans-serif;background:var(--secondary-soft);}
.header-ui{background:var(--primary);box-shadow:0 4px 14px rgba(0,0,0,.08);}
.crud-card{background:white;border-radius:18px;box-shadow:0 8px 18px rgba(0,0,0,.06);}
table{width:100%;border-collapse:collapse;background:white;border-radius:14px;overflow:hidden;}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left;font-size:13px;vertical-align:top;}
th{background:#a774d6;color:white;font-weight:700;}
</style>
</head>
<body>

<header class="header-ui px-6 py-3 flex justify-between items-center">
<img src="../assets/logo.png" class="w-28 rounded-lg" alt="Dental Guru">
<div class="flex items-center gap-4">
<span class="text-sm font-semibold text-white"><?php echo e($_SESSION["nombre"]); ?></span>
<a href="admin.php" class="text-xs font-semibold text-white hover:underline">Panel admin</a>
<a href="../config/cerrar_sesion.php" class="text-xs font-semibold text-white hover:underline">Cerrar sesion</a>
</div>
</header>

<main class="max-w-7xl mx-auto p-4 space-y-4">
<div>
<h1 class="text-2xl font-bold">CRUD Administrativo</h1>
<p class="text-sm text-gray-600">Gestion dinamica de tablas de la base de datos.</p>
</div>

<?php if ($mensaje): ?>
<div class="p-3 rounded-2xl text-sm bg-green-50 text-green-700 border border-green-200"><?php echo e($mensaje); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="p-3 rounded-2xl text-sm bg-red-50 text-red-700 border border-red-200"><?php echo e($error); ?></div>
<?php endif; ?>

<section class="crud-card p-4">
<form method="GET" class="flex flex-col md:flex-row md:items-center gap-3">
<label class="font-semibold text-sm" for="tabla">Tabla</label>
<select id="tabla" name="tabla" class="input-ui md:w-80">
<?php foreach ($tablas as $t): ?>
<option value="<?php echo e($t); ?>" <?php echo $tabla === $t ? "selected" : ""; ?>><?php echo e($t); ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn-main">Cargar</button>
</form>
</section>

<section class="crud-card p-4">
<h2 class="font-bold mb-3">Insertar en <?php echo e($tabla); ?></h2>
<form method="POST" class="grid md:grid-cols-3 gap-3">
<?php foreach ($columns as $col): ?>
<?php if (!str_contains($col["Extra"], "auto_increment")): ?>
<input type="text" name="<?php echo e($col["Field"]); ?>" placeholder="<?php echo e($col["Field"]); ?>" class="input-ui">
<?php endif; ?>
<?php endforeach; ?>
<button type="submit" name="insert" class="btn-main md:col-span-3">Guardar</button>
</form>
</section>

<section class="crud-card p-4 overflow-x-auto">
<h2 class="font-bold mb-3">Datos de <?php echo e($tabla); ?></h2>
<table>
<thead>
<tr>
<?php foreach ($columns as $col): ?>
<th><?php echo e($col["Field"]); ?></th>
<?php endforeach; ?>
<th>Acciones</th>
</tr>
</thead>
<tbody>
<?php if ($data): ?>
<?php while ($row = $data->fetch_assoc()): ?>
<tr>
<?php foreach ($columns as $col): ?>
<td><?php echo e($row[$col["Field"]] ?? ""); ?></td>
<?php endforeach; ?>
<td>
<?php if ($primaryKey): ?>
<a class="text-red-600 font-semibold text-xs" data-confirm="Seguro que deseas eliminar este registro?" href="?tabla=<?php echo urlencode($tabla); ?>&delete=<?php echo urlencode($row[$primaryKey]); ?>">Eliminar</a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php endif; ?>
</tbody>
</table>
</section>
</main>

<script src="../js/app.js"></script>
</body>
</html>
