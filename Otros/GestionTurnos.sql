 
----------------------------------------------------------------------------------------------------------
-- FECHA: 23/09 - ULTIMA VERSION
----------------------------------------------------------------------------------------------------------

--
-- Base de datos: `gestionturnos`
--

CREATE database gestionturnos;

USE gestionturnos;
-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administrador`
--

CREATE TABLE `administrador` (
  `id_admin` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `afiliados`
--

CREATE TABLE `afiliados` (
  `id` int(11) NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `numero_afiliado` varchar(30) NOT NULL,
  `cobertura_salud` enum('UOM','OSDE','Swiss Medical','Galeno','Otra') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `tipo_beneficiario` enum('titular','conyuge','conviviente','hijo menor','hijo mayor') NOT NULL,
  `cursa_estudios` tinyint(1) DEFAULT 0,
  `seccional` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `afiliados`
--

INSERT INTO `afiliados` (`id`, `numero_documento`, `numero_afiliado`, `cobertura_salud`, `estado`, `tipo_beneficiario`, `cursa_estudios`, `seccional`) VALUES
(16, '44000555', '22018615000-00', 'UOM', 'activo', 'titular', 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agenda`
--

CREATE TABLE `agenda` (
  `id_agenda` int(11) NOT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `id_recurso` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agenda_bloqueos`
--

CREATE TABLE `agenda_bloqueos` (
  `id_bloqueo` int(11) NOT NULL,
  `id_medico` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `tipo` enum('dia','slot') NOT NULL,
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `agenda_bloqueos`
--

INSERT INTO `agenda_bloqueos` (`id_bloqueo`, `id_medico`, `fecha`, `hora`, `tipo`, `motivo`) VALUES
(1, 1, '2025-09-06', '12:30:00', 'slot', 'Bloqueo manual');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `credencial_virtual`
--

CREATE TABLE `credencial_virtual` (
  `id_credencial` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `codigo_qr` varchar(255) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id_especialidad` int(11) NOT NULL,
  `nombre_especialidad` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especialidades`
--

INSERT INTO `especialidades` (`id_especialidad`, `nombre_especialidad`) VALUES
(1, 'Cardiología'),
(7, 'Clínica Médica'),
(4, 'Dermatología'),
(6, 'Ginecología'),
(5, 'Oftalmología'),
(2, 'Pediatría'),
(3, 'Traumatología');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado`
--

CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado`
--

INSERT INTO `estado` (`id_estado`, `nombre_estado`) VALUES
(3, 'atendido'),
(4, 'cancelado'),
(2, 'confirmado'),
(1, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudios`
--

CREATE TABLE `estudios` (
  `id_estudio` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `requiere_preparacion` tinyint(1) DEFAULT 0,
  `instrucciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `excepciones`
-- (Véase abajo para la vista actual)
--
-- CREATE TABLE `excepciones` (
-- `id_excepcion` int(11)
-- ,`id_medico` int(11)
-- ,`fecha` date
-- ,`hora_desde` time
-- ,`hora_hasta` time
-- ,`motivo` varchar(255)
-- );

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `feriados`
--

CREATE TABLE `feriados` (
  `id_feriado` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `descripcion` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `feriados`
--

INSERT INTO `feriados` (`id_feriado`, `fecha`, `motivo`, `descripcion`) VALUES
(1, '2025-01-01', 'Año Nuevo', ''),
(2, '2025-03-24', 'Día Nacional de la Memoria por la Verdad y la Justicia', ''),
(3, '2025-03-03', 'Carnaval (Lunes)', ''),
(4, '2025-03-04', 'Carnaval (Martes)', ''),
(5, '2025-04-02', 'Día del Veterano y de los Caídos en la Guerra de Malvinas', ''),
(6, '2025-04-18', 'Viernes Santo', ''),
(7, '2025-05-01', 'Día del Trabajador', ''),
(8, '2025-05-25', 'Día de la Revolución de Mayo', ''),
(9, '2025-06-17', 'Paso a la Inmortalidad del Gral. Martín Miguel de Güemes', ''),
(10, '2025-06-20', 'Paso a la Inmortalidad del Gral. Manuel Belgrano', ''),
(11, '2025-07-09', 'Día de la Independencia', ''),
(12, '2025-08-17', 'Paso a la Inmortalidad del Gral. José de San Martín', ''),
(13, '2025-10-12', 'Día del Respeto a la Diversidad Cultural', ''),
(14, '2025-11-20', 'Día de la Soberanía Nacional', ''),
(15, '2025-12-08', 'Inmaculada Concepción de María', ''),
(16, '2025-12-25', 'Navidad', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicos`
--

CREATE TABLE `medicos` (
  `id_medico` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `matricula` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medicos`
--

INSERT INTO `medicos` (`id_medico`, `id_usuario`, `matricula`, `telefono`) VALUES
(1, 3, '111222333', '1122000022'),
(2, 6, '123456789', ''),
(3, 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medico_especialidad`
--

CREATE TABLE `medico_especialidad` (
  `id_medico` int(11) NOT NULL,
  `id_especialidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medico_especialidad`
--

INSERT INTO `medico_especialidad` (`id_medico`, `id_especialidad`) VALUES
(1, 2),
(3, 1),
(3, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_turno` int(11) DEFAULT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `observaciones`
--

CREATE TABLE `observaciones` (
  `id_observacion` int(11) NOT NULL,
  `id_turno` int(11) DEFAULT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `nota` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_estudio`
--

CREATE TABLE `ordenes_estudio` (
  `id_orden` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `id_estudio` int(11) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `estado` enum('pendiente','validada','rechazada') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `archivo_orden` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pacientes`
--

CREATE TABLE `pacientes` (
  `id_paciente` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
  `nro_documento` varchar(50) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `token_qr` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pacientes`
--

INSERT INTO `pacientes` (`id_paciente`, `id_usuario`, `tipo_documento`, `nro_documento`, `fecha_nacimiento`, `direccion`, `telefono`, `email`, `estado_civil`, `token_qr`) VALUES
(1, 2, 'DNI', '23111222', '0000-00-00', 'corrientes 1000', '1133000000', 'juanperez@gmail.com', 'casado', NULL),
(2, 5, 'DNI', '44000555', '1995-05-05', 'manuel belgrano 555', '1133778899', 'anarodriguez@gmail.com', NULL, 'e2ee7df3a9280d6f7edcc2dcbf4f36e8'),
(3, 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recuperacion_password`
--

CREATE TABLE `recuperacion_password` (
  `id_recuperacion` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `token` varchar(64) NOT NULL,
  `fecha_expiracion` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos`
--

CREATE TABLE `recursos` (
  `id_recurso` int(11) NOT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `tipo` enum('medico','tecnico','equipo') DEFAULT NULL,
  `id_sede` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `descripcion` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requisitos_estudio`
--

CREATE TABLE `requisitos_estudio` (
  `id_requisito` int(11) NOT NULL,
  `id_estudio` int(11) NOT NULL,
  `tipo_requisito` varchar(150) NOT NULL,
  `valor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(3, 'Administrador'),
(2, 'Medico'),
(1, 'Paciente'),
(4, 'tecnico');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id_sede` int(11) NOT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id_sede`, `nombre`, `direccion`) VALUES
(1, 'Policlínico Regional Avellaneda OUM', 'Av. Hipólito Yrigoyen 670');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tecnico`
--

CREATE TABLE `tecnico` (
  `id_tecnico` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tecnico`
--

INSERT INTO `tecnico` (`id_tecnico`, `id_usuario`, `id_rol`) VALUES
(1, 9, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id_turno` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `id_estado` int(11) DEFAULT NULL,
  `id_estudio` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `copago` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id_turno`, `id_paciente`, `id_medico`, `id_estado`, `id_estudio`, `fecha`, `hora`, `copago`, `observaciones`, `fecha_creacion`) VALUES
(2, 2, 1, 1, NULL, '2025-09-06', '10:00:00', 0.00, NULL, '2025-09-23 20:58:48'),
(3, 2, 3, 2, NULL, '2025-09-29', '10:00:00', 0.00, NULL, '2025-09-23 21:00:18'),
(4, 2, 1, 1, NULL, '2025-10-15', '10:00:00', 0.00, '', '2025-09-23 22:58:47'),
(5, 1, 2, 4, NULL, '2025-10-25', '12:30:00', 0.00, '', '2025-09-23 22:59:16'),
(6, 3, 2, 3, NULL, '2025-09-14', '13:00:00', 0.00, '', '2025-09-23 23:00:05'),
(7, 3, 2, 1, NULL, '2025-09-21', '22:00:00', 0.00, 'extra turno', '2025-09-23 23:37:07'),
(8, 1, 2, 1, NULL, '2025-09-23', '22:30:00', 0.00, '', '2025-09-23 23:48:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `genero` varchar(100) NOT NULL,
  `img_dni` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `email`, `password_hash`, `id_rol`, `activo`, `fecha_creacion`, `genero`, `img_dni`) VALUES
(1, 'juli', 'rojas', 'julietadeniserojas@gmail.com', '$2y$10$SIG/LtPWZZhm3dpgWHdBzOflhIne5dSn0ov/qMCMvZ1TIzM5/wwaK', 3, 1, '2025-09-17 03:50:19', '', ''),
(2, 'juan', 'perez', 'juanperez@gmail.com', '$2y$10$DKgNJRkq.cRqVDphZmJ1hOWLJ3gem4zsXyf3kjntzudChurfybG2m', 2, 1, '2025-09-17 04:05:11', '', ''),
(3, 'Maria', 'Paz', 'mariapaz@gmail.com', '$2y$10$wKLIgLpEBpbOD37BuxN34.3vicTOPfZztKCTibcy3nG/YEFGb5tjO', 2, 1, '2025-09-17 04:15:04', '', ''),
(5, 'Ana', 'Rodriguez', 'anarodriguez@gmail.com', '$2y$10$CarKE51rEQHZEdLjQGBDguUJPo7I/gN9mXLwARd3pcnHcvXyAmRMK', 1, 1, '2025-09-21 23:32:04', 'Femenino', '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'),
(6, 'javier', 'lopez', 'javierlopez@gmail.com', '$2y$10$RmOFeDKEOg3mq5jqnWvd0OF1h4ED0IOYA1zLcagHVaEq.E0bXqpLa', 2, 1, '2025-09-22 23:58:01', '', ''),
(7, 'carlos', 'artaza', 'carlosartaza@gmail.com', '$2y$10$mN9.TwaL2DAkHGh/du.IwekC.gTClxkFDMUh6y.SxxOT54gj5PtaS', 1, 1, '2025-09-23 00:07:25', '', ''),
(8, 'brian', 'ruiz', 'brianruiz@gmail.com', '$2y$10$3waQdMdebhs.S/l/cew7X.RFg1D2AJMaP1uOGV34XOepmOy.F3pqe', 1, 1, '2025-09-23 00:18:35', '', ''),
(9, 'Tomas', 'Otero', 'tomasotero@gmail.com', '$2y$10$IKCo1dyT/BQce8JbnIgbWurBnVxfb74IzodgH7CfYEPdmHUV1HUN2', 4, 1, '2025-09-23 21:44:10', '', '');

-- --------------------------------------------------------

--
-- Estructura para la vista `excepciones`
--
CREATE VIEW `excepciones` AS 
SELECT 
  `agenda_bloqueos`.`id_bloqueo` AS `id_excepcion`, 
  `agenda_bloqueos`.`id_medico` AS `id_medico`, 
  `agenda_bloqueos`.`fecha` AS `fecha`, 
  `agenda_bloqueos`.`hora` AS `hora_desde`, 
  `agenda_bloqueos`.`hora` AS `hora_hasta`, 
  `agenda_bloqueos`.`motivo` AS `motivo` 
FROM `agenda_bloqueos`;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `afiliados`
--
ALTER TABLE `afiliados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`);

--
-- Indices de la tabla `agenda`
--
ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id_agenda`),
  ADD KEY `id_medico` (`id_medico`),
  ADD KEY `id_recurso` (`id_recurso`);

--
-- Indices de la tabla `agenda_bloqueos`
--
ALTER TABLE `agenda_bloqueos`
  ADD PRIMARY KEY (`id_bloqueo`),
  ADD UNIQUE KEY `ux_medico_fecha_hora` (`id_medico`,`fecha`,`hora`,`tipo`);

--
-- Indices de la tabla `credencial_virtual`
--
ALTER TABLE `credencial_virtual`
  ADD PRIMARY KEY (`id_credencial`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id_especialidad`),
  ADD UNIQUE KEY `nombre_especialidad` (`nombre_especialidad`);

--
-- Indices de la tabla `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `uk_estado_nombre` (`nombre_estado`);

--
-- Indices de la tabla `estudios`
--
ALTER TABLE `estudios`
  ADD PRIMARY KEY (`id_estudio`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `feriados`
--
ALTER TABLE `feriados`
  ADD PRIMARY KEY (`id_feriado`);

--
-- Indices de la tabla `medicos`
--
ALTER TABLE `medicos`
  ADD PRIMARY KEY (`id_medico`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `medico_especialidad`
--
ALTER TABLE `medico_especialidad`
  ADD PRIMARY KEY (`id_medico`,`id_especialidad`),
  ADD KEY `id_especialidad` (`id_especialidad`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `id_turno` (`id_turno`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `observaciones`
--
ALTER TABLE `observaciones`
  ADD PRIMARY KEY (`id_observacion`),
  ADD KEY `id_turno` (`id_turno`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `ordenes_estudio`
--
ALTER TABLE `ordenes_estudio`
  ADD PRIMARY KEY (`id_orden`),
  ADD KEY `id_paciente` (`id_paciente`),
  ADD KEY `id_medico` (`id_medico`),
  ADD KEY `id_estudio` (`id_estudio`);

--
-- Indices de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`),
  ADD UNIQUE KEY `nro_documento` (`nro_documento`),
  ADD UNIQUE KEY `token_qr` (`token_qr`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD PRIMARY KEY (`id_recuperacion`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`),
  ADD KEY `id_sede` (`id_sede`);

--
-- Indices de la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `requisitos_estudio`
--
ALTER TABLE `requisitos_estudio`
  ADD PRIMARY KEY (`id_requisito`),
  ADD KEY `id_estudio` (`id_estudio`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id_sede`);

--
-- Indices de la tabla `tecnico`
--
ALTER TABLE `tecnico`
  ADD PRIMARY KEY (`id_tecnico`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`),
  ADD UNIQUE KEY `id_rol` (`id_rol`);

--
-- Indices de la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id_turno`),
  ADD KEY `id_paciente` (`id_paciente`),
  ADD KEY `id_medico` (`id_medico`),
  ADD KEY `id_estado` (`id_estado`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `afiliados`
--
ALTER TABLE `afiliados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `agenda`
--
ALTER TABLE `agenda`
  MODIFY `id_agenda` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `agenda_bloqueos`
--
ALTER TABLE `agenda_bloqueos`
  MODIFY `id_bloqueo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `credencial_virtual`
--
ALTER TABLE `credencial_virtual`
  MODIFY `id_credencial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id_especialidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `estado`
--
ALTER TABLE `estado`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `estudios`
--
ALTER TABLE `estudios`
  MODIFY `id_estudio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `feriados`
--
ALTER TABLE `feriados`
  MODIFY `id_feriado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `medicos`
--
ALTER TABLE `medicos`
  MODIFY `id_medico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `observaciones`
--
ALTER TABLE `observaciones`
  MODIFY `id_observacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes_estudio`
--
ALTER TABLE `ordenes_estudio`
  MODIFY `id_orden` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  MODIFY `id_recuperacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos`
--
ALTER TABLE `recursos`
  MODIFY `id_recurso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes`
--
ALTER TABLE `reportes`
  MODIFY `id_reporte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `requisitos_estudio`
--
ALTER TABLE `requisitos_estudio`
  MODIFY `id_requisito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id_sede` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tecnico`
--
ALTER TABLE `tecnico`
  MODIFY `id_tecnico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD CONSTRAINT `administrador_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `agenda`
--
ALTER TABLE `agenda`
  ADD CONSTRAINT `agenda_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  ADD CONSTRAINT `agenda_ibfk_2` FOREIGN KEY (`id_recurso`) REFERENCES `recursos` (`id_recurso`);

--
-- Filtros para la tabla `credencial_virtual`
--
ALTER TABLE `credencial_virtual`
  ADD CONSTRAINT `credencial_virtual_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`);

--
-- Filtros para la tabla `medicos`
--
ALTER TABLE `medicos`
  ADD CONSTRAINT `medicos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `medico_especialidad`
--
ALTER TABLE `medico_especialidad`
  ADD CONSTRAINT `medico_especialidad_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  ADD CONSTRAINT `medico_especialidad_ibfk_2` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id_especialidad`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`),
  ADD CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`);

--
-- Filtros para la tabla `observaciones`
--
ALTER TABLE `observaciones`
  ADD CONSTRAINT `observaciones_ibfk_1` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`),
  ADD CONSTRAINT `observaciones_ibfk_2` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`);

--
-- Filtros para la tabla `ordenes_estudio`
--
ALTER TABLE `ordenes_estudio`
  ADD CONSTRAINT `ordenes_estudio_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`),
  ADD CONSTRAINT `ordenes_estudio_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  ADD CONSTRAINT `ordenes_estudio_ibfk_3` FOREIGN KEY (`id_estudio`) REFERENCES `estudios` (`id_estudio`);

--
-- Filtros para la tabla `pacientes`
--
ALTER TABLE `pacientes`
  ADD CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `recuperacion_password`
--
ALTER TABLE `recuperacion_password`
  ADD CONSTRAINT `recuperacion_password_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD CONSTRAINT `recursos_ibfk_1` FOREIGN KEY (`id_sede`) REFERENCES `sedes` (`id_sede`);

--
-- Filtros para la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `requisitos_estudio`
--
ALTER TABLE `requisitos_estudio`
  ADD CONSTRAINT `requisitos_estudio_ibfk_1` FOREIGN KEY (`id_estudio`) REFERENCES `estudios` (`id_estudio`);

--
-- Filtros para la tabla `tecnico`
--
ALTER TABLE `tecnico`
  ADD CONSTRAINT `tecnico_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `tecnico_ibfk_2` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`),
  ADD CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`),
  ADD CONSTRAINT `turnos_ibfk_3` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);
COMMIT;


-----------------------------
-- PRUEBAS -- 24/09
-----------------------------
INSERT INTO afiliados
(id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES(19, '11222333', '22123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');

INSERT INTO afiliados
(id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES(20, '11222334', '23123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');

INSERT INTO afiliados
(id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES(21, '11222335', '24123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');
   
INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `email`, `password_hash`, `id_rol`, `activo`, `fecha_creacion`, `genero`, `img_dni`) VALUES
(11, 'Javi', 'López', 'admin@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 3, 1, '2025-09-24 18:23:00', 'Masculino', '');

INSERT INTO administrador (id_admin, id_usuario, fecha_asignacion) VALUES
(1, 11, '2025-09-24 18:24:00');
   
INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `email`, `password_hash`, `id_rol`, `activo`, `fecha_creacion`, `genero`, `img_dni`) VALUES
(12, 'Juan', 'Perez', 'jp@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-25 12:35:00', 'Masculino', ''), -- CONTRASEÑA = 123456
(13, 'Luciana', 'Martinez', 'lm@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-25 12:35:00', 'Femenino', ''); -- CONTRASEÑA = 123456


INSERT INTO medicos (id_medico, id_usuario, matricula, telefono) VALUES
(4, 12, 'MAT111', '123456789'),
(5, 13, 'MAT222', '123456789');

INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
(4, 1),
(5, 2),
(5, 3); -- Médico con dos especialidades

INSERT INTO recursos (id_recurso, nombre, tipo, id_sede) VALUES
(1, 'Dr. Juan Pérez', 'medico', 1),
(2, 'Dra. Luciana Martinez', 'medico', 1);

INSERT INTO agenda (id_agenda, id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES
(1, 4, 1, '2025-09-25', '10:00:00', '10:30:00', 1),
(2, 4, 1, '2025-09-25', '11:00:00', '11:30:00', 1),
(3, 4, 1, '2025-09-25', '12:00:00', '12:30:00', 0),
(4, 5, 2, '2025-09-25', '10:00:00', '10:30:00', 1),
(5, 5, 2, '2025-09-25', '11:00:00', '11:30:00', 1),
(6, 5, 2, '2025-09-25', '12:00:00', '12:30:00', 0);

INSERT INTO agenda (id_agenda, id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES
(7, 4, 1, '2025-09-25', '10:00:00', '10:30:00', 1),
(8, 4, 1, '2025-09-25', '11:00:00', '11:30:00', 1),
(9, 4, 1, '2025-09-25', '12:00:00', '12:30:00', 1),
(10, 5, 2, '2025-09-25', '10:00:00', '10:30:00', 1),
(11, 5, 2, '2025-09-25', '11:00:00', '11:30:00', 1),
(12, 5, 2, '2025-09-25', '12:00:00', '12:30:00', 1);

INSERT INTO agenda (id_agenda, id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES
(13, 5, 2, '2025-09-29', '19:00:00', '19:30:00', 1);

   
 use gestionturnos;