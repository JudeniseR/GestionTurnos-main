/***************************************************************
 * SCRIPT: GestionTurnos - Versión adaptada para MariaDB
 * Motor: InnoDB
 * Charset: utf8mb4 / Collation: utf8mb4_general_ci
 * Fecha: 26/09/2025
 ***************************************************************/

-- CREATE database gestionturnos; Lo creo en el motor dentro de la maquina virtual
-- USE gestionturnos; Lo gestiono dentro de la maquina virtual

/***************************************************************
 * TABLA: roles
 * Descripción: Define los roles del sistema de turnos médicos.
 * Ejemplos: paciente 1, médico 2, administrativo 3, técnico 4, ¿admin?
 * Cada usuario debe estar asociado a un rol para determinar
 * sus permisos y accesos dentro de la aplicación.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS roles (
  id_rol INT NOT NULL AUTO_INCREMENT,
  nombre_rol VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uk_roles_nombre (nombre_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=5;

-- Datos iniciales
INSERT INTO roles (id_rol, nombre_rol) VALUES
(1, 'Paciente'),
(2, 'Medico'),
(3, 'Administrador'),
(4, 'Tecnico');


/***************************************************************
 * TABLA: usuarios
 * Descripción: Almacena la información de login y perfil de
 * todas las personas que acceden al sistema (pacientes, médicos,
 * técnicos, administradores). Incluye datos de acceso como email
 * y contraseña, así como información básica de contacto.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  id_rol INT DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  genero VARCHAR(100) DEFAULT NULL,
  img_dni LONGTEXT DEFAULT NULL,
  CONSTRAINT pk_usuario PRIMARY KEY (id_usuario),         
  CONSTRAINT uk_usuario_email UNIQUE (email),             
  KEY idx_usuario_id_rol (id_rol),                        
  CONSTRAINT fk_usuario_id_rol FOREIGN KEY (id_rol)       
    REFERENCES roles(id_rol)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=10;

-- Datos iniciales (ejemplo con usuarios de prueba)
INSERT INTO usuarios (
  id_usuario, nombre, apellido, email, password_hash, id_rol, activo, fecha_creacion, genero, img_dni
) VALUES
(1, 'juli', 'rojas', 'julietadeniserojas@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 3, 1, '2025-09-17 03:50:19', '', ''),
(2, 'juan', 'perez', 'juanperez@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-17 04:05:11', '', ''),
(3, 'Maria', 'Paz', 'cartazaemuiba@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-17 04:15:04', '', ''),
(4, 'javier', 'lopez', 'javierlopez@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-22 23:58:01', '', ''),
(5, 'carlos', 'artaza', 'carlosartaza@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 1, 1, '2025-09-23 00:07:25', '', ''),
(6, 'brian', 'ruiz', 'brianruiz@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 1, 1, '2025-09-23 00:18:35', '', ''),
(7, 'Tomas', 'Otero', 'tomasotero@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 4, 1, '2025-09-23 21:44:10', '', ''),
(8, 'Javi', 'López', 'admin@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 3, 1, '2025-09-24 18:23:00', 'Masculino', ''),
(9, 'Juan', 'Perez', 'jp@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 2, 1, '2025-09-25 12:35:00', 'Masculino', ''), -- CONTRASEÑA = 123456
(10, 'Luciana', 'Martinez', 'lm@gmail.com', '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 3, 1, '2025-09-25 12:35:00', 'Femenino', ''); -- CONTRASEÑA = 123456

/***************************************************************
 * TABLA: sedes
 * Descripción: Representa las distintas sedes, centros médicos
 * o consultorios donde se pueden asignar turnos médicos o de
 * estudios. Permite organizar la disponibilidad por ubicación.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS sedes (
  id_sede INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_sede)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2;

-- Datos iniciales (ejemplo con usuarios de prueba)
INSERT INTO sedes (id_sede, nombre, direccion) VALUES
(1, 'Policlínico Regional Avellaneda OUM', 'Av. Hipólito Yrigoyen 670');

/***************************************************************
 * TABLA: especialidades
 * Descripción: Catálogo de especialidades médicas que ofrece el
 * sanatorio (ej: pediatría, cardiología, traumatología, etc.).
 * Se utiliza para parametrizar la agenda y búsqueda de turnos.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS especialidades (
  id_especialidad INT NOT NULL AUTO_INCREMENT,
  nombre_especialidad VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_especialidad),
  UNIQUE KEY uk_especialidades_nombre (nombre_especialidad)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=8;

-- Volcado de datos para la tabla especialidades
INSERT INTO especialidades (id_especialidad, nombre_especialidad) VALUES
(1, 'Cardiología'),
(7, 'Clínica Médica'),
(4, 'Dermatología'),
(6, 'Ginecología'),
(5, 'Oftalmología'),
(2, 'Pediatría'),
(3, 'Traumatología');

/***************************************************************
 * TABLA: estados
 * Descripción: Estados posibles de los turnos y otros procesos
 * del sistema. Ejemplo: pendiente, confirmado, cancelado, atendido, en curso.
 ***************************************************************/ /**/

CREATE TABLE IF NOT EXISTS estados (
  id_estado INT NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_estado),
  UNIQUE KEY uk_estado_nombre (nombre_estado)   -- cada estado debe ser único
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=5;

-- Volcado de datos para la tabla `estado`
INSERT INTO estados (id_estado, nombre_estado) VALUES
(3, 'atendido'),
(4, 'cancelado'),
(5, 'en_curso'),
(2, 'confirmado'),
(1, 'pendiente');

/***************************************************************
 * TABLA: estudios
 * Descripción: Catálogo de estudios médicos que pueden solicitarse
 * en el sanatorio (ej: análisis de laboratorio, radiografías,
 * resonancias, tomografías, etc.). 
 * Se parametrizan con requisitos y disponibilidad horaria.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS estudios (
  id_estudio INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  requiere_preparacion TINYINT(1) DEFAULT 0,      -- 0=no, 1=sí
  instrucciones TEXT DEFAULT NULL,
  CONSTRAINT pk_estudios PRIMARY KEY (id_estudio),
  CONSTRAINT uk_estudios_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla estudios
INSERT INTO estudios (nombre, requiere_preparacion) VALUES
('Electrocardiograma', 0),
('Análisis de Sangre', 1);

/***************************************************************
 * TABLA: feriados
 * Descripción: Días no laborales (festivos o asuetos) que afectan
 * la disponibilidad de turnos médicos o de estudios. 
 * La agenda se bloquea automáticamente en esas fechas.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS feriados (
  id_feriado INT NOT NULL AUTO_INCREMENT,
  fecha DATE NOT NULL,
  motivo VARCHAR(255) DEFAULT NULL,
  descripcion VARCHAR(150) NOT NULL,
  CONSTRAINT pk_feriados PRIMARY KEY (id_feriado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=17;

-- Volcado de datos para la tabla feriados
INSERT INTO feriados (id_feriado, fecha, motivo, descripcion) VALUES
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

/***************************************************************
 * TABLA: recursos
 * Descripción: Recursos asociados a una sede que se requieren
 * para la atención de pacientes o realización de estudios.
 * Pueden ser:
 *  - Técnicos de salud (ej: bioquímicos, radiólogos).
 *  - Equipamiento médico (ej: resonador, ecógrafo).
 *  - Salas específicas.
 * Los recursos se vinculan con la agenda y determinan la
 * disponibilidad real de un estudio en determinada sede.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS recursos (
  id_recurso INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) DEFAULT NULL,
  tipo ENUM('medico','tecnico','equipo') DEFAULT NULL,
  id_sede INT DEFAULT NULL,
  CONSTRAINT pk_recursos PRIMARY KEY (id_recurso),
  KEY idx_recursos_id_sede (id_sede),
  CONSTRAINT fk_recursos_id_sede FOREIGN KEY (id_sede)
    REFERENCES sedes(id_sede)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla recursos
INSERT INTO recursos (id_recurso, nombre, tipo, id_sede) VALUES
(1, 'Dr. Juan Pérez', 'medico', 1),
(2, 'Dra. Luciana Martinez', 'medico', 1);

/***************************************************************
 * TABLA: medicos
 * Descripción: Registro de médicos que atienden en el sistema.
 * Cada médico está vinculado a un usuario y puede tener una o
 * varias especialidades médicas. 
 * Se almacenan datos como matrícula y teléfono de contacto.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS medicos (
  id_medico INT NOT NULL AUTO_INCREMENT,
  id_usuario INT DEFAULT NULL,
  matricula VARCHAR(100) DEFAULT NULL,
  telefono VARCHAR(50) DEFAULT NULL,
  CONSTRAINT pk_medicos PRIMARY KEY (id_medico),
  CONSTRAINT uk_medicos_matricula UNIQUE (matricula),
  KEY idx_medicos_id_usuario (id_usuario),
  CONSTRAINT fk_medicos_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=4;

-- Volcado de datos para la tabla `medicos`
INSERT INTO medicos (id_medico, id_usuario, matricula, telefono) VALUES
(1, 3, '111222333', '1122000022'),
(2, 6, '123456789', NULL),
(3, 2, NULL, NULL),
(4, 9, 'MAT111', '123456789'),
(5, 10, 'MAT222', '123456789');

/***************************************************************
 * TABLA: medico_especialidad
 * Descripción: Relación muchos-a-muchos entre médicos y
 * especialidades. Permite que un médico tenga más de una
 * especialidad y que una especialidad esté cubierta por
 * múltiples médicos en el sistema.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS medico_especialidad (
  id_medico INT NOT NULL,
  id_especialidad INT NOT NULL,
  CONSTRAINT pk_medico_especialidad PRIMARY KEY (id_medico, id_especialidad),
  KEY idx_medesp_id_especialidad (id_especialidad),
  CONSTRAINT fk_medesp_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_medesp_id_especialidad FOREIGN KEY (id_especialidad)
    REFERENCES especialidades(id_especialidad)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `medico_especialidad`
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES
(1, 2),
(3, 1),
(3, 3),
(4, 1),
(5, 2),
(5, 3); -- Médico con dos especialidades

/***************************************************************
 * TABLA: pacientes
 * Descripción: Registro principal de los pacientes/afiliados.
 * Contiene los datos personales, documento, cobertura de salud,
 * nro. de afiliado, dirección, teléfono y correo electrónico.
 * Se usa para identificar al paciente en la asignación de turnos
 * médicos o de estudios. 
 * Cada paciente puede tener una credencial virtual con QR.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS pacientes (
  id_paciente INT NOT NULL AUTO_INCREMENT,
  id_usuario INT DEFAULT NULL,                       
  tipo_documento VARCHAR(50) DEFAULT NULL,
  nro_documento VARCHAR(50) DEFAULT NULL,
  fecha_nacimiento DATE DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  estado_civil VARCHAR(50) DEFAULT NULL,
  token_qr VARCHAR(255) DEFAULT NULL,                
  CONSTRAINT pk_pacientes PRIMARY KEY (id_paciente),
  CONSTRAINT uk_pacientes_nro_documento UNIQUE (nro_documento),
  CONSTRAINT uk_pacientes_token_qr UNIQUE (token_qr),
  KEY idx_pacientes_id_usuario (id_usuario),
  CONSTRAINT fk_pacientes_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=5;

-- Volcado de datos para la tabla pacientes
INSERT INTO pacientes (id_paciente, id_usuario, tipo_documento, nro_documento, fecha_nacimiento, direccion, telefono, email, estado_civil, token_qr) VALUES
(1, 2, 'DNI', '23111222', '0000-00-00', 'corrientes 1000', '1133000000', 'juanperez@gmail.com', 'casado', NULL),
(2, 5, 'DNI', '44000555', '1995-05-05', 'manuel belgrano 555', '1133778899', 'anarodriguez@gmail.com', NULL, 'e2ee7df3a9280d6f7edcc2dcbf4f36e8'),
(3, 7, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

/***************************************************************
 * TABLA: credencial_virtual
 * Descripción: Credencial digital asociada a cada paciente.
 * Contiene el código QR generado en el registro, que permite
 * identificarlo al ingresar al sistema o presentarlo en la sede.
 * Permite descargar una versión digital o usarla online.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS credencial_virtual (
  id_credencial INT NOT NULL AUTO_INCREMENT,
  id_paciente INT DEFAULT NULL,
  codigo_qr VARCHAR(255) DEFAULT NULL,
  fecha_emision DATE DEFAULT NULL,
  CONSTRAINT pk_credencial_virtual PRIMARY KEY (id_credencial),
  KEY idx_credencial_id_paciente (id_paciente),
  CONSTRAINT fk_credencial_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: afiliados
 * Descripción: Información de afiliación a la cobertura de salud
 * (obra social o prepaga). Incluye número de documento, número
 * de afiliado, tipo de cobertura, estado y tipo de beneficiario
 * (titular, cónyuge, hijo, etc.). Se usa para validar la info del
 * paciente al registrarse.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS afiliados (
  id INT NOT NULL AUTO_INCREMENT,
  numero_documento VARCHAR(20) NOT NULL,
  numero_afiliado VARCHAR(30) NOT NULL,
  cobertura_salud ENUM('UOM','OSDE','Swiss Medical','Galeno','Otra') NOT NULL,
  estado ENUM('activo','inactivo') DEFAULT 'activo',
  tipo_beneficiario ENUM('titular','conyuge','conviviente','hijo menor','hijo mayor') NOT NULL,
  cursa_estudios TINYINT(1) DEFAULT 0,               -- 0=no, 1=sí
  seccional VARCHAR(50) DEFAULT NULL,
  CONSTRAINT pk_afiliados PRIMARY KEY (id),
  CONSTRAINT uk_afiliados_numero_documento UNIQUE (numero_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=17;

-- Volcado de datos para la tabla `afiliados`
INSERT INTO afiliados (id, numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional) VALUES
(16, '44000556', '22018618000-00', 'UOM', 'activo', 'titular', 0, NULL),
(19, '11222333', '22123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
(20, '11222334', '23123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
(21, '11222335', '24123456789-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda');

/***************************************************************
 * TABLA: agenda
 * Descripción: Define los horarios de disponibilidad de médicos
 * y recursos (equipos/técnicos) para asignación de turnos.
 * Cada registro representa un rango horario disponible en un día.
 * Se actualiza automáticamente al reservar un turno y sirve para
 * mostrar disponibilidad en verde (libre) o rojo (ocupado).
 ***************************************************************/
CREATE TABLE IF NOT EXISTS agenda (
  id_agenda INT NOT NULL AUTO_INCREMENT,
  id_medico INT DEFAULT NULL,
  id_recurso INT DEFAULT NULL,
  fecha DATE DEFAULT NULL,
  hora_inicio TIME DEFAULT NULL,
  hora_fin TIME DEFAULT NULL,
  disponible TINYINT(1) DEFAULT 1,                   -- 1=disponible, 0=no
  CONSTRAINT pk_agenda PRIMARY KEY (id_agenda),
  KEY idx_agenda_id_medico (id_medico),
  KEY idx_agenda_id_recurso (id_recurso),
  CONSTRAINT fk_agenda_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_agenda_id_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla agenda
INSERT INTO agenda (id_agenda, id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible) VALUES
(1, 4, 1, '2025-09-28', '10:00:00', '10:30:00', 1),
(2, 4, 1, '2025-09-28', '11:00:00', '11:30:00', 1),
(3, 4, 1, '2025-09-28', '12:00:00', '12:30:00', 0),
(4, 5, 2, '2025-09-29', '10:00:00', '10:30:00', 1),
(5, 5, 2, '2025-09-29', '11:00:00', '11:30:00', 1),
(6, 5, 2, '2025-09-29', '12:00:00', '12:30:00', 0),
(7, 4, 1, '2025-10-08', '10:00:00', '10:30:00', 1),
(8, 4, 1, '2025-10-25', '11:00:00', '11:30:00', 1),
(9, 4, 1, '2025-10-25', '12:00:00', '12:30:00', 1),
(10, 5, 2, '2025-11-22', '10:00:00', '10:30:00', 1),
(11, 5, 2, '2025-11-23', '11:00:00', '11:30:00', 1),
(12, 5, 2, '2025-11-25', '12:00:00', '12:30:00', 1),
(13, 5, 2, '2025-12-21', '19:00:00', '19:30:00', 1);

/***************************************************************
 * TABLA: agenda_bloqueos
 * Descripción: Bloqueos de agenda para un médico o recurso,
 * que pueden ser de tipo "día completo" o "slot horario".
 * Se utilizan para impedir que se asignen turnos en horarios
 * donde el profesional no atiende o el recurso no está disponible.
 * Garantiza que no existan bloqueos duplicados para el mismo día
 * y hora de un médico.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS agenda_bloqueos (
  id_bloqueo INT NOT NULL AUTO_INCREMENT,
  id_medico INT NOT NULL,
  fecha DATE NOT NULL,
  hora TIME DEFAULT NULL,
  tipo ENUM('dia','slot') NOT NULL,
  motivo VARCHAR(255) DEFAULT NULL,
  CONSTRAINT pk_agenda_bloqueos PRIMARY KEY (id_bloqueo),
  CONSTRAINT uk_agendabloq_medico_fecha_hora UNIQUE (id_medico, fecha, hora, tipo),
  KEY idx_agendabloq_id_medico (id_medico),
  CONSTRAINT fk_agendabloq_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2;

-- Volcado de datos para la tabla agenda_bloqueos
INSERT INTO agenda_bloqueos (id_bloqueo, id_medico, fecha, hora, tipo, motivo) VALUES
(1, 1, '2025-09-06', '12:30:00', 'slot', 'Bloqueo manual');

/***************************************************************
 * TABLA: turnos
 * Descripción: Representa cada turno solicitado en el sistema.
 * Puede estar asociado a una consulta médica o a un estudio.
 * Contiene datos del paciente, profesional/recurso, fecha, hora,
 * estado y observaciones. Se utiliza para la gestión de agendas,
 * envío de notificaciones y control de copagos.
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS turnos (
  id_turno INT NOT NULL AUTO_INCREMENT,
  id_paciente INT DEFAULT NULL,
  id_medico INT DEFAULT NULL,
  id_estado INT DEFAULT NULL,
  id_estudio INT DEFAULT NULL,
  id_recurso INT DEFAULT NULL,
  fecha DATE DEFAULT NULL,
  hora TIME DEFAULT NULL,
  copago DECIMAL(10,2) DEFAULT 0.00,
  observaciones TEXT DEFAULT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_turnos PRIMARY KEY (id_turno),
  KEY idx_turnos_id_paciente (id_paciente),
  KEY idx_turnos_id_medico (id_medico),
  KEY idx_turnos_id_estado (id_estado),
  KEY idx_turnos_id_estudio (id_estudio),
  CONSTRAINT fk_turnos_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_turnos_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_turnos_id_estado FOREIGN KEY (id_estado)
    REFERENCES estados(id_estado)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_turnos_id_estudio FOREIGN KEY (id_estudio)
    REFERENCES estudios(id_estudio)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_turno_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=9;

-- Volcado de datos para la tabla turnos
INSERT INTO turnos (id_turno, id_paciente, id_medico, id_estado, id_estudio, fecha, hora, copago, observaciones, fecha_creacion) VALUES
(2, 2, 1, 1, NULL, '2025-09-06', '10:00:00', 0.00, NULL, '2025-09-23 20:58:48'),
(3, 2, 3, 2, NULL, '2025-09-29', '10:00:00', 0.00, NULL, '2025-09-23 21:00:18'),
(4, 2, 1, 1, NULL, '2025-10-15', '10:00:00', 0.00, '', '2025-09-23 22:58:47'),
(5, 1, 2, 4, NULL, '2025-10-25', '12:30:00', 0.00, '', '2025-09-23 22:59:16'),
(6, 3, 2, 3, NULL, '2025-09-14', '13:00:00', 0.00, '', '2025-09-23 23:00:05'),
(7, 3, 2, 1, NULL, '2025-09-21', '22:00:00', 0.00, 'extra turno', '2025-09-23 23:37:07'),
(8, 1, 2, 1, NULL, '2025-09-23', '22:30:00', 0.00, '', '2025-09-23 23:48:22');

/***************************************************************
 * TABLA: notificaciones
 * Descripción: Mensajes enviados a los pacientes relacionados
 * con turnos médicos o estudios. 
 * Se usan para:
 *  - Confirmaciones de citas.
 *  - Recordatorios.
 *  - Cancelaciones o reprogramaciones.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS notificaciones (
  id_notificacion INT NOT NULL AUTO_INCREMENT,
  id_turno INT DEFAULT NULL,
  id_paciente INT DEFAULT NULL,
  mensaje TEXT DEFAULT NULL,
  fecha_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado VARCHAR(50) DEFAULT NULL,                 -- ej: enviado, pendiente, error
  CONSTRAINT pk_notificaciones PRIMARY KEY (id_notificacion),
  KEY idx_notif_id_turno (id_turno),
  KEY idx_notif_id_paciente (id_paciente),
  CONSTRAINT fk_notif_id_turno FOREIGN KEY (id_turno)
    REFERENCES turnos(id_turno)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_notif_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: observaciones
 * Descripción: Observaciones adicionales cargadas por médicos o
 * administrativos. 
 * Pueden estar asociadas a un turno específico o a un paciente.
 ***************************************************************/ 
CREATE TABLE IF NOT EXISTS observaciones (
  id_observacion INT NOT NULL AUTO_INCREMENT,
  id_turno INT DEFAULT NULL,
  id_paciente INT DEFAULT NULL,
  fecha DATE DEFAULT NULL,
  nota TEXT DEFAULT NULL,
  CONSTRAINT pk_observaciones PRIMARY KEY (id_observacion),
  KEY idx_observaciones_id_turno (id_turno),
  KEY idx_observaciones_id_paciente (id_paciente),
  CONSTRAINT fk_observaciones_id_turno FOREIGN KEY (id_turno)
    REFERENCES turnos(id_turno)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_observaciones_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: ordenes_estudio
 * Descripción: Órdenes emitidas por médicos para que pacientes
 * realicen estudios (laboratorio, rayos X, etc.). 
 * Incluye estado (pendiente, validada, rechazada), observaciones
 * y archivo escaneado de la orden.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS ordenes_estudio (
  id_orden INT NOT NULL AUTO_INCREMENT,
  id_paciente INT DEFAULT NULL,
  id_medico INT DEFAULT NULL,
  id_estudio INT DEFAULT NULL,
  fecha_emision DATE DEFAULT NULL,
  estado ENUM('pendiente','validada','rechazada') DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  archivo_orden VARCHAR(255) DEFAULT NULL,          -- ruta/archivo de la orden escaneada
  CONSTRAINT pk_ordenes_estudio PRIMARY KEY (id_orden),
  KEY idx_ordenes_id_paciente (id_paciente),
  KEY idx_ordenes_id_medico (id_medico),
  KEY idx_ordenes_id_estudio (id_estudio),
  CONSTRAINT fk_ordenes_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_ordenes_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_ordenes_id_estudio FOREIGN KEY (id_estudio)
    REFERENCES estudios(id_estudio)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: requisitos_estudio
 * Descripción: Lista de requisitos previos a cumplir por el
 * paciente antes de realizar un estudio (ej: ayuno, no medicarse,
 * concurrir acompañado). 
 * Estos requisitos se muestran al confirmar el turno.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS requisitos_estudio (
  id_requisito INT NOT NULL AUTO_INCREMENT,
  id_estudio INT NOT NULL,
  tipo_requisito VARCHAR(150) NOT NULL,
  valor TEXT DEFAULT NULL,
  CONSTRAINT pk_requisitos_estudio PRIMARY KEY (id_requisito),
  KEY idx_requisitos_id_estudio (id_estudio),
  CONSTRAINT fk_requisitos_id_estudio FOREIGN KEY (id_estudio)
    REFERENCES estudios(id_estudio)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: tecnicos
 * Descripción: Técnicos de salud que realizan estudios médicos
 * (ej: laboratorio, radiología, tomografía). 
 * Cada técnico está vinculado a un usuario para el acceso al
 * sistema, y puede ser asignado a recursos/equipos según la sede.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS tecnicos (
  id_tecnico INT NOT NULL AUTO_INCREMENT,
  id_usuario INT DEFAULT NULL,
  id_rol INT DEFAULT NULL,
  -- especialidad VARCHAR(150) DEFAULT NULL,
  -- telefono VARCHAR(50) DEFAULT NULL,
  -- email VARCHAR(150) DEFAULT NULL,
  CONSTRAINT pk_tecnico PRIMARY KEY (id_tecnico),
  KEY idx_tecnico_id_usuario (id_usuario),
  KEY idx_tecnico_id_rol (id_rol),
  CONSTRAINT fk_tecnico_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_tecnico_id_rol FOREIGN KEY (id_rol)
    REFERENCES roles(id_rol)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci AUTO_INCREMENT=2;

-- Volcado de datos para la tabla `tecnicos`
INSERT INTO tecnicos (id_tecnico, id_usuario, id_rol) VALUES
(1, 9, 4);

/***************************************************************
 * TABLA: administradores
 * Descripción: Administradores del sistema. Cada administrador
 * está asociado a un usuario y puede tener permisos especiales
 * para parametrizar agendas, especialidades, médicos, estudios,
 * gestionar cancelaciones y monitorear el sistema.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS administradores (
  id_admin INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL, 
  fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,  
  CONSTRAINT pk_administrador PRIMARY KEY (id_admin),
  KEY idx_admin_id_usuario (id_usuario),
  CONSTRAINT fk_admin_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcado de datos para la tabla `administradores`
INSERT INTO administradores (id_admin, id_usuario, fecha_asignacion) VALUES
(1, 7, '2025-09-24 18:24:00');

/***************************************************************
 * TABLA: reportes
 * Descripción: Reportes generados por el sistema. 
 * Se utilizan para estadísticas de uso, asistencia, cancelaciones,
 * volumen de estudios y turnos, indicadores por especialidad, sede
 * o período. Son accesibles por el personal autorizado.
 ***************************************************************/
  CREATE TABLE IF NOT EXISTS reportes (
    id_reporte INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NULL,               -- <=== permitir NULL
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    tipo ENUM('PDF','Excel','Otro') DEFAULT 'PDF',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    archivo VARCHAR(255) DEFAULT NULL, 
    CONSTRAINT pk_reportes PRIMARY KEY (id_reporte),
    KEY idx_reportes_generado_por (id_usuario),
    CONSTRAINT fk_reportes_id_usuario FOREIGN KEY (id_usuario)
      REFERENCES usuarios(id_usuario)
      ON UPDATE CASCADE
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: recuperacion_password
 * Descripción: Tokens temporales para recuperación de contraseñas
 * de usuarios. Cada token se invalida al usarse o expirar.
 ***************************************************************/
CREATE TABLE IF NOT EXISTS recuperacion_password (
  id_recuperacion INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token VARCHAR(255) NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_expiracion TIMESTAMP NOT NULL,
  usado TINYINT(1) DEFAULT 0,                      -- 0 = no usado, 1 = usado
  CONSTRAINT pk_recuperacion_password PRIMARY KEY (id_recuperacion),
  CONSTRAINT uk_recuperacion_token UNIQUE (token),
  KEY idx_recuperacion_id_usuario (id_usuario),
  CONSTRAINT fk_recuperacion_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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


--
-- Estructura para la vista `excepciones`
--
CREATE VIEW excepciones AS 
SELECT 
  agenda_bloqueos.id_bloqueo AS id_excepcion, 
  agenda_bloqueos.id_medico AS id_medico, 
  agenda_bloqueos.fecha AS fecha, 
  agenda_bloqueos.hora AS hora_desde, 
  agenda_bloqueos.hora AS hora_hasta, 
  agenda_bloqueos.motivo AS motivo 
FROM agenda_bloqueos; 
