/***************************************************************
--     ESTRUCTURA    --
--       11/11       --
 ***************************************************************/ /**/

CREATE DATABASE gestionturnos; 

use gestionturnos; 

/***************************************************************

 * TABLA: roles
 * Descripción: Define los roles del sistema de gestion de turnos.
 * Esto permite que cada usuario tenga su rol para determinar los
 * permisos y accesos dentro del sistema.
 * 
 * 1. Paciente
 * 2. Medico
 * 3. Administrador
 * 4. Tecnico
 * 5. Administrador

 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS roles (
  id_rol INT NOT NULL AUTO_INCREMENT,
  nombre_rol VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uk_roles_nombre (nombre_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: sedes
 ***************************************************************/ /**/
CREATE TABLE IF NOT EXISTS sedes (
  id_sede INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_sede)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
 * TABLA: estados
 * Descripción: Estados posibles de los turnos.
 * 1. Pendiente
 * 2. Confirmados: Turnos confirmados (id_estado=2) que aún no han pasado 24h
 * 3. Atendidos: Turnos con id_estado=3
 * 4. Cancelados: Turnos con id_estado=4
 * 5. Reprogramado
 * 6. Derivado
 * Vencidos: Turnos confirmados con más de 24h pasadas sin atender
 ***************************************************************/ /**/

CREATE TABLE IF NOT EXISTS estados (
  id_estado INT NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_estado),
  UNIQUE KEY uk_estado_nombre (nombre_estado)   -- cada estado debe ser único
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  clave_publica TEXT NULL COMMENT 'Clave pública RSA para verificar firmas',
  clave_privada TEXT NULL COMMENT 'Clave privada RSA (encriptada) para firmar órdenes',
  fecha_generacion_claves TIMESTAMP NULL COMMENT 'Fecha en que se generaron las claves',
  CONSTRAINT pk_medicos PRIMARY KEY (id_medico),
  CONSTRAINT uk_medicos_matricula UNIQUE (matricula),
  KEY idx_medicos_id_usuario (id_usuario),
  CONSTRAINT fk_medicos_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_general_ci;


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
  id_titular INT DEFAULT NULL,
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
  KEY idx_pacientes_id_titular (id_titular),
  
  CONSTRAINT fk_pacientes_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
    
  CONSTRAINT fk_pacientes_id_titular FOREIGN KEY (id_titular)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  nombre VARCHAR(100) DEFAULT NULL,
  apellido VARCHAR(100) DEFAULT NULL,
  fecha_nacimiento DATE DEFAULT NULL,
  cobertura_salud ENUM('UOM','OSDE','Swiss Medical','Galeno','Otra') NOT NULL,
  estado ENUM('activo','inactivo') DEFAULT 'activo',
  id_titular INT DEFAULT NULL,
  tipo_beneficiario ENUM('titular','conyuge','conviviente','hijo menor','hijo mayor') NOT NULL,
  cursa_estudios TINYINT(1) DEFAULT 0,               -- 0=no, 1=sí
  seccional VARCHAR(50) DEFAULT NULL,
  CONSTRAINT pk_afiliados PRIMARY KEY (id),
  CONSTRAINT uk_afiliados_numero_documento UNIQUE (numero_documento),
  CONSTRAINT fk_afiliados_titular FOREIGN KEY (id_titular) 
      REFERENCES afiliados(id) 
      ON UPDATE CASCADE 
      ON DELETE SET NULL,
  INDEX idx_afiliados_titular (id_titular)
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
  id_recurso INT DEFAULT NULL,
  CONSTRAINT pk_tecnico PRIMARY KEY (id_tecnico),

  KEY idx_tecnico_id_usuario (id_usuario),
  KEY idx_tecnico_id_rol (id_rol),
  KEY idx_tecnico_id_recurso (id_recurso),

  CONSTRAINT fk_tecnico_id_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_tecnico_id_rol FOREIGN KEY (id_rol)
    REFERENCES roles(id_rol)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_tecnico_id_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  id_tecnico INT DEFAULT NULL,
  id_recurso INT DEFAULT NULL,
  id_estudio INT DEFAULT NULL,
  fecha DATE DEFAULT NULL,
  hora_inicio TIME DEFAULT NULL,
  hora_fin TIME DEFAULT NULL,
  disponible TINYINT(1) DEFAULT 1,  -- 1=disponible, 0=no
  CONSTRAINT pk_agenda PRIMARY KEY (id_agenda),

  KEY idx_agenda_id_medico (id_medico),
  KEY idx_agenda_id_tecnico (id_tecnico),
  KEY idx_agenda_id_recurso (id_recurso),
  KEY idx_agenda_id_estudio (id_estudio),

  CONSTRAINT fk_agenda_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_agenda_id_tecnico FOREIGN KEY (id_tecnico)
    REFERENCES tecnicos(id_tecnico)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_agenda_id_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_agenda_id_estudio FOREIGN KEY (id_estudio)
    REFERENCES estudios(id_estudio)
    ON UPDATE CASCADE
    ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  id_medico INT DEFAULT NULL,
  id_tecnico INT DEFAULT NULL,
  fecha DATE NOT NULL,
  hora TIME DEFAULT NULL,
  tipo ENUM('dia','slot') NOT NULL,
  motivo VARCHAR(255) DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,

  CONSTRAINT pk_agenda_bloqueos PRIMARY KEY (id_bloqueo),

  CONSTRAINT uk_agendabloq_medico_fecha_hora UNIQUE (id_medico, fecha, hora, tipo),

  KEY idx_agendabloq_id_medico (id_medico),
  KEY idx_agendabloq_id_tecnico (id_tecnico),
  KEY idx_medico_fecha_tipo (id_medico, fecha, tipo),
  KEY idx_full (id_medico, fecha, hora, tipo, activo),

  CONSTRAINT fk_agendabloq_id_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_agendabloq_id_tecnico FOREIGN KEY (id_tecnico)
    REFERENCES tecnicos(id_tecnico)
    ON UPDATE CASCADE
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLA: ordenes_medicas
-- ========================================

CREATE TABLE IF NOT EXISTS ordenes_medicas (
  id_orden INT NOT NULL AUTO_INCREMENT,
  id_paciente INT NOT NULL,
  id_afiliado INT DEFAULT NULL,
  id_titular INT DEFAULT NULL,
  id_medico INT NOT NULL,
  diagnostico TEXT NOT NULL,
  estudios_indicados TEXT NOT NULL COMMENT 'Nombres de estudios separados por comas o JSON',
  observaciones TEXT DEFAULT NULL,
  contenido_hash VARCHAR(255) NOT NULL COMMENT 'Hash SHA256 del contenido de la orden',
  firma_digital TEXT NOT NULL COMMENT 'Firma RSA del hash',
  fecha_emision TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('activa','utilizada','cancelada') DEFAULT 'activa',
  
  CONSTRAINT pk_ordenes_medicas PRIMARY KEY (id_orden),
  
  KEY idx_ordenes_id_paciente (id_paciente),
  KEY idx_ordenes_id_afiliado (id_afiliado),
  KEY idx_ordenes_id_titular (id_titular),
  KEY idx_ordenes_id_medico (id_medico),
  KEY idx_ordenes_fecha (fecha_emision),
  
  CONSTRAINT fk_ordenes_medicas_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
    
  CONSTRAINT fk_ordenes_medicas_afiliado FOREIGN KEY (id_afiliado)
    REFERENCES afiliados(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
    
  CONSTRAINT fk_ordenes_medicas_titular FOREIGN KEY (id_titular)
    REFERENCES afiliados(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
    
  CONSTRAINT fk_ordenes_medicas_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE RESTRICT

) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_general_ci
  COMMENT='Órdenes médicas firmadas digitalmente';

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
  id_afiliado INT DEFAULT NULL,
  id_tecnico INT DEFAULT NULL COMMENT 'Técnico asignado al turno',
  id_medico INT DEFAULT NULL,
  id_estado INT DEFAULT NULL,
  id_estudio INT DEFAULT NULL,
  id_recurso INT DEFAULT NULL,
  id_orden_medica INT DEFAULT NULL COMMENT 'Orden médica asociada al turno de estudio',
  fecha DATE DEFAULT NULL,
  hora TIME DEFAULT NULL,
  copago DECIMAL(10,2) DEFAULT 0.00,
  observaciones TEXT DEFAULT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reprogramado TINYINT(1) DEFAULT 0,

  CONSTRAINT pk_turnos PRIMARY KEY (id_turno),

  KEY idx_turnos_id_paciente (id_paciente),
  KEY idx_turnos_id_afiliado (id_afiliado),
  KEY idx_turnos_id_tecnico (id_tecnico),
  KEY idx_turnos_id_medico (id_medico),
  KEY idx_turnos_id_estado (id_estado),
  KEY idx_turnos_id_estudio (id_estudio),
  KEY idx_turnos_id_recurso (id_recurso),
  KEY idx_turnos_id_orden_medica (id_orden_medica),

  CONSTRAINT fk_turnos_id_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_turnos_id_afiliado FOREIGN KEY (id_afiliado)
    REFERENCES afiliados(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_turnos_id_tecnico FOREIGN KEY (id_tecnico)
    REFERENCES tecnicos(id_tecnico)
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

  CONSTRAINT fk_turnos_id_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE SET NULL,

  CONSTRAINT fk_turnos_orden_medica FOREIGN KEY (id_orden_medica)
    REFERENCES ordenes_medicas(id_orden)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_general_ci;




-- ==========================================
-- 2. CREAR TABLA tipo_notificaciones
-- ==========================================
CREATE TABLE IF NOT EXISTS tipo_notificaciones (
  id_tipo_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_turno INT DEFAULT NULL,
  id_paciente INT DEFAULT NULL,
  id_usuario INT DEFAULT NULL,
  id_tipo_notificacion INT NOT NULL,
  email_destino VARCHAR(255) NOT NULL,
  asunto VARCHAR(255) NOT NULL,
  cuerpo TEXT NOT NULL,
  estado ENUM('pendiente','enviado','error') DEFAULT 'pendiente',
  mensaje_error TEXT DEFAULT NULL,
  fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_tipo FOREIGN KEY (id_tipo_notificacion)
    REFERENCES tipo_notificaciones(id_tipo_notificacion)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_notif_paciente FOREIGN KEY (id_paciente)
    REFERENCES pacientes(id_paciente)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_notif_turno FOREIGN KEY (id_turno)
    REFERENCES turnos(id_turno)
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

/***************************************************************
 * TABLA: tecnico_estudio
 * PARA SABER QUÉ TECNICOS PUEDEN ATENDER LOS ESTUDIOS
 ***************************************************************/
CREATE TABLE IF NOT EXISTS tecnico_estudio (
  id_tecnico INT NOT NULL,
  id_estudio INT NOT NULL,
  CONSTRAINT pk_tecnico_estudio PRIMARY KEY (id_tecnico, id_estudio),
  CONSTRAINT fk_tecnico_estudio_tecnico FOREIGN KEY (id_tecnico)
    REFERENCES tecnicos(id_tecnico)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_tecnico_estudio_estudio FOREIGN KEY (id_estudio)
    REFERENCES estudios(id_estudio)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


/***************************************************************
 * TABLA: medico_recursos
 ***************************************************************/
CREATE TABLE IF NOT EXISTS medico_recursos (
  id_medico INT NOT NULL,
  id_recurso INT NOT NULL,
  PRIMARY KEY (id_medico, id_recurso),  -- Clave compuesta para evitar duplicados
  CONSTRAINT fk_medico_recursos_medico FOREIGN KEY (id_medico)
    REFERENCES medicos(id_medico)
    ON UPDATE CASCADE
    ON DELETE CASCADE,  -- Si se elimina un médico, se eliminan sus asociaciones
  CONSTRAINT fk_medico_recursos_recurso FOREIGN KEY (id_recurso)
    REFERENCES recursos(id_recurso)
    ON UPDATE CASCADE
    ON DELETE CASCADE  -- Si se elimina un recurso, se eliminan sus asociaciones
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TABLA: administrativos
-- ========================================
CREATE TABLE IF NOT EXISTS administrativos (
  id_administrativo INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  dni VARCHAR(20) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_administrativo PRIMARY KEY (id_administrativo),
  CONSTRAINT fk_administrativo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/***************************************************************
-- Estructura para la vista `excepciones` --
 ***************************************************************/
CREATE VIEW excepciones AS 
SELECT 
  agenda_bloqueos.id_bloqueo AS id_excepcion, 
  agenda_bloqueos.id_medico AS id_medico, 
  agenda_bloqueos.fecha AS fecha, 
  agenda_bloqueos.hora AS hora_desde, 
  agenda_bloqueos.hora AS hora_hasta, 
  agenda_bloqueos.motivo AS motivo 
FROM agenda_bloqueos; 



