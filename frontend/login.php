<?php
session_start();
ob_start();
require_once "../frontend/config/conexion.php"; // Ajusta la ruta según tu estructura

$mensaje = "";
$mensaje_tipo = ""; // 'error' o 'success'

// =========================
// VALIDAR REQUEST
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // =====================================================
    // REGISTRO (ROL = 4 PACIENTE + AUTO LOGIN)
    // =====================================================
    if ($action === "register") {
        $nombre = trim($_POST["nombre"] ?? "");
        $correo = trim($_POST["correo"] ?? "");
        $clave = trim($_POST["clave"] ?? "");
        $confirmar = trim($_POST["confirmar_clave"] ?? "");
        $rol_id = 4; // paciente por defecto

        // VALIDACIONES
        if ($nombre === "" || $correo === "" || $clave === "" || $confirmar === "") {
            $mensaje = "Todos los campos son obligatorios.";
            $mensaje_tipo = "error";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "Correo electrónico inválido.";
            $mensaje_tipo = "error";
        } elseif (strlen($clave) < 6) {
            $mensaje = "La contraseña debe tener mínimo 6 caracteres.";
            $mensaje_tipo = "error";
        } elseif ($clave !== $confirmar) {
            $mensaje = "Las contraseñas no coinciden.";
            $mensaje_tipo = "error";
        } else {
            // Verificar si correo ya existe
            $stmt = $conn->prepare("SELECT id_usuario FROM USUARIO WHERE correo = ?");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $mensaje = "Este correo ya está registrado.";
                $mensaje_tipo = "error";
            } else {
                // Encriptar contraseña
                $hash = password_hash($clave, PASSWORD_DEFAULT);

                // Insertar usuario
                $stmt = $conn->prepare("
                    INSERT INTO USUARIO (ROL_id_rol, nombre, correo, password, estado)
                    VALUES (?, ?, ?, ?, 'activo')
                ");
                $stmt->bind_param("isss", $rol_id, $nombre, $correo, $hash);

                if ($stmt->execute()) {
                    $id_usuario = $stmt->insert_id;

                    // Crear paciente automáticamente
                    $stmt2 = $conn->prepare("
                        INSERT INTO PACIENTE (USUARIO_id_usuario)
                        VALUES (?)
                    ");
                    $stmt2->bind_param("i", $id_usuario);
                    $stmt2->execute();

                    // AUTO LOGIN
                    $_SESSION["id"] = $id_usuario;
                    $_SESSION["nombre"] = $nombre;
                    $_SESSION["rol"] = "paciente";

                    // Guardar último acceso
                    $update = $conn->prepare("
                        UPDATE USUARIO 
                        SET ultimo_acceso = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $update->bind_param("i", $id_usuario);
                    $update->execute();

                    // REDIRECCIÓN DIRECTA
                    echo "<script>window.location='validacion.php'</script>";
                    exit;
                } else {
                    $mensaje = "Error al registrar. Intente nuevamente.";
                    $mensaje_tipo = "error";
                }
            }
        }
    }

    // =====================================================
    // LOGIN
    // =====================================================
    if ($action === "login") {
        $correo = trim($_POST["correo"] ?? "");
        $clave = trim($_POST["clave"] ?? "");

        if ($correo === "" || $clave === "") {
            $mensaje = "Complete todos los campos.";
            $mensaje_tipo = "error";
        } else {
            $stmt = $conn->prepare("
                SELECT U.*, R.nombre AS rol
                FROM USUARIO U
                JOIN ROL R ON U.ROL_id_rol = R.id_rol
                WHERE U.correo = ? AND U.estado = 'activo'
            ");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $user = $res->fetch_assoc();

                // Verificar contraseña
                $passwordOk = password_verify($clave, $user["password"]);

                if ($passwordOk) {
                    // Crear sesión
                    $_SESSION["id"] = $user["id_usuario"];
                    $_SESSION["nombre"] = $user["nombre"];
                    $_SESSION["rol"] = $user["rol"];

                    // Actualizar último acceso
                    $update = $conn->prepare("
                        UPDATE USUARIO 
                        SET ultimo_acceso = NOW() 
                        WHERE id_usuario = ?
                    ");
                    $update->bind_param("i", $user["id_usuario"]);
                    $update->execute();

                    // Redirección según rol
                    echo "<script>window.location='validacion.php'</script>";
                    exit;
                } else {
                    $mensaje = "Contraseña incorrecta.";
                    $mensaje_tipo = "error";
                }
            } else {
                $mensaje = "Usuario no encontrado o inactivo.";
                $mensaje_tipo = "error";
            }
        }
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Dental Gurú | Sistema Clínico Dental</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #625fa5;
            --primary-dark: #4a4790;
            --primary-light: #7b78b5;
            --green: #87f4b5;
            --green-dark: #6adf9e;
            --green-light: #b0f0d1;
            --gray-50: #fafbfc;
            --gray-100: #f5f6f7;
            --gray-200: #e8eaed;
            --gray-600: #5f6368;
            --gray-700: #3c4043;
            --gray-800: #202124;
            --red-500: #ea4335;
            --red-600: #d3382a;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
        }

        /* Decoración de fondo */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(135,244,181,0.12) 0%, transparent 70%);
            border-radius: 50%;
            top: -250px;
            right: -250px;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(135,244,181,0.08) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -300px;
            left: -300px;
            pointer-events: none;
        }

        /* CONTENEDOR PRINCIPAL */
        .container {
            width: 100%;
            max-width: 1280px;
            background: #ffffff;
            border-radius: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255,255,255,0.1);
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 1;
            max-height: 90vh;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* IZQUIERDA - BRANDING */
        .left {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow-y: auto;
        }

        .left::-webkit-scrollbar {
            width: 4px;
        }

        .left::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
        }

        .left::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 10px;
        }

        /* LOGO MEJORADO - SIN CÍRCULO, MÁS GRANDE Y CON EFECTO SUTIL */
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease-out;
            position: relative;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-wrapper {
            display: inline-block;
            position: relative;
        }

        .logo {
            width: 220px;
            height: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.2));
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 20px;
        }

        /* Efecto hover: solo escala y sombra más fuerte */
        .logo-wrapper:hover .logo {
            transform: scale(1.05);
            filter: drop-shadow(0 15px 30px rgba(0, 0, 0, 0.3));
        }

        /* Efecto de "pulso" sutil al cargar la página */
        .logo-wrapper {
            animation: subtlePulse 2s ease-in-out;
        }

        @keyframes subtlePulse {
            0% {
                transform: scale(0.98);
                opacity: 0.8;
            }
            50% {
                transform: scale(1.02);
                opacity: 1;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .left h1 {
            font-size: 30px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.3px;
            line-height: 1.2;
            animation: fadeInUp 0.6s ease-out 0.1s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .left .description {
            font-size: 15px;
            opacity: 0.92;
            margin-bottom: 28px;
            line-height: 1.5;
            font-weight: 400;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        .feature-list {
            list-style: none;
            margin-top: 16px;
        }

        .feature-list li {
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
        }

        .feature-list li:nth-child(1) { animation-delay: 0.3s; }
        .feature-list li:nth-child(2) { animation-delay: 0.4s; }
        .feature-list li:nth-child(3) { animation-delay: 0.5s; }

        .feature-list i {
            font-size: 18px;
            color: var(--green);
            width: 24px;
        }

        /* DERECHA - FORMULARIOS */
        .right {
            padding: 48px 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            overflow-y: auto;
        }

        .right::-webkit-scrollbar {
            width: 4px;
        }

        .right::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }

        .right::-webkit-scrollbar-thumb {
            background: var(--gray-200);
            border-radius: 10px;
        }

        /* FORMULARIOS */
        .form {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.96);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .form h2 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .sub {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 32px;
            font-weight: 400;
        }

        /* INPUTS */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-600);
            font-size: 16px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            background: var(--gray-50);
            font-weight: 500;
        }

        .input:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(98, 95, 165, 0.08);
        }

        .input.error {
            border-color: var(--red-500);
            background: #fef8f8;
        }

        .error-message {
            color: var(--red-500);
            font-size: 12px;
            margin-top: 6px;
            display: block;
            font-weight: 500;
        }

        /* BOTÓN */
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--green) 0%, var(--green-dark) 100%);
            color: var(--gray-800);
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 8px;
            font-family: 'Inter', sans-serif;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(135, 244, 181, 0.35);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* LINK */
        .link {
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* ALERTA */
        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
            font-weight: 500;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.error {
            background: #fef8f8;
            border-left: 4px solid var(--red-500);
            color: var(--red-600);
        }

        .alert.success {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            color: #166534;
        }

        .alert i {
            font-size: 16px;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .container {
                max-width: 1100px;
            }
            
            .left h1 {
                font-size: 26px;
            }
            
            .logo {
                width: 180px;
            }
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
                max-width: 550px;
                max-height: 85vh;
                overflow-y: auto;
                border-radius: 32px;
            }
            
            .container::-webkit-scrollbar {
                width: 4px;
            }
            
            .left {
                padding: 40px 32px;
                text-align: center;
            }
            
            .feature-list li {
                justify-content: center;
            }
            
            .right {
                padding: 40px 32px;
            }
            
            .logo {
                width: 160px;
            }
            
            .left h1 {
                font-size: 24px;
            }
            
            .form h2 {
                font-size: 28px;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }
            
            .container {
                max-width: 100%;
                max-height: 95vh;
                border-radius: 28px;
            }
            
            .left, .right {
                padding: 32px 24px;
            }
            
            .form h2 {
                font-size: 26px;
            }
            
            .logo {
                width: 140px;
            }
            
            .btn {
                padding: 13px;
            }
            
            .input {
                padding: 12px 16px 12px 44px;
            }
        }

        @media (max-width: 480px) {
            .left, .right {
                padding: 28px 20px;
            }
            
            .form h2 {
                font-size: 24px;
            }
            
            .sub {
                margin-bottom: 24px;
            }
            
            .feature-list li {
                font-size: 13px;
            }
            
            .logo {
                width: 120px;
            }
        }

        /* LOADING STATE */
        .btn.loading {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            right: 20px;
            margin-top: -8px;
            border: 2px solid var(--gray-800);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }
        
        p {
            font-weight: 400;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- IZQUIERDA - BRANDING -->
        <div class="left">
            <div class="logo-container">
                <div class="logo-wrapper">
                    <img src="assets/logo.png" alt="Dental Gurú" class="logo" onerror="this.src='https://via.placeholder.com/220x220?text=🦷'">
                </div>
            </div>
            <h1>Sistema Clínico Dental</h1>
            <p class="description">Gestión inteligente para clínicas odontológicas modernas</p>
            <ul class="feature-list">
                <li><i class="fas fa-calendar-check"></i> Agenda inteligente</li>
                <li><i class="fas fa-file-medical"></i> Expedientes digitales</li>
                <li><i class="fas fa-chart-line"></i> Control administrativo</li>
            </ul>
        </div>

        <!-- DERECHA - FORMULARIOS -->
        <div class="right">
            <!-- FORMULARIO LOGIN -->
            <div id="loginBox" class="form">
                <h2>Bienvenido</h2>
                <p class="sub">Ingresa a tu cuenta</p>

                <?php if ($mensaje && $mensaje_tipo === "error" && $_POST["action"] !== "register"): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="correo" class="input" placeholder="Correo electrónico" required autocomplete="email">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="clave" class="input" placeholder="Contraseña" required autocomplete="current-password">
                        </div>
                    </div>

                    <button type="submit" class="btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                        Ingresar
                    </button>

                    <p style="margin-top: 28px; text-align: center; font-size: 14px;">
                        ¿No tienes cuenta?
                        <span class="link" onclick="toggleForms()">Crear cuenta</span>
                    </p>
                </form>
            </div>

            <!-- FORMULARIO REGISTRO -->
            <div id="registerBox" class="form" style="display: none;">
                <h2>Crear cuenta</h2>
                <p class="sub">Regístrate como paciente</p>

                <?php if ($mensaje && $mensaje_tipo === "error" && $_POST["action"] === "register"): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($mensaje); ?></span>
                </div>
                <?php endif; ?>

                <form id="registerForm" method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" name="nombre" class="input" placeholder="Nombre completo" required autocomplete="name">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="correo" class="input" placeholder="Correo electrónico" required autocomplete="email">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="clave" class="input" placeholder="Contraseña (mínimo 6 caracteres)" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-check-circle"></i>
                            <input type="password" name="confirmar_clave" class="input" placeholder="Confirmar contraseña" required autocomplete="off">
                        </div>
                    </div>

                    <button type="submit" class="btn" id="registerBtn">
                        <i class="fas fa-user-plus" style="margin-right: 8px;"></i>
                        Registrarse
                    </button>

                    <p style="margin-top: 28px; text-align: center; font-size: 14px;">
                        ¿Ya tienes cuenta?
                        <span class="link" onclick="toggleForms()">Iniciar sesión</span>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Elementos DOM
        const loginBox = document.getElementById('loginBox');
        const registerBox = document.getElementById('registerBox');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');

        // Función toggle entre formularios
        function toggleForms() {
            const isLoginVisible = loginBox.style.display !== 'none';
            
            if (isLoginVisible) {
                loginBox.style.display = 'none';
                registerBox.style.display = 'block';
                clearAlerts();
                clearErrors();
            } else {
                loginBox.style.display = 'block';
                registerBox.style.display = 'none';
                clearAlerts();
                clearErrors();
            }
        }

        // Limpiar alertas
        function clearAlerts() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
        }

        // Limpiar errores de inputs
        function clearErrors() {
            const errorInputs = document.querySelectorAll('.input.error');
            errorInputs.forEach(input => input.classList.remove('error'));
            const errorMessages = document.querySelectorAll('.error-message');
            errorMessages.forEach(msg => msg.remove());
        }

        // Validación en tiempo real para registro
        if (registerForm) {
            const nombreInput = registerForm.querySelector('input[name="nombre"]');
            const emailInput = registerForm.querySelector('input[name="correo"]');
            const passInput = registerForm.querySelector('input[name="clave"]');
            const confirmInput = registerForm.querySelector('input[name="confirmar_clave"]');

            function validateField(input, condition, message) {
                let errorDiv = input.parentElement.parentElement.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
                
                if (input.value.trim() !== "" && !condition) {
                    input.classList.add('error');
                    errorDiv = document.createElement('span');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = `<i class="fas fa-times-circle" style="font-size: 11px;"></i> ${message}`;
                    input.parentElement.parentElement.appendChild(errorDiv);
                    return false;
                } else {
                    input.classList.remove('error');
                    return true;
                }
            }

            nombreInput.addEventListener('blur', function() {
                validateField(this, this.value.trim().length >= 2, 'El nombre debe tener al menos 2 caracteres');
            });

            emailInput.addEventListener('blur', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                validateField(this, emailRegex.test(this.value.trim()), 'Ingrese un correo válido');
            });

            passInput.addEventListener('blur', function() {
                validateField(this, this.value.length >= 6, 'La contraseña debe tener mínimo 6 caracteres');
                if (confirmInput.value) {
                    validateField(confirmInput, confirmInput.value === passInput.value, 'Las contraseñas no coinciden');
                }
            });

            confirmInput.addEventListener('blur', function() {
                validateField(this, this.value === passInput.value, 'Las contraseñas no coinciden');
            });
        }

        // Validación en tiempo real para login
        if (loginForm) {
            const loginEmail = loginForm.querySelector('input[name="correo"]');
            const loginPass = loginForm.querySelector('input[name="clave"]');

            loginEmail.addEventListener('blur', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                let errorDiv = this.parentElement.parentElement.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
                
                if (this.value.trim() && !emailRegex.test(this.value.trim())) {
                    this.classList.add('error');
                    errorDiv = document.createElement('span');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = '<i class="fas fa-times-circle" style="font-size: 11px;"></i> Ingrese un correo válido';
                    this.parentElement.parentElement.appendChild(errorDiv);
                } else {
                    this.classList.remove('error');
                }
            });

            loginPass.addEventListener('blur', function() {
                let errorDiv = this.parentElement.parentElement.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
                
                if (this.value.trim() === "") {
                    this.classList.add('error');
                    errorDiv = document.createElement('span');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = '<i class="fas fa-times-circle" style="font-size: 11px;"></i> La contraseña es requerida';
                    this.parentElement.parentElement.appendChild(errorDiv);
                } else {
                    this.classList.remove('error');
                }
            });
        }

        // Prevenir envío doble y mostrar loading
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                if (btn.classList.contains('loading')) {
                    e.preventDefault();
                    return;
                }
                btn.classList.add('loading');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Ingresando...';
            });
        }

        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const nombre = this.querySelector('input[name="nombre"]').value.trim();
                const correo = this.querySelector('input[name="correo"]').value.trim();
                const clave = this.querySelector('input[name="clave"]').value;
                const confirmar = this.querySelector('input[name="confirmar_clave"]').value;
                
                if (nombre.length < 2) {
                    e.preventDefault();
                    showTemporaryAlert('El nombre debe tener al menos 2 caracteres', 'error');
                    return;
                }
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(correo)) {
                    e.preventDefault();
                    showTemporaryAlert('Ingrese un correo electrónico válido', 'error');
                    return;
                }
                
                if (clave.length < 6) {
                    e.preventDefault();
                    showTemporaryAlert('La contraseña debe tener mínimo 6 caracteres', 'error');
                    return;
                }
                
                if (clave !== confirmar) {
                    e.preventDefault();
                    showTemporaryAlert('Las contraseñas no coinciden', 'error');
                    return;
                }
                
                const btn = this.querySelector('button[type="submit"]');
                if (btn.classList.contains('loading')) {
                    e.preventDefault();
                    return;
                }
                btn.classList.add('loading');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Registrando...';
            });
        }

        function showTemporaryAlert(message, type) {
            clearAlerts();
            const form = document.querySelector('.form:not([style*="display: none"])');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i><span>${message}</span>`;
            form.insertBefore(alertDiv, form.firstChild);
            
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 300);
            }, 3500);
        }
    </script>
</body>
</html>