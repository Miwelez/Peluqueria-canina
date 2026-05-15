-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2026 at 10:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.5.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peluqueria-canina`
--

-- --------------------------------------------------------

--
-- Table structure for table `centros`
--

DROP TABLE IF EXISTS `centros`;
CREATE TABLE IF NOT EXISTS `centros` (
  `centro_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_centro` varchar(150) NOT NULL,
  `telefono_centro` varchar(20) DEFAULT NULL,
  `localizacion_centro` varchar(150) DEFAULT NULL,
  `empleador_id` int(10) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`centro_id`),
  KEY `idx_centros_empleador` (`empleador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `centros`
--

INSERT INTO `centros` (`centro_id`, `nombre_centro`, `telefono_centro`, `localizacion_centro`, `empleador_id`, `activo`, `dia_creado`) VALUES
(1, 'Peluquería Canina El Bigote', '933111222', 'Calle Mayor 10, Madrid', 1, 1, '2026-05-14 16:30:12');

-- --------------------------------------------------------

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
CREATE TABLE IF NOT EXISTS `citas` (
  `cita_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `centro_id` int(10) UNSIGNED NOT NULL,
  `perro_id` int(10) UNSIGNED NOT NULL,
  `servicio_id` int(10) UNSIGNED NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `duracion_real` smallint(5) UNSIGNED DEFAULT NULL,
  `estado` enum('pendiente','confirmada','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cita_id`),
  KEY `idx_citas_usuario` (`usuario_id`),
  KEY `idx_citas_centro` (`centro_id`),
  KEY `idx_citas_perro` (`perro_id`),
  KEY `idx_citas_servicio` (`servicio_id`),
  KEY `idx_citas_fecha` (`fecha_hora`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `citas`
--

INSERT INTO `citas` (`cita_id`, `usuario_id`, `centro_id`, `perro_id`, `servicio_id`, `fecha_hora`, `duracion_real`, `estado`, `notas`, `dia_creado`) VALUES
(1, 2, 1, 1, 3, '2026-05-20 10:00:00', 70, 'pendiente', NULL, '2026-05-14 16:30:12'),
(2, 2, 1, 1, 3, '2026-05-15 10:15:00', 70, 'pendiente', NULL, '2026-05-14 19:36:45'),
(3, 1, 1, 1, 2, '2026-05-16 11:30:00', 65, 'pendiente', NULL, '2026-05-14 19:36:58'),
(4, 2, 1, 2, 3, '2026-05-16 12:45:00', 90, 'pendiente', NULL, '2026-05-15 10:00:00');

--
-- Triggers `citas`
--
DROP TRIGGER IF EXISTS `trg_insertar_duracion_en_citas`;
DELIMITER $$
CREATE TRIGGER `trg_insertar_duracion_en_citas` BEFORE INSERT ON `citas` FOR EACH ROW BEGIN
  DECLARE v_duracion SMALLINT UNSIGNED DEFAULT NULL;
  IF NEW.duracion_real IS NULL THEN
    SELECT ds.duracion INTO v_duracion
    FROM duracion_servicio ds
    INNER JOIN perros p ON p.raza_id = ds.raza_id
    WHERE p.perro_id = NEW.perro_id
      AND ds.servicio_id = NEW.servicio_id
    LIMIT 1;
    SET NEW.duracion_real = v_duracion;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `cliente_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_cliente` varchar(100) NOT NULL,
  `telefono_cliente` varchar(20) NOT NULL,
  `centro_id` int(10) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cliente_id`),
  KEY `idx_clientes_centro` (`centro_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`cliente_id`, `nombre_cliente`, `telefono_cliente`, `centro_id`, `activo`, `dia_creado`) VALUES
(1, 'María Fernández', '644555666', 1, 1, '2026-05-14 16:30:12'),
(2, 'Jesus Martinez', '12312412', 1, 1, '2026-05-15 09:59:08');

-- --------------------------------------------------------

--
-- Table structure for table `duracion_servicio`
--

DROP TABLE IF EXISTS `duracion_servicio`;
CREATE TABLE IF NOT EXISTS `duracion_servicio` (
  `duracion_servicio_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `raza_id` int(10) UNSIGNED NOT NULL,
  `servicio_id` int(10) UNSIGNED NOT NULL,
  `duracion` smallint(5) UNSIGNED NOT NULL,
  PRIMARY KEY (`duracion_servicio_id`),
  UNIQUE KEY `uq_raza_servicio` (`raza_id`,`servicio_id`),
  KEY `idx_ds_raza` (`raza_id`),
  KEY `idx_ds_servicio` (`servicio_id`)
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `duracion_servicio`
--

INSERT INTO `duracion_servicio` (`duracion_servicio_id`, `raza_id`, `servicio_id`, `duracion`) VALUES
(1, 1, 1, 45),
(2, 1, 2, 30),
(3, 1, 3, 60),
(4, 1, 4, 75),
(5, 1, 5, 45),
(6, 2, 1, 30),
(7, 2, 2, 20),
(8, 2, 3, 30),
(9, 2, 4, 45),
(10, 2, 5, 20),
(11, 3, 1, 45),
(12, 3, 2, 30),
(13, 3, 3, 60),
(14, 3, 4, 60),
(15, 3, 5, 60),
(16, 4, 1, 50),
(17, 4, 2, 35),
(18, 4, 3, 70),
(19, 4, 4, 70),
(20, 4, 5, 50),
(21, 5, 1, 50),
(22, 5, 2, 35),
(23, 5, 3, 70),
(24, 5, 4, 75),
(25, 5, 5, 50),
(26, 6, 1, 50),
(27, 6, 2, 35),
(28, 6, 3, 65),
(29, 6, 4, 70),
(30, 6, 5, 50),
(31, 7, 1, 50),
(32, 7, 2, 40),
(33, 7, 3, 60),
(34, 7, 4, 90),
(35, 7, 5, 45),
(36, 8, 1, 55),
(37, 8, 2, 35),
(38, 8, 3, 70),
(39, 8, 4, 70),
(40, 8, 5, 55),
(41, 9, 1, 35),
(42, 9, 2, 25),
(43, 9, 3, 35),
(44, 9, 4, 45),
(45, 9, 5, 25),
(46, 10, 1, 50),
(47, 10, 2, 35),
(48, 10, 3, 65),
(49, 10, 4, 65),
(50, 10, 5, 50),
(51, 11, 1, 60),
(52, 11, 2, 45),
(53, 11, 3, 75),
(54, 11, 4, 80),
(55, 11, 5, 60),
(56, 12, 1, 60),
(57, 12, 2, 45),
(58, 12, 3, 75),
(59, 12, 4, 80),
(60, 12, 5, 75),
(61, 13, 1, 45),
(62, 13, 2, 30),
(63, 13, 3, 45),
(64, 13, 4, 60),
(65, 13, 5, 30),
(66, 14, 1, 60),
(67, 14, 2, 45),
(68, 14, 3, 75),
(69, 14, 4, 90),
(70, 14, 5, 60),
(71, 15, 1, 75),
(72, 15, 2, 55),
(73, 15, 3, 80),
(74, 15, 4, 90),
(75, 15, 5, 90),
(76, 16, 1, 65),
(77, 16, 2, 50),
(78, 16, 3, 75),
(79, 16, 4, 80),
(80, 16, 5, 75),
(81, 17, 1, 75),
(82, 17, 2, 50),
(83, 17, 3, 60),
(84, 17, 4, 90),
(85, 17, 5, 45),
(86, 18, 1, 90),
(87, 18, 2, 60),
(88, 18, 3, 90),
(89, 18, 4, 105),
(90, 18, 5, 90),
(91, 19, 1, 80),
(92, 19, 2, 55),
(93, 19, 3, 75),
(94, 19, 4, 90),
(95, 19, 5, 90),
(96, 20, 1, 80),
(97, 20, 2, 55),
(98, 20, 3, 75),
(99, 20, 4, 90),
(100, 20, 5, 105),
(101, 21, 1, 80),
(102, 21, 2, 60),
(103, 21, 3, 80),
(104, 21, 4, 120),
(105, 21, 5, 75),
(106, 22, 1, 105),
(107, 22, 2, 70),
(108, 22, 3, 105),
(109, 22, 4, 120),
(110, 22, 5, 120),
(111, 23, 1, 90),
(112, 23, 2, 65),
(113, 23, 3, 85),
(114, 23, 4, 105),
(115, 23, 5, 120),
(116, 24, 1, 120),
(117, 24, 2, 80),
(118, 24, 3, 120),
(119, 24, 4, 150),
(120, 24, 5, 120),
(121, 25, 1, 120),
(122, 25, 2, 80),
(123, 25, 3, 120),
(124, 25, 4, 150),
(125, 25, 5, 135),
(126, 26, 1, 90),
(127, 26, 2, 65),
(128, 26, 3, 105),
(129, 26, 4, 120),
(130, 26, 5, 90);

-- --------------------------------------------------------

--
-- Table structure for table `perros`
--

DROP TABLE IF EXISTS `perros`;
CREATE TABLE IF NOT EXISTS `perros` (
  `perro_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_perro` varchar(100) NOT NULL,
  `raza_id` int(10) UNSIGNED NOT NULL,
  `cliente_id` int(10) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`perro_id`),
  KEY `idx_perros_raza` (`raza_id`),
  KEY `idx_perros_cliente` (`cliente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `perros`
--

INSERT INTO `perros` (`perro_id`, `nombre_perro`, `raza_id`, `cliente_id`, `activo`, `dia_creado`) VALUES
(1, 'Tobi', 4, 1, 1, '2026-05-14 16:30:12'),
(2, 'Vini', 3, 2, 1, '2026-05-15 09:59:19');

-- --------------------------------------------------------

--
-- Table structure for table `razas`
--

DROP TABLE IF EXISTS `razas`;
CREATE TABLE IF NOT EXISTS `razas` (
  `raza_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_raza` varchar(100) NOT NULL,
  `tamano_raza` enum('mini','pequenio','mediano','grande','gigante') NOT NULL,
  `tipo_pelo_raza` enum('corto','medio','largo','rizado','duro') NOT NULL,
  PRIMARY KEY (`raza_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `razas`
--

INSERT INTO `razas` (`raza_id`, `nombre_raza`, `tamano_raza`, `tipo_pelo_raza`) VALUES
(1, 'Caniche Toy', 'mini', 'rizado'),
(2, 'Chihuahua', 'mini', 'corto'),
(3, 'Pomerania', 'mini', 'largo'),
(4, 'Yorkshire Terrier', 'pequenio', 'largo'),
(5, 'Bichon Frise', 'pequenio', 'rizado'),
(6, 'Maltes', 'pequenio', 'largo'),
(7, 'Schnauzer Miniatura', 'pequenio', 'duro'),
(8, 'Shih Tzu', 'pequenio', 'largo'),
(9, 'Bulldog Frances', 'pequenio', 'corto'),
(10, 'Cavalier King Charles', 'pequenio', 'largo'),
(11, 'Cocker Spaniel', 'mediano', 'largo'),
(12, 'Border Collie', 'mediano', 'largo'),
(13, 'Beagle', 'mediano', 'corto'),
(14, 'Caniche Mediano', 'mediano', 'rizado'),
(15, 'Chow Chow', 'mediano', 'largo'),
(16, 'Spitz Aleman', 'mediano', 'largo'),
(17, 'Labrador', 'grande', 'corto'),
(18, 'Golden Retriever', 'grande', 'largo'),
(19, 'Pastor Aleman', 'grande', 'medio'),
(20, 'Husky Siberiano', 'grande', 'medio'),
(21, 'Schnauzer Gigante', 'grande', 'duro'),
(22, 'Samoyedo', 'grande', 'largo'),
(23, 'Malamute de Alaska', 'grande', 'medio'),
(24, 'San Bernardo', 'gigante', 'largo'),
(25, 'Gran Pirineo', 'gigante', 'largo'),
(26, 'Caniche Gigante', 'gigante', 'rizado');

-- --------------------------------------------------------

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
CREATE TABLE IF NOT EXISTS `servicios` (
  `servicio_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_servicio` enum('banio','rapar','corte','stripping','deslanado') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`servicio_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `servicios`
--

INSERT INTO `servicios` (`servicio_id`, `nombre_servicio`, `activo`) VALUES
(1, 'banio', 1),
(2, 'rapar', 1),
(3, 'corte', 1),
(4, 'stripping', 1),
(5, 'deslanado', 1);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `usuario_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(150) NOT NULL,
  `email_usuario` varchar(150) NOT NULL,
  `password_usuario` varchar(255) NOT NULL,
  `telefono_usuario` varchar(20) NOT NULL,
  `rol_usuario` enum('empleador','empleado') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`),
  UNIQUE KEY `uq_email_usuario` (`email_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`usuario_id`, `nombre_usuario`, `email_usuario`, `password_usuario`, `telefono_usuario`, `rol_usuario`, `activo`, `dia_creado`) VALUES
(1, 'Ana García', 'ana@ejemplo.com', '$2y$12$HDUZCdLznkJD60a7EMUSY.fVpLXEZcaJJYrZlOSG104Yw/q/1Ixg.', '611222333', 'empleador', 1, '2026-05-14 16:30:11'),
(2, 'Carlos López', 'carlos@ejemplo.com', '$2y$12$HDUZCdLznkJD60a7EMUSY.fVpLXEZcaJJYrZlOSG104Yw/q/1Ixg.', '622333444', 'empleado', 1, '2026-05-14 16:30:11');

-- --------------------------------------------------------

--
-- Table structure for table `usuario_centro`
--

DROP TABLE IF EXISTS `usuario_centro`;
CREATE TABLE IF NOT EXISTS `usuario_centro` (
  `usuario_centro_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `centro_id` int(10) UNSIGNED NOT NULL,
  `dia_creado` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_centro_id`),
  UNIQUE KEY `uq_usuario_centro` (`usuario_id`,`centro_id`),
  KEY `idx_uc_usuario` (`usuario_id`),
  KEY `idx_uc_centro` (`centro_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Dumping data for table `usuario_centro`
--

INSERT INTO `usuario_centro` (`usuario_centro_id`, `usuario_id`, `centro_id`, `dia_creado`) VALUES
(1, 2, 1, '2026-05-14 16:30:12');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_citas_calendario`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vista_citas_calendario`;
CREATE TABLE IF NOT EXISTS `vista_citas_calendario` (
`cita_id` int(10) unsigned
,`fecha_hora` datetime
,`duracion_real` smallint(5) unsigned
,`estado` enum('pendiente','confirmada','completada','cancelada')
,`notas` text
,`usuario_id` int(10) unsigned
,`nombre_usuario` varchar(150)
,`centro_id` int(10) unsigned
,`nombre_centro` varchar(150)
,`perro_id` int(10) unsigned
,`nombre_perro` varchar(100)
,`raza_id` int(10) unsigned
,`nombre_raza` varchar(100)
,`servicio_id` int(10) unsigned
,`nombre_servicio` enum('banio','rapar','corte','stripping','deslanado')
,`cliente_id` int(10) unsigned
,`nombre_cliente` varchar(100)
,`telefono_cliente` varchar(20)
);

-- --------------------------------------------------------

--
-- Structure for view `vista_citas_calendario`
--
DROP TABLE IF EXISTS `vista_citas_calendario`;

DROP VIEW IF EXISTS `vista_citas_calendario`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_citas_calendario`  AS SELECT `c`.`cita_id` AS `cita_id`, `c`.`fecha_hora` AS `fecha_hora`, `c`.`duracion_real` AS `duracion_real`, `c`.`estado` AS `estado`, `c`.`notas` AS `notas`, `c`.`usuario_id` AS `usuario_id`, `u`.`nombre_usuario` AS `nombre_usuario`, `c`.`centro_id` AS `centro_id`, `ct`.`nombre_centro` AS `nombre_centro`, `c`.`perro_id` AS `perro_id`, `p`.`nombre_perro` AS `nombre_perro`, `r`.`raza_id` AS `raza_id`, `r`.`nombre_raza` AS `nombre_raza`, `c`.`servicio_id` AS `servicio_id`, `s`.`nombre_servicio` AS `nombre_servicio`, `cl`.`cliente_id` AS `cliente_id`, `cl`.`nombre_cliente` AS `nombre_cliente`, `cl`.`telefono_cliente` AS `telefono_cliente` FROM ((((((`citas` `c` join `usuarios` `u` on(`u`.`usuario_id` = `c`.`usuario_id`)) join `centros` `ct` on(`ct`.`centro_id` = `c`.`centro_id`)) join `servicios` `s` on(`s`.`servicio_id` = `c`.`servicio_id`)) join `perros` `p` on(`p`.`perro_id` = `c`.`perro_id`)) join `razas` `r` on(`r`.`raza_id` = `p`.`raza_id`)) join `clientes` `cl` on(`cl`.`cliente_id` = `p`.`cliente_id`)) WHERE `u`.`activo` = 1 AND `ct`.`activo` = 1 AND `p`.`activo` = 1 AND `cl`.`activo` = 1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `centros`
--
ALTER TABLE `centros`
  ADD CONSTRAINT `fk_centros_empleador` FOREIGN KEY (`empleador_id`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Constraints for table `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `fk_citas_centro` FOREIGN KEY (`centro_id`) REFERENCES `centros` (`centro_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_perro` FOREIGN KEY (`perro_id`) REFERENCES `perros` (`perro_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`servicio_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_citas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON UPDATE CASCADE;

--
-- Constraints for table `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_centro` FOREIGN KEY (`centro_id`) REFERENCES `centros` (`centro_id`) ON UPDATE CASCADE;

--
-- Constraints for table `duracion_servicio`
--
ALTER TABLE `duracion_servicio`
  ADD CONSTRAINT `fk_ds_raza` FOREIGN KEY (`raza_id`) REFERENCES `razas` (`raza_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ds_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`servicio_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `perros`
--
ALTER TABLE `perros`
  ADD CONSTRAINT `fk_perros_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`cliente_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perros_raza` FOREIGN KEY (`raza_id`) REFERENCES `razas` (`raza_id`) ON UPDATE CASCADE;

--
-- Constraints for table `usuario_centro`
--
ALTER TABLE `usuario_centro`
  ADD CONSTRAINT `fk_uc_centro` FOREIGN KEY (`centro_id`) REFERENCES `centros` (`centro_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
