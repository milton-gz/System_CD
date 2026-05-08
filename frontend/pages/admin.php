<?php
// Mostrar errores para depuración (QUITA ESTO EN PRODUCCIÓN)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION)) session_start();
require_once "../config/conexion.php";

// =========================
// 1. VALIDAR SESIÓN
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// =========================
// 2. VALIDAR ROL (SOLO ADMIN)
// =========================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
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
            session_destroy();
            header("Location: ../login.php");
            break;
    }
    exit();
}

// =========================
// 3. SEGURIDAD EXTRA
// =========================
if (!isset($_SESSION['nombre'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// =========================
// 4. ESTADÍSTICAS AVANZADAS CON MANEJO DE ERRORES
// =========================

// Usuarios totales
$totalUsuarios = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM USUARIO");
if ($result && $result->num_rows > 0) {
    $totalUsuarios = $result->fetch_assoc()['total'];
}

// Citas hoy
$totalCitasHoy = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM CITA WHERE fecha = CURDATE()");
if ($result && $result->num_rows > 0) {
    $totalCitasHoy = $result->fetch_assoc()['total'];
}

// Productos críticos
$totalProductosCriticos = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE estado IN ('bajo','agotado')");
if ($result && $result->num_rows > 0) {
    $totalProductosCriticos = $result->fetch_assoc()['total'];
}

// Citas por día de la semana (últimos 7 días)
$diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$citasPorDia = array_fill(0, 7, 0);

$semanaQuery = $conn->query("
    SELECT 
        DAYOFWEEK(fecha) as dia_semana,
        COUNT(*) as total
    FROM CITA
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DAYOFWEEK(fecha)
");

if ($semanaQuery && $semanaQuery->num_rows > 0) {
    while ($row = $semanaQuery->fetch_assoc()) {
        $idx = $row['dia_semana'] - 1;
        if ($idx >= 0 && $idx < 7) {
            $citasPorDia[$idx] = $row['total'];
        }
    }
}

// Top 5 productos más usados en recetas (con verificación de tablas)
$topMedicinas = [];
$tablaMedicamentosExiste = false;

// Verificar si la tabla RECETA_MEDICAMENTO existe
$checkTables = $conn->query("SHOW TABLES LIKE 'RECETA_MEDICAMENTO'");
if ($checkTables && $checkTables->num_rows > 0) {
    $medicinasQuery = $conn->query("
        SELECT 
            m.nombre,
            COUNT(*) as total_recetas
        FROM RECETA_MEDICAMENTO rm
        JOIN MEDICAMENTO m ON rm.MEDICAMENTO_id_medicamento = m.id_medicamento
        GROUP BY m.id_medicamento
        ORDER BY total_recetas DESC
        LIMIT 5
    ");
    
    if ($medicinasQuery && $medicinasQuery->num_rows > 0) {
        while ($row = $medicinasQuery->fetch_assoc()) {
            $topMedicinas[] = $row;
        }
    }
}

// Citas por estado (incluyendo 'atendida' en lugar de 'completada')
$estadosCitas = ['pendiente', 'confirmada', 'atendida', 'cancelada'];
$citasPorEstado = array_fill(0, 4, 0);

$estadoQuery = $conn->query("SELECT estado, COUNT(*) as total FROM CITA GROUP BY estado");
if ($estadoQuery && $estadoQuery->num_rows > 0) {
    while ($row = $estadoQuery->fetch_assoc()) {
        $estado = strtolower(trim($row['estado']));
        if ($estado == 'pendiente') $citasPorEstado[0] = $row['total'];
        elseif ($estado == 'confirmada') $citasPorEstado[1] = $row['total'];
        elseif ($estado == 'atendida' || $estado == 'completada') $citasPorEstado[2] = $row['total'];
        elseif ($estado == 'cancelada') $citasPorEstado[3] = $row['total'];
    }
}

// =========================
// OBTENER USUARIOS
// =========================
$usuarios = [];
$sql = "SELECT U.id_usuario, U.nombre, U.correo, R.nombre AS rol, U.estado, U.ultimo_acceso
        FROM USUARIO U
        JOIN ROL R ON U.ROL_id_rol = R.id_rol
        ORDER BY U.id_usuario DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// =========================
// OBTENER CITAS RECIENTES
// =========================
$citas = [];
$sql = "
SELECT
    C.id_cita,
    C.fecha,
    C.hora,
    C.tipo,
    C.estado,
    UP.nombre AS paciente,
    UD.nombre AS doctor
FROM CITA C
LEFT JOIN PACIENTE P ON P.id_paciente = C.PACIENTE_id_paciente
LEFT JOIN USUARIO UP ON UP.id_usuario = P.USUARIO_id_usuario
LEFT JOIN DOCTOR D ON D.id_doctor = C.DOCTOR_id_doctor
LEFT JOIN USUARIO UD ON UD.id_usuario = D.USUARIO_id_usuario
ORDER BY C.fecha DESC, C.hora DESC
LIMIT 50";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convertir 'completada' a 'atendida' para mostrar
        if ($row['estado'] == 'completada') {
            $row['estado'] = 'atendida';
        }
        $citas[] = $row;
    }
}

// =========================
// OBTENER INVENTARIO
// =========================
$inventario = [];
$sql = "SELECT nombre, categoria, stock, fecha_reposicion, estado FROM PRODUCTO ORDER BY stock ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventario[] = $row;
    }
}

// Calcular porcentaje para gráfico de stock
$totalProductos = count($inventario);
$productosCriticos = 0;
foreach ($inventario as $p) {
    if ($p['estado'] == 'bajo' || $p['estado'] == 'agotado') $productosCriticos++;
}
$porcentajeCritico = $totalProductos > 0 ? round(($productosCriticos / $totalProductos) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dental Gurú | Panel Administrativo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            font-family: system-ui, -apple-system, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 70px;
        }

        .header-fixed {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--primary);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 280px;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            transition: right 0.3s ease;
            z-index: 1001;
            padding: 24px 20px;
        }

        .mobile-menu.active {
            right: 0;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Contenedor para gráficas más pequeñas */
        .chart-container {
            max-width: 6 5%;
            margin: 0 auto;
        }

        .tab {
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            background: #f0f0f0;
            color: #666;
        }

        .tab.active {
            background: var(--green);
            color: #1a1a1a;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .table th {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            padding: 12px 16px;
            text-align: left;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .btn-crud {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-dark) 100%);
            color: #1a1a1a;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-activo { background: #d1fae5; color: #065f46; }
        .badge-inactivo { background: #fee2e2; color: #991b1b; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        .badge-confirmada { background: #dbeafe; color: #1e40af; }
        .badge-atendida { background: #d1fae5; color: #065f46; }
        .badge-cancelada { background: #fee2e2; color: #991b1b; }
        .badge-agotado { background: #fee2e2; color: #dc2626; }
        .badge-bajo { background: #fef3c7; color: #d97706; }
        .badge-normal { background: #d1fae5; color: #059669; }

        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: auto;
        }

        .hidden {
            display: none;
        }
    </style>
</head>

<body>

<header class="header-fixed">
    <div class="flex justify-between items-center px-4 md:px-8 py-3">
        <div class="flex items-center gap-3">
            <img src="../assets/logo.png" class="w-10 md:w-12 rounded-lg" alt="Logo" onerror="this.style.display='none'">
            <div class="hidden sm:block">
                <p class="font-semibold text-white text-sm md:text-base">Dental Gurú</p>
                <p class="text-xs text-white/80">Panel administrativo</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-white text-sm hidden md:block">
                <i class="fas fa-user-circle mr-2"></i><?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin') ?>
            </span>
            <button onclick="openMenu()" class="text-white text-2xl">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</header>

<div id="overlay" class="overlay" onclick="closeMenu()"></div>

<div id="menu" class="mobile-menu">
    <div class="flex justify-between items-center mb-6 pb-3 border-b border-white/20">
        <div class="flex items-center gap-2">
            <img src="../assets/logo.png" class="w-8 rounded-lg" alt="Logo" onerror="this.style.display='none'">
            <p class="font-bold text-white">Dental Gurú</p>
        </div>
        <button onclick="closeMenu()" class="text-white/80 text-xl">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="mb-6 p-3 bg-white/10 rounded-xl">
        <p class="text-sm font-medium text-white/90"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin') ?></p>
        <p class="text-xs text-white/70 mt-1">
            <i class="fas fa-shield-alt mr-1"></i>Administrador
        </p>
    </div>

    <nav class="space-y-2">
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition">
            <i class="fas fa-chart-line w-5"></i> Dashboard
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition">
            <i class="fas fa-users w-5"></i> Usuarios
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition">
            <i class="fas fa-calendar w-5"></i> Citas
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/10 transition">
            <i class="fas fa-boxes w-5"></i> Inventario
        </a>
    </nav>

    <hr class="my-4 border-white/20">

    <a href="../config/cerrar_sesion.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition">
        <i class="fas fa-sign-out-alt w-5"></i> Cerrar sesión
    </a>
</div>

<main class="flex-1 w-full px-4 md:px-8 py-6">
    <div class="max-w-7xl mx-auto">
        
        <div class="mb-6">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Panel Administrativo</h2>
            <p class="text-gray-500 text-sm mt-1">Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin') ?></p>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 font-medium">Usuarios</p>
                    <i class="fas fa-users text-2xl text-[#625fa5]/30"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800"><?= $totalUsuarios ?></h3>
            </div>

            <div class="card">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 font-medium">Citas hoy</p>
                    <i class="fas fa-calendar-day text-2xl text-[#625fa5]/30"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800"><?= $totalCitasHoy ?></h3>
            </div>

            <div class="card">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 font-medium">Productos críticos</p>
                    <i class="fas fa-exclamation-triangle text-2xl text-red-500/30"></i>
                </div>
                <h3 class="text-2xl font-bold text-red-600"><?= $totalProductosCriticos ?></h3>
            </div>

            <div class="card">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-gray-500 font-medium">Acciones rápidas</p>
                    <i class="fas fa-bolt text-2xl text-[#87f4b5]/50"></i>
                </div>
                <a href="crud_admin.php" class="btn-crud w-full justify-center">
                    <i class="fas fa-cogs"></i> Gestionar sistema
                </a>
            </div>
        </div>

        <!-- GRÁFICAS MÁS PEQUEÑAS (15% más pequeñas) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card">
                <h4 class="font-semibold text-gray-800 mb-3 text-center">
                    <i class="fas fa-chart-line text-[#625fa5] mr-2"></i> Citas - Últimos 7 días
                </h4>
                <div class="chart-container" style="max-width: 65%; margin: 0 auto;">
                    <canvas id="citasChart" height="170"></canvas>
                </div>
            </div>

            <div class="card">
                <h4 class="font-semibold text-gray-800 mb-3 text-center">
                    <i class="fas fa-chart-pie text-[#625fa5] mr-2"></i> Distribución de citas
                </h4>
                <div class="chart-container" style="max-width: 65%; margin: 0 auto;">
                    <canvas id="estadoChart" height="170"></canvas>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="flex flex-col sm:flex-row sm:justify-between gap-3 mb-4">
            <div class="flex gap-2 flex-wrap">
                <button class="tab active" data-tab="usuarios">
                    <i class="fas fa-users mr-1"></i> Usuarios (<?= count($usuarios) ?>)
                </button>
                <button class="tab" data-tab="citas">
                    <i class="fas fa-calendar mr-1"></i> Citas (<?= count($citas) ?>)
                </button>
                <button class="tab" data-tab="inventario">
                    <i class="fas fa-boxes mr-1"></i> Inventario (<?= count($inventario) ?>)
                </button>
            </div>

            <input type="text" id="search" placeholder="Buscar..." class="px-4 py-2 border border-gray-200 rounded-xl w-full sm:w-64 text-sm">
        </div>

        <!-- TABLA USUARIOS -->
        <section id="usuarios" class="card tab-content">
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if(count($usuarios) > 0): ?>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u["nombre"] ?? '') ?></td>
                                <td><?= htmlspecialchars($u["correo"] ?? '') ?></td>
                                <td><span class="badge bg-[#625fa5]/10 text-[#625fa5]"><?= htmlspecialchars($u["rol"] ?? '') ?></span></td>
                                <td><span class="badge <?= ($u["estado"] ?? '') == 'activo' ? 'badge-activo' : 'badge-inactivo' ?>"><?= $u["estado"] ?? '' ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-8 text-gray-500">No hay usuarios registrados</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TABLA CITAS -->
        <section id="citas" class="card tab-content hidden">
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Doctor</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if(count($citas) > 0): ?>
                            <?php foreach ($citas as $c): ?>
                            <tr>
                                <td><?= isset($c["fecha"]) ? date('d/m/Y', strtotime($c["fecha"])) : 'N/A' ?></td>
                                <td><?= isset($c["hora"]) ? substr($c["hora"], 0, 5) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($c["paciente"] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($c["doctor"] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge 
                                        <?= $c["estado"] == 'pendiente' ? 'badge-pendiente' : 
                                           ($c["estado"] == 'confirmada' ? 'badge-confirmada' : 
                                           ($c["estado"] == 'atendida' ? 'badge-atendida' : 'badge-cancelada')) ?>">
                                        <?= ucfirst($c["estado"] ?? 'pendiente') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-8 text-gray-500">No hay citas registradas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- TABLA INVENTARIO -->
        <section id="inventario" class="card tab-content hidden">
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Producto</th><th>Categoría</th><th>Stock</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if(count($inventario) > 0): ?>
                            <?php foreach ($inventario as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p["nombre"] ?? '') ?></td>
                                <td><?= htmlspecialchars($p["categoria"] ?? '') ?></td>
                                <td><?= $p["stock"] ?? 0 ?> unidades</td>
                                <td>
                                    <span class="badge <?= ($p["estado"] ?? '') == 'agotado' ? 'badge-agotado' : (($p["estado"] ?? '') == 'bajo' ? 'badge-bajo' : 'badge-normal') ?>">
                                        <?= $p["estado"] == 'agotado' ? 'Agotado' : ($p["estado"] == 'bajo' ? 'Stock bajo' : 'Normal') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-8 text-gray-500">No hay productos en inventario</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

<footer class="footer">
    <div class="relative inline-block group">
        <span class="font-semibold cursor-pointer">
            <i class="fas fa-code-branch mr-1"></i> Error 404: Members not found
        </span>
        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-3 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition bg-white text-gray-800 p-4 rounded-xl shadow-xl min-w-[290px] z-10">
            <p class="text-sm">SHIRLEY ESTEFANÍA SALAZAR MORALES</p>
            <p class="text-sm">KEVIN ALEJANDRO LEMUS TEJADA</p>
            <p class="text-sm">MARCOS ANTONIO QUINTANILLA VALLE</p>
            <p class="text-sm">MILTON ALEXIS GUTIÉRREZ RODRÍGUEZ</p>
            <p class="text-sm">OTTO FERNANDO SÁNCHEZ CENTENO</p>
        </div>
    </div>
    <p class="text-xs mt-3 opacity-80">Sistema clínico Dental Gurú © 2024</p>
</footer>

<script>
function openMenu() {
    document.getElementById("menu").classList.add("active");
    document.getElementById("overlay").classList.add("active");
    document.body.style.overflow = "hidden";
}

function closeMenu() {
    document.getElementById("menu").classList.remove("active");
    document.getElementById("overlay").classList.remove("active");
    document.body.style.overflow = "";
}

document.querySelectorAll(".tab").forEach(btn => {
    btn.onclick = () => {
        document.querySelectorAll(".tab").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        document.querySelectorAll(".tab-content").forEach(c => c.classList.add("hidden"));
        document.getElementById(btn.dataset.tab).classList.remove("hidden");
    };
});

document.getElementById("search").addEventListener("input", function() {
    let val = this.value.toLowerCase();
    const activeTab = document.querySelector(".tab.active").dataset.tab;
    const rows = document.querySelectorAll("#" + activeTab + " tbody tr");
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? "" : "none";
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de citas por día - Con colores mejorados
    const ctx1 = document.getElementById('citasChart');
    if(ctx1) {
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?= json_encode($diasSemana) ?>,
                datasets: [{
                    label: 'Citas',
                    data: <?= json_encode($citasPorDia) ?>,
                    borderColor: '#625fa5',
                    backgroundColor: 'rgba(98, 95, 165, 0.08)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#87f4b5',
                    pointBorderColor: '#625fa5',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#6adf9e'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                plugins: { 
                    legend: { 
                        display: false 
                    },
                    tooltip: {
                        backgroundColor: '#625fa5',
                        titleColor: 'white',
                        bodyColor: 'rgba(255,255,255,0.9)',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#e5e7eb' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Gráfico de estado de citas - Con colores más vibrantes
    const ctx2 = document.getElementById('estadoChart');
    if(ctx2) {
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($estadosCitas) ?>,
                datasets: [{
                    data: <?= json_encode($citasPorEstado) ?>,
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                cutout: '60%',
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>