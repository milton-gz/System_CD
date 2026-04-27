-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-04-2026 a las 23:16:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `clinica`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cita`
--

CREATE TABLE `cita` (
  `id_cita` int(11) NOT NULL,
  `PACIENTE_id_paciente` int(11) DEFAULT NULL,
  `DOCTOR_id_doctor` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `tipo` enum('limpieza','revision','emergencia','otros') DEFAULT NULL,
  `estado` enum('pendiente','confirmada','atendida','cancelada') DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `doctor`
--

CREATE TABLE `doctor` (
  `id_doctor` int(11) NOT NULL,
  `USUARIO_id_usuario` int(11) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expediente`
--

CREATE TABLE `expediente` (
  `id_expediente` int(11) NOT NULL,
  `PACIENTE_id_paciente` int(11) DEFAULT NULL,
  `DOCTOR_id_doctor` int(11) DEFAULT NULL,
  `CITA_id_cita` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `diagnostico` text DEFAULT NULL,
  `tratamiento` text DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `estado` enum('activo','cerrado') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_cita`
--

CREATE TABLE `historial_cita` (
  `id_historial` int(11) NOT NULL,
  `CITA_id_cita` int(11) DEFAULT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente`
--

CREATE TABLE `paciente` (
  `id_paciente` int(11) NOT NULL,
  `USUARIO_id_usuario` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `sexo` varchar(10) DEFAULT NULL,
  `tipo_sangre` varchar(5) DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `enfermedades_previas` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `paciente`
--

INSERT INTO `paciente` (`id_paciente`, `USUARIO_id_usuario`, `telefono`, `direccion`, `edad`, `sexo`, `tipo_sangre`, `alergias`, `enfermedades_previas`, `fecha_registro`) VALUES
(1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-24 11:43:29'),
(2, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-27 15:09:45'),
(3, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-27 15:11:00'),
(4, 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-27 15:12:35'),
(5, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-27 15:14:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

CREATE TABLE `producto` (
  `id_producto` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `fecha_reposicion` date DEFAULT NULL,
  `estado` enum('disponible','bajo','agotado') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `receta`
--

CREATE TABLE `receta` (
  `id_receta` int(11) NOT NULL,
  `EXPEDIENTE_id_expediente` int(11) DEFAULT NULL,
  `medicamento` varchar(100) DEFAULT NULL,
  `dosis` varchar(100) DEFAULT NULL,
  `indicaciones` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recuperacion_password`
--

CREATE TABLE `recuperacion_password` (
  `id_recuperacion` int(11) NOT NULL,
  `USUARIO_id_usuario` int(11) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `fecha_expiracion` datetime DEFAULT NULL,
  `usado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `nombre`) VALUES
(1, 'admin'),
(2, 'doctor'),
(4, 'paciente'),
(3, 'recepcion');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--


CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `ROL_id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `ROL_id_rol`, `nombre`, `correo`, `password`, `estado`, `fecha_registro`, `ultimo_acceso`) VALUES
(1, 1, 'MARCOS ANTONIO QUINTANILLA VALLE', 'quintanillamarcos468@gmail.com', '$2y$10$q/P/ZT7afm.Dd/.5a14KEOxHyrJ7Urkwsb93vBd3kvJnZkbF62tfC', 'activo', '2026-04-24 11:43:29', '2026-04-27 15:14:14'),
(2, 4, 'Kevin Lemus', 'kaysitoboy@gmail.com', '$2y$10$ka9e.xjaDBBy.4RoiGlTw.NxeQGlOGy5WM6A3eS1CgXzmJnUnTMtm', 'activo', '2026-04-27 15:09:45', '2026-04-27 15:09:45'),
(3, 4, 'MILTON', 'milton@gmail.com', '$2y$10$weGmpPmK0fO4Fk/S8l5R0OwO8ushEIxAh6anGQyR.1Wtk1P0VT6LC', 'activo', '2026-04-27 15:11:00', '2026-04-27 15:11:00'),
(4, 4, 'shirley', 'shirley@gmail.com', '$2y$10$jAiB2uUxlE1OI9gQu/EpI.GZy8K6/E7f41ziTutbol31lJkvJewZS', 'activo', '2026-04-27 15:12:35', '2026-04-27 15:12:35'),
(5, 4, 'otto', 'otto@gmail.com', '$2y$10$q/.aUn4dObVIMGg6jPlGEuYzndzETidZJRt2eM8GTs.3dVKjvsgM.', 'activo', '2026-04-27 15:14:06', '2026-04-27 15:14:06');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cita`
--
ALTER TABLE `cita`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `PACIENTE_id_paciente` (`PACIENTE_id_paciente`),
  ADD KEY `DOCTOR_id_doctor` (`DOCTOR_id_doctor`);

--
-- Indices de la tabla `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`id_doctor`),
  ADD UNIQUE KEY `USUARIO_id_usuario` (`USUARIO_id_usuario`);

--
-- Indices de la tabla `expediente`
--
ALTER TABLE `expediente`
  ADD PRIMARY KEY (`id_expediente`),
  ADD KEY `PACIENTE_id_paciente` (`PACIENTE_id_paciente`),
  ADD KEY `DOCTOR_id_doctor` (`DOCTOR_id_doctor`),
  ADD KEY `CITA_id_cita` (`CITA_id_cita`);

--
-- Indices de la tabla `historial_cita`
--
ALTER TABLE `historial_cita`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `CITA_id_cita` (`CITA_id_cita`);

--
-- Indices de la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD PRIMARY KEY (`id_paciente`),
  ADD UNIQUE KEY `USUARIO_id_usuario` (`USUARIO_id_usuario`);

--
-- Indices de la tabla `producto`
--
ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `receta`
--
ALTER TABLE `receta`
  ADD PRIMARY KEY (`id_receta`),
  ADD KEY `EXPEDIENTE_id_expediente` (`EXPEDIENTE_id_expediente`);

--
-- Indices de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD PRIMARY KEY (`id_recuperacion`),
  ADD KEY `USUARIO_id_usuario` (`USUARIO_id_usuario`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `ROL_id_rol` (`ROL_id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cita`
--
ALTER TABLE `cita`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `doctor`
--
ALTER TABLE `doctor`
  MODIFY `id_doctor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `expediente`
--
ALTER TABLE `expediente`
  MODIFY `id_expediente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_cita`
--
ALTER TABLE `historial_cita`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paciente`
--
ALTER TABLE `paciente`
  MODIFY `id_paciente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `producto`
--
ALTER TABLE `producto`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `receta`
--
ALTER TABLE `receta`
  MODIFY `id_receta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  MODIFY `id_recuperacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cita`
--
ALTER TABLE `cita`
  ADD CONSTRAINT `cita_ibfk_1` FOREIGN KEY (`PACIENTE_id_paciente`) REFERENCES `paciente` (`id_paciente`) ON DELETE CASCADE,
  ADD CONSTRAINT `cita_ibfk_2` FOREIGN KEY (`DOCTOR_id_doctor`) REFERENCES `doctor` (`id_doctor`) ON DELETE SET NULL;

--
-- Filtros para la tabla `doctor`
--
ALTER TABLE `doctor`
  ADD CONSTRAINT `doctor_ibfk_1` FOREIGN KEY (`USUARIO_id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `expediente`
--
ALTER TABLE `expediente`
  ADD CONSTRAINT `expediente_ibfk_1` FOREIGN KEY (`PACIENTE_id_paciente`) REFERENCES `paciente` (`id_paciente`) ON DELETE CASCADE,
  ADD CONSTRAINT `expediente_ibfk_2` FOREIGN KEY (`DOCTOR_id_doctor`) REFERENCES `doctor` (`id_doctor`) ON DELETE SET NULL,
  ADD CONSTRAINT `expediente_ibfk_3` FOREIGN KEY (`CITA_id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE SET NULL;

--
-- Filtros para la tabla `historial_cita`
--
ALTER TABLE `historial_cita`
  ADD CONSTRAINT `historial_cita_ibfk_1` FOREIGN KEY (`CITA_id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE;

--
-- Filtros para la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD CONSTRAINT `paciente_ibfk_1` FOREIGN KEY (`USUARIO_id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `receta`
--
ALTER TABLE `receta`
  ADD CONSTRAINT `receta_ibfk_1` FOREIGN KEY (`EXPEDIENTE_id_expediente`) REFERENCES `expediente` (`id_expediente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD CONSTRAINT `recuperacion_password_ibfk_1` FOREIGN KEY (`USUARIO_id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`ROL_id_rol`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
