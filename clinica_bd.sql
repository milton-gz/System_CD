CREATE DATABASE Clinica;
USE Clinica;

-- =========================
-- ROLES
-- =========================
CREATE TABLE ROL (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO ROL (nombre) VALUES
('admin'),
('doctor'),
('recepcion'),
('paciente');

-- =========================
-- USUARIO
-- =========================
CREATE TABLE USUARIO (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    
    ROL_id_rol INT NOT NULL,
    
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME,

    FOREIGN KEY (ROL_id_rol) REFERENCES ROL(id_rol)
    ON DELETE CASCADE ON UPDATE CASCADE
);

-- =========================
-- RECUPERACIÓN
-- =========================
CREATE TABLE RECUPERACION_PASSWORD (
    id_recuperacion INT AUTO_INCREMENT PRIMARY KEY,
    
    USUARIO_id_usuario INT,
    
    token VARCHAR(255),
    fecha_expiracion DATETIME,
    usado BOOLEAN DEFAULT FALSE,

    FOREIGN KEY (USUARIO_id_usuario) REFERENCES USUARIO(id_usuario)
    ON DELETE CASCADE
);

-- =========================
-- PACIENTE
-- =========================
CREATE TABLE PACIENTE (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    
    USUARIO_id_usuario INT UNIQUE,
    
    telefono VARCHAR(20),
    direccion TEXT,
    edad INT,
    sexo VARCHAR(10),
    tipo_sangre VARCHAR(5),
    
    alergias TEXT,
    enfermedades_previas TEXT,

    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (USUARIO_id_usuario) REFERENCES USUARIO(id_usuario)
    ON DELETE CASCADE
);

-- =========================
-- DOCTOR
-- =========================
CREATE TABLE DOCTOR (
    id_doctor INT AUTO_INCREMENT PRIMARY KEY,
    
    USUARIO_id_usuario INT UNIQUE,
    
    especialidad VARCHAR(100),
    telefono VARCHAR(20),
    estado ENUM('activo','inactivo') DEFAULT 'activo',

    FOREIGN KEY (USUARIO_id_usuario) REFERENCES USUARIO(id_usuario)
    ON DELETE CASCADE
);

-- =========================
-- CITAS
-- =========================
CREATE TABLE CITA (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    
    PACIENTE_id_paciente INT,
    DOCTOR_id_doctor INT,
    
    fecha DATE,
    hora TIME,
    
    tipo ENUM('limpieza','revision','emergencia','otros'),
    estado ENUM('pendiente','confirmada','atendida','cancelada'),

    observaciones TEXT,

    FOREIGN KEY (PACIENTE_id_paciente) REFERENCES PACIENTE(id_paciente)
    ON DELETE CASCADE,

    FOREIGN KEY (DOCTOR_id_doctor) REFERENCES DOCTOR(id_doctor)
    ON DELETE SET NULL
);

-- =========================
-- EXPEDIENTE (CLAVE)
-- =========================
CREATE TABLE EXPEDIENTE (
    id_expediente INT AUTO_INCREMENT PRIMARY KEY,
    
    PACIENTE_id_paciente INT,
    DOCTOR_id_doctor INT,
    CITA_id_cita INT,
    
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    diagnostico TEXT,
    tratamiento TEXT,
    notas TEXT,

    estado ENUM('activo','cerrado') DEFAULT 'activo',

    FOREIGN KEY (PACIENTE_id_paciente) REFERENCES PACIENTE(id_paciente)
    ON DELETE CASCADE,

    FOREIGN KEY (DOCTOR_id_doctor) REFERENCES DOCTOR(id_doctor)
    ON DELETE SET NULL,

    FOREIGN KEY (CITA_id_cita) REFERENCES CITA(id_cita)
    ON DELETE SET NULL
);

-- =========================
-- RECETA
-- =========================
CREATE TABLE RECETA (
    id_receta INT AUTO_INCREMENT PRIMARY KEY,
    
    EXPEDIENTE_id_expediente INT,
    
    medicamento VARCHAR(100),
    dosis VARCHAR(100),
    indicaciones TEXT,
    
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (EXPEDIENTE_id_expediente) REFERENCES EXPEDIENTE(id_expediente)
    ON DELETE CASCADE
);

-- =========================
-- HISTORIAL
-- =========================
CREATE TABLE HISTORIAL_CITA (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    
    CITA_id_cita INT,
    
    estado_anterior VARCHAR(50),
    estado_nuevo VARCHAR(50),
    
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (CITA_id_cita) REFERENCES CITA(id_cita)
    ON DELETE CASCADE
);

-- =========================
-- INVENTARIO
-- =========================
CREATE TABLE PRODUCTO (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    
    nombre VARCHAR(100),
    categoria VARCHAR(100),
    
    stock INT,
    fecha_reposicion DATE,
    
    estado ENUM('disponible','bajo','agotado')
);

-- =========================
-- USUARIO PACIENTE INICIAL
-- La clave 1111 se guarda temporalmente para importar la BD sin depender de PHP CLI.
-- login.php la convierte automaticamente a password_hash() al primer acceso.
-- =========================
INSERT INTO USUARIO (ROL_id_rol, nombre, correo, password, estado)
VALUES (4, 'Leandro Lemus', 'leandrolemus800@gmail.con', '1111', 'activo');

INSERT INTO PACIENTE (USUARIO_id_usuario)
VALUES (LAST_INSERT_ID());

INSERT INTO USUARIO (ROL_id_rol, nombre, correo, password, estado)
VALUES
(1, 'Sofia Herrera', 'sofia.admin@dentalguru.test', '$2y$10$T9OlMWgQjBH66becv84Lb.eCYF4l5jac9oCsmzQO9CWX7GbmM8FGa', 'activo'),
(2, 'Dr. Mateo Rivas', 'mateo.doctor@dentalguru.test', '$2y$10$5U8AC/bbGGrHMsQGhF9IVOaS1xnrtBkhY.cHlZcSrYLKwhI8xPXbG', 'activo');

INSERT INTO DOCTOR (USUARIO_id_usuario, especialidad, telefono, estado)
SELECT id_usuario, 'Ortodoncia', '7777-2026', 'activo'
FROM USUARIO
WHERE correo = 'mateo.doctor@dentalguru.test';

select * from usuario
