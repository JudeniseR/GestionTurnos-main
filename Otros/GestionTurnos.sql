-- ----------------------------------------------------------------------------------------------------
-- ESTRUCTURA ACTUALIZADA 16/09
-- ----------------------------------------------------------------------------------------------------

CREATE DATABASE gestionturnos;

USE gestionturnos;

-- Script unificado: CREATE TABLEs con índices, AUTO_INCREMENT y FOREIGN KEYS inline
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=5;

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `genero` varchar(50) DEFAULT NULL, -- DESPUES CAMBIAR A NOT NULL
  `img_dni` TEXT DEFAULT NULL,  -- DESPUES CAMBIAR A NOT NULL
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `id_rol` (`id_rol`),
  CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=4;



CREATE TABLE `sedes` (
  `id_sede` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sede`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `recursos` (
  `id_recurso` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) DEFAULT NULL,
  `tipo` enum('medico','tecnico','equipo') DEFAULT NULL,
  `id_sede` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_recurso`),
  KEY `id_sede` (`id_sede`),
  CONSTRAINT `recursos_ibfk_1` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `especialidades` (
  `id_especialidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_especialidad` varchar(100) NOT NULL,
  PRIMARY KEY (`id_especialidad`),
  UNIQUE KEY `nombre_especialidad` (`nombre_especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `estudios` (
  `id_estudio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `requiere_preparacion` tinyint(1) DEFAULT 0,
  `instrucciones` text DEFAULT NULL,
  PRIMARY KEY (`id_estudio`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `requisitos_estudio` (
  `id_requisito` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudio` int(11) NOT NULL,
  `tipo_requisito` varchar(150) NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`id_requisito`),
  KEY `id_estudio` (`id_estudio`),
  CONSTRAINT `requisitos_estudio_ibfk_1` FOREIGN KEY (`id_estudio`) REFERENCES `estudios` (`id_estudio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_estado` varchar(100) NOT NULL,
  PRIMARY KEY (`id_estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `afiliados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_documento` varchar(20) NOT NULL,
  `numero_afiliado` varchar(30) NOT NULL,
  `cobertura_salud` enum('UOM','OSDE','Swiss Medical','Galeno','Otra') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `tipo_beneficiario` enum('titular','conyuge','conviviente','hijo menor','hijo mayor') NOT NULL,
  `cursa_estudios` tinyint(1) DEFAULT 0,
  `seccional` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pacientes` (
  `id_paciente` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
  `nro_documento` varchar(50) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `token_qr` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_paciente`),
  UNIQUE KEY `nro_documento` (`nro_documento`),
  UNIQUE KEY `token_qr` (`token_qr`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2;

CREATE TABLE `medicos` (
  `id_medico` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `matricula` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_medico`),
  UNIQUE KEY `matricula` (`matricula`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `medicos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2;

CREATE TABLE `medico_especialidad` (
  `id_medico` int(11) NOT NULL,
  `id_especialidad` int(11) NOT NULL,
  PRIMARY KEY (`id_medico`,`id_especialidad`),
  KEY `id_especialidad` (`id_especialidad`),
  CONSTRAINT `medico_especialidad_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  CONSTRAINT `medico_especialidad_ibfk_2` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id_especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `agenda` (
  `id_agenda` int(11) NOT NULL AUTO_INCREMENT,
  `id_medico` int(11) DEFAULT NULL,
  `id_recurso` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_agenda`),
  KEY `id_medico` (`id_medico`),
  KEY `id_recurso` (`id_recurso`),
  CONSTRAINT `agenda_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  CONSTRAINT `agenda_ibfk_2` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `administrador` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `administrador_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `credencial_virtual` (
  `id_credencial` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) DEFAULT NULL,
  `codigo_qr` varchar(255) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  PRIMARY KEY (`id_credencial`),
  KEY `id_paciente` (`id_paciente`),
  CONSTRAINT `credencial_virtual_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `observaciones` (
  `id_observacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_turno` int(11) DEFAULT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `nota` text DEFAULT NULL,
  PRIMARY KEY (`id_observacion`),
  KEY `id_turno` (`id_turno`),
  KEY `id_paciente` (`id_paciente`)
  -- FK to turnos and pacientes added after turnos definition
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ordenes_estudio` (
  `id_orden` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `id_estudio` int(11) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `estado` enum('pendiente','validada','rechazada') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `archivo_orden` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_orden`),
  KEY `id_paciente` (`id_paciente`),
  KEY `id_medico` (`id_medico`),
  KEY `id_estudio` (`id_estudio`),
  CONSTRAINT `ordenes_estudio_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`),
  CONSTRAINT `ordenes_estudio_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  CONSTRAINT `ordenes_estudio_ibfk_3` FOREIGN KEY (`id_estudio`) REFERENCES `estudios` (`id_estudio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_turno` int(11) DEFAULT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `id_turno` (`id_turno`),
  KEY `id_paciente` (`id_paciente`)
  -- FK to turnos & pacientes added after turnos definition
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_reporte`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `recuperacion_password` (
  `id_recuperacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `fecha_expiracion` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_recuperacion`),
  UNIQUE KEY `token` (`token`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `recuperacion_password_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tecnico` (
  `id_tecnico` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  PRIMARY KEY (`id_tecnico`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  UNIQUE KEY `id_rol` (`id_rol`),
  CONSTRAINT `tecnico_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `tecnico_ibfk_2` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `turnos` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `id_estado` int(11) DEFAULT NULL,
  `id_estudio` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `copago` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_turno`),
  KEY `id_paciente` (`id_paciente`),
  KEY `id_medico` (`id_medico`),
  KEY `id_estado` (`id_estado`),
  CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`),
  CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  CONSTRAINT `turnos_ibfk_3` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`),
  CONSTRAINT `turnos_ibfk_4` FOREIGN KEY (`id_estudio`) REFERENCES `estudios` (`id_estudio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Now add the remaining FK constraints that referenced turnos/pacientes created earlier
ALTER TABLE `observaciones`
  ADD CONSTRAINT `observaciones_ibfk_1` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`),
  ADD CONSTRAINT `observaciones_ibfk_2` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`);

ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`),
  ADD CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`);

-- Datos de ejemplo (volcados originales)
INSERT INTO `medicos` (`id_medico`, `id_usuario`, `matricula`, `telefono`) VALUES
(1, 3, '111222333', '1122000022');

INSERT INTO `pacientes` (`id_paciente`, `id_usuario`, `tipo_documento`, `nro_documento`, `fecha_nacimiento`, `direccion`, `telefono`, `email`, `estado_civil`, `token_qr`) VALUES
(1, 2, 'DNI', '23111222', '0000-00-00', 'corrientes 1000', '1133000000', 'juanperez@gmail.com', 'casado', NULL);

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(3, 'Administrador'),
(2, 'Medico'),
(1, 'Paciente'),
(4, 'tecnico');

INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `email`, `password_hash`, `id_rol`, `activo`, `fecha_creacion`) VALUES
(1, 'juli', 'rojas', 'julietadeniserojas@gmail.com', '$2y$10$SIG/LtPWZZhm3dpgWHdBzOflhIne5dSn0ov/qMCMvZ1TIzM5/wwaK', 3, 1, '2025-09-17 03:50:19'),
(2, 'juan ', 'perez', 'juanperez@gmail.com', '$2y$10$DKgNJRkq.cRqVDphZmJ1hOWLJ3gem4zsXyf3kjntzudChurfybG2m', 1, 1, '2025-09-17 04:05:11'),
(3, 'Maria', 'Paz', 'mariapaz@gmail.com', '$2y$10$wKLIgLpEBpbOD37BuxN34.3vicTOPfZztKCTibcy3nG/YEFGb5tjO', 2, 1, '2025-09-17 04:15:04');

-- Ajustes AUTO_INCREMENTs según el volcado original
ALTER TABLE `medicos` AUTO_INCREMENT=2;
ALTER TABLE `pacientes` AUTO_INCREMENT=2;
ALTER TABLE `roles` AUTO_INCREMENT=5;
ALTER TABLE `usuario` AUTO_INCREMENT=4;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;



----------------------------------------------------------------------------------------------------------
-- FECHA: 21/09
----------------------------------------------------------------------------------------------------------

USE gestionturnos;

INSERT INTO afiliados
(id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES(1, '11222333', '22123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');

INSERT INTO afiliados
(id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES(2, '11222334', '23123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');

--------------
-- PRUEBAS
--------------

INSERT INTO usuario (id_usuario, nombre, apellido, email, password_hash, id_rol, activo, fecha_creacion) VALUES
(5, 'Juan', 'Perez', 'jp@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-21 18:00:00'), -- CONTRASEÑA = 123456
(6, 'Luciana', 'Martinez', 'lm@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-21 18:00:00'); -- CONTRASEÑA = 123456

INSERT INTO medicos (id_medico, id_usuario, matricula, telefono) VALUES
(2, 5, 'MAT123', '123456789'),
(3, 6, 'MAT124', '123456789');

INSERT INTO especialidades (id_especialidad, nombre_especialidad) VALUES
(1, 'Cardiología'),
(2, 'Pediatría'),
(3, 'Dermatología');

INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
(2, 1),
(2, 2),
(3, 3); -- Médico con dos especialidades

INSERT INTO sedes (id_sede, nombre, direccion) VALUES
(1, 'Policlínico Central UOM', 'Av. Hipólito Yrigoyen 3352'),
(2, 'Policlínico Regional Avellaneda UOM', 'Av. Hipólito Yrigoyen 670');

INSERT INTO recursos (id_recurso, nombre, tipo, id_sede) VALUES
(1, 'Dr. Juan Pérez', 'medico', 1),
(2, 'Dra. Luciana Martinez', 'medico', 2);

INSERT INTO agenda (id_agenda, id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES
(1, 2, 1, '2025-09-25', '10:00:00', '10:30:00', 1),
(2, 3, 2, '2025-09-27', '11:00:00', '11:30:00', 1);

INSERT INTO estado (id_estado, nombre_estado) VALUES
(1, 'confirmado'),
(2, 'pendiente'),
(3, 'cancelado'),
(4, 'atendido');

INSERT INTO estudios (nombre, requiere_preparacion, instrucciones) VALUES
('Análisis de Sangre', 1, 'No comer ni beber nada 8 horas antes del estudio.'),
('Radiografía de Tórax', 0, 'No requiere preparación especial.'),
('Ecografía Abdominal', 1, 'Venir con la vejiga llena. No consumir alimentos grasos el día anterior.'),
('Electrocardiograma', 0, 'No requiere preparación especial.'),
('Tomografía Computada', 0, 'Seguir instrucciones específicas que se indicarán en la clínica.');

INSERT INTO requisitos_estudio (id_requisito, id_estudio, tipo_requisito, valor) VALUES
(1, 1, 'Ayuno', 'No comer ni beber por 8 horas antes del estudio.'),
(2, 3, 'Preparación', 'Beber 1 litro de agua una hora antes.'),
(3, 4, 'Restricciones', 'No requiere.');


----------------------------------------------------------------------------------------------------------
-- FECHA: 22/09
----------------------------------------------------------------------------------------------------------
ALTER TABLE `usuario`
    ADD COLUMN `genero` VARCHAR(50) DEFAULT NULL AFTER `apellido`;

 ALTER TABLE `usuario`
    ADD COLUMN `img_dni` TEXT DEFAULT NULL AFTER `genero`;






