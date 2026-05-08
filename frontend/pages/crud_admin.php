<?php
session_start();
require_once "../config/conexion.php";

// =========================
// VALIDAR SESIÓN Y ROL
// =========================
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

// =========================
// OBTENER TABLAS
// =========================
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

// =========================
// INSERTAR REGISTRO
// =========================
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
            $mensaje = "✅ Registro guardado correctamente.";
        } else {
            $error = "❌ No se pudo guardar: " . $stmt->error;
        }
    } else {
        $error = "⚠️ No hay datos para insertar.";
    }
}

// =========================
// ELIMINAR REGISTRO
// =========================
if (isset($_GET["delete"]) && $tabla !== "" && $primaryKey) {
    $id = $_GET["delete"];
    $stmt = $conn->prepare("DELETE FROM `$tabla` WHERE `$primaryKey` = ? LIMIT 1");
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        header("Location: crud_admin.php?tabla=" . urlencode($tabla) . "&success=deleted");
        exit();
    }

    $error = "❌ No se pudo eliminar: " . $stmt->error;
}

// Verificar si hay éxito por eliminación
if (isset($_GET["success"]) && $_GET["success"] === "deleted") {
    $mensaje = "🗑️ Registro eliminado correctamente.";
}

// =========================
// OBTENER DATOS
// =========================
$data = $tabla !== "" ? $conn->query("SELECT * FROM `$tabla` LIMIT 100") : false;

// Contar registros
$totalRegistros = 0;
if ($data && $data->num_rows > 0) {
    $totalRegistros = $data->num_rows;
    // Resetear puntero
    $data->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dental Gurú | CRUD Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary: #625fa5;
            --primary-dark: #4a4790;
            --primary-light: #7b78b5;
            --green: #87f4b5;
            --green-dark: #6adf9e;
            --green-light: #b0f0d1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f6fa 0%, #f0f1f5 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER FIJO */
        .header-fixed {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        /* CARDS */
        .crud-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(98, 95, 165, 0.08);
        }

        .crud-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(98, 95, 165, 0.1);
        }

        /* INPUTS */
        .input-ui {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #fafbfc;
        }

        .input-ui:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(98, 95, 165, 0.1);
            background: white;
        }

        /* SELECT */
        .select-ui {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            font-size: 14px;
            background: #fafbfc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .select-ui:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(98, 95, 165, 0.1);
        }

        /* BOTONES */
        .btn-main {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-dark) 100%);
            color: #1a1a1a;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(135, 244, 181, 0.4);
        }

        .btn-main:active {
            transform: translateY(0);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* TABLA */
        .table-container {
            overflow-x: auto;
            border-radius: 16px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #374151;
        }

        .table tbody tr:hover {
            background: #faf5ff;
            transition: background 0.2s ease;
        }

        /* BOTÓN ELIMINAR */
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-1px);
        }

        /* ALERTAS */
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* BADGES */
        .badge-info {
            background: var(--primary-light);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* FOOTER */
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            text-align: center;
            padding: 25px 20px;
            margin-top: auto;
        }

        /* ANIMACIONES */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.4s ease-out;
        }

        /* SCROLLBAR */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }
    </style>
</head>
<body>

<!-- HEADER MEJORADO -->
<header class="header-fixed">
    <div class="flex justify-between items-center px-4 md:px-8 py-3">
        <div class="flex items-center gap-3">
            <img src="../assets/logo.png" class="w-12 md:w-14 rounded-xl shadow-md" alt="Dental Gurú" onerror="this.style.display='none'">
            <div>
                <p class="font-bold text-white text-lg">Dental Gurú</p>
                <p class="text-xs text-white/80">CRUD Administrativo</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-full">
                <i class="fas fa-user-circle text-white"></i>
                <span class="text-sm text-white font-medium"><?php echo e($_SESSION["nombre"]); ?></span>
            </div>
            <a href="admin.php" class="btn-outline !bg-white/10 !border-white/30 !text-white hover:!bg-white/20">
                <i class="fas fa-arrow-left"></i> Panel
            </a>
            <a href="../config/cerrar_sesion.php" class="text-white/80 hover:text-white transition">
                <i class="fas fa-sign-out-alt text-xl"></i>
            </a>
        </div>
    </div>
</header>

<main class="flex-1 w-full px-4 md:px-8 py-6">
    <div class="max-w-7xl mx-auto">
        
        <!-- TÍTULO -->
        <div class="mb-6 animate-fadeIn">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-database text-[#625fa5]"></i>
                CRUD Administrativo
            </h1>
            <p class="text-gray-500 text-sm mt-1">Gestión dinámica de todas las tablas de la base de datos</p>
        </div>

        <!-- ALERTAS -->
        <?php if ($mensaje): ?>
        <div class="p-4 rounded-xl mb-4 alert-success flex items-center gap-3 animate-fadeIn">
            <i class="fas fa-check-circle text-lg"></i>
            <span><?php echo e($mensaje); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="p-4 rounded-xl mb-4 alert-error flex items-center gap-3 animate-fadeIn">
            <i class="fas fa-exclamation-triangle text-lg"></i>
            <span><?php echo e($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- SELECCIONAR TABLA -->
        <div class="crud-card p-5 mb-6 animate-fadeIn">
            <div class="flex items-center gap-2 mb-4">
                <i class="fas fa-table text-[#625fa5] text-lg"></i>
                <h2 class="font-semibold text-gray-700">Seleccionar Tabla</h2>
            </div>
            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <select id="tabla" name="tabla" class="select-ui">
                        <?php foreach ($tablas as $t): ?>
                        <option value="<?php echo e($t); ?>" <?php echo $tabla === $t ? "selected" : ""; ?>>
                            <?php echo e($t); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-main">
                    <i class="fas fa-sync-alt"></i> Cargar Tabla
                </button>
            </form>
        </div>

        <?php if ($tabla !== ""): ?>
        <!-- INSERTAR REGISTRO -->
        <div class="crud-card p-5 mb-6 animate-fadeIn">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="fas fa-plus-circle text-[#87f4b5] text-xl"></i>
                    <h2 class="font-semibold text-gray-700">Insertar en <span class="text-[#625fa5] font-bold"><?php echo e($tabla); ?></span></h2>
                </div>
                <span class="badge-info">
                    <i class="fas fa-info-circle mr-1"></i> Campos obligatorios marcados con *
                </span>
            </div>
            
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($columns as $col): ?>
                    <?php if (!str_contains($col["Extra"], "auto_increment")): ?>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">
                            <?php echo e($col["Field"]); ?>
                            <?php if ($col["Null"] === "NO" && !str_contains($col["Default"], "CURRENT_TIMESTAMP")): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" 
                               name="<?php echo e($col["Field"]); ?>" 
                               placeholder="<?php echo e($col["Field"]); ?>" 
                               class="input-ui"
                               value="<?php echo isset($_POST[$col["Field"]]) ? e($_POST[$col["Field"]]) : ''; ?>">
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="md:col-span-2 lg:col-span-3 mt-2">
                    <button type="submit" name="insert" class="btn-main w-full md:w-auto">
                        <i class="fas fa-save"></i> Guardar Registro
                    </button>
                </div>
            </form>
        </div>

        <!-- DATOS DE LA TABLA -->
        <div class="crud-card p-5 animate-fadeIn">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="fas fa-list-ul text-[#625fa5] text-lg"></i>
                    <h2 class="font-semibold text-gray-700">Datos de <span class="text-[#625fa5] font-bold"><?php echo e($tabla); ?></span></h2>
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full">
                        <i class="fas fa-database mr-1"></i><?= $totalRegistros ?> registros
                    </span>
                </div>
                <div class="flex gap-2">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" id="searchTable" placeholder="Buscar..." class="pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm w-full md:w-56">
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table" id="dataTable">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                            <th><?php echo e($col["Field"]); ?></th>
                            <?php endforeach; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data && $data->num_rows > 0): ?>
                            <?php while ($row = $data->fetch_assoc()): ?>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                <td title="<?php echo e($row[$col["Field"]] ?? ''); ?>">
                                    <?php 
                                        $value = $row[$col["Field"]] ?? "";
                                        echo strlen($value) > 50 ? e(substr($value, 0, 50)) . "..." : e($value);
                                    ?>
                                </td>
                                <?php endforeach; ?>
                                <td>
                                    <?php if ($primaryKey): ?>
                                    <a class="btn-delete" 
                                       href="javascript:void(0)" 
                                       onclick="confirmDelete('<?php echo urlencode($tabla); ?>', '<?php echo urlencode($row[$primaryKey]); ?>')">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo count($columns) + 1; ?>" class="text-center py-8 text-gray-500">
                                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                                    No hay registros en esta tabla
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalRegistros >= 100): ?>
            <div class="mt-4 text-center text-xs text-gray-400">
                <i class="fas fa-info-circle mr-1"></i> Mostrando los últimos 100 registros
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- FOOTER MEJORADO -->
<footer class="footer">
    <div class="relative inline-block group">
        <span class="font-semibold cursor-pointer hover:opacity-90 transition">
            <i class="fas fa-code-branch mr-1"></i> Error 404: Members not found
        </span>
        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-3 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 bg-white text-gray-800 p-4 rounded-xl shadow-xl min-w-[280px] z-10">
            <div class="space-y-2">
                <p class="text-sm font-medium"><i class="fas fa-user text-[#625fa5] mr-2"></i>SHIRLEY ESTEFANÍA SALAZAR MORALES</p>
                <p class="text-sm font-medium"><i class="fas fa-user text-[#625fa5] mr-2"></i>KEVIN ALEJANDRO LEMUS TEJADA</p>
                <p class="text-sm font-medium"><i class="fas fa-user text-[#625fa5] mr-2"></i>MARCOS ANTONIO QUINTANILLA VALLE</p>
                <p class="text-sm font-medium"><i class="fas fa-user text-[#625fa5] mr-2"></i>MILTON ALEXIS GUTIÉRREZ RODRÍGUEZ</p>
                <p class="text-sm font-medium"><i class="fas fa-user text-[#625fa5] mr-2"></i>OTTO FERNANDO SÁNCHEZ CENTENO</p>
            </div>
            <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 rotate-45 w-3 h-3 bg-white"></div>
        </div>
    </div>
    <p class="text-xs mt-3 opacity-80">Sistema clínico Dental Gurú © 2024 | CRUD Administrativo</p>
</footer>

<script>
// BÚSQUEDA EN TABLA
document.getElementById('searchTable')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('dataTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        let text = '';
        for (let cell of row.getElementsByTagName('td')) {
            text += cell.textContent.toLowerCase();
        }
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    }
});

// CONFIRMAR ELIMINACIÓN MEJORADA
function confirmDelete(tabla, id) {
    Swal.fire({
        title: '¿Eliminar registro?',
        text: `Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#625fa5',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: 'white',
        customClass: {
            popup: 'rounded-2xl',
            title: 'text-gray-800',
            confirmButton: 'btn-delete !rounded-lg',
            cancelButton: 'btn-outline !rounded-lg'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?tabla=${encodeURIComponent(tabla)}&delete=${encodeURIComponent(id)}`;
        }
    });
}

// MENSAJES DE ÉXITO CON SWEET ALERT
<?php if ($mensaje && strpos($mensaje, 'guardado') !== false): ?>
Swal.fire({
    icon: 'success',
    title: '¡Registro guardado!',
    text: 'El registro se ha guardado correctamente.',
    confirmButtonColor: '#625fa5',
    timer: 2000,
    showConfirmButton: false
});
<?php endif; ?>

<?php if ($mensaje && strpos($mensaje, 'eliminado') !== false): ?>
Swal.fire({
    icon: 'success',
    title: '¡Registro eliminado!',
    text: 'El registro se ha eliminado correctamente.',
    confirmButtonColor: '#625fa5',
    timer: 2000,
    showConfirmButton: false
});
<?php endif; ?>
</script>

<!-- SweetAlert2 para mejores alertas -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>