-- Creacion de la base de datos 
CREATE DATABASE GestionTurnos;

USE GestionTurnos;

CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (nombre_rol) VALUES
('Paciente'),
('Medico'),
('Administrador');

CREATE TABLE perfiles (
    id_perfil INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id_rol)
);

CREATE TABLE administradores (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    id_perfil INT,
    FOREIGN KEY (id_perfil) REFERENCES perfiles(id_perfil)
);

-- Borrar este scrip cuando este el abm en el front
INSERT INTO perfiles (nombre, apellido, email, password_hash, rol_id)
VALUES ('Laura', 'Martínez', 'laura.martinez@clinica.com', 
        '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 
        2), -- 2 = Médico
        ('Lionel', 'Messi', 'lionel.messi@clinica.com', 
        '$2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m', 
        3); -- 3 = Administrador

CREATE TABLE afiliados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    numero_afiliado VARCHAR(30) NOT NULL,
    cobertura_salud ENUM('UOM', 'OSDE', 'Swiss Medical', 'Galeno', 'Otra') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    tipo_beneficiario ENUM('titular', 'conyuge', 'conviviente', 'hijo menor', 'hijo mayor') NOT NULL,
    cursa_estudios BOOLEAN DEFAULT FALSE,
    seccional VARCHAR(50)
);

INSERT INTO afiliados (
  numero_documento, numero_afiliado, cobertura_salud, estado,
  tipo_beneficiario, cursa_estudios, seccional
) VALUES
-- Titular
('22000001', '22018515933-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda'),
('22000002', '22018515934-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda'),
('22000003', '22018515935-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda'),
('22000004', '22018515936-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda'),
('22000005', '22018515937-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda');

CREATE TABLE tipos_estudio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    requiere_preparacion BOOLEAN DEFAULT FALSE
);

INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) 
VALUES 
    (1, 'Laboratorio', TRUE),
    (2, 'Rayos X', FALSE),
    (3, 'Tomografía', TRUE),
    (4, 'Resonancia Magnética', TRUE),
    (5, 'Ecografía', FALSE);

CREATE TABLE estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    tipo_estudio_id INT NOT NULL,
    duracion_min INT NOT NULL, -- duración en minutos
    requiere_acompaniante BOOLEAN DEFAULT FALSE,
    requiere_ayuno BOOLEAN DEFAULT FALSE,
    requiere_orden_medica BOOLEAN DEFAULT TRUE,
    instrucciones_preparacion TEXT, -- texto explicativo o HTML
    FOREIGN KEY (tipo_estudio_id) REFERENCES tipos_estudio(id)
);

INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion) 
VALUES 
    (1, 'Análisis de Sangre', 1, 15, FALSE, TRUE, TRUE, 'Presentarse en ayunas de 8 horas.'),
    (2, 'Radiografía de Tórax', 2, 20, FALSE, FALSE, TRUE, 'Quitar objetos metálicos.'),
    (3, 'Tomografía de Abdomen', 3, 30, FALSE, TRUE, TRUE, 'Beber 1 litro de agua antes del estudio.'),
    (4, 'Resonancia de Columna', 4, 45, TRUE, TRUE, TRUE, 'No usar elementos metálicos.'),
    (5, 'Ecografía Abdominal', 5, 25, FALSE, FALSE, FALSE, 'No requiere preparación especial.');

CREATE TABLE sedes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(200) NOT NULL
);

INSERT INTO sedes (id, nombre, direccion) 
VALUES 
    (1, 'Centro Médico Central', 'Av. Siempre Viva 123');

CREATE TABLE recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('medico', 'tecnico', 'equipo') NOT NULL,
    sede_id INT NOT NULL,
    FOREIGN KEY (sede_id) REFERENCES sedes(id)
);

INSERT INTO recursos (id, nombre, tipo, sede_id) 
VALUES 
    (1, 'Dr. Juan Pérez', 'medico', 1),
    (2, 'Técnico Luis Gómez', 'tecnico', 1),
    (3, 'Resonador 3T GE', 'equipo', 1),
    (4, 'Tomógrafo Siemens 64', 'equipo', 1),
    (5, 'Ecógrafo Toshiba X100', 'equipo', 1);

CREATE TABLE agenda_estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recurso_id INT NOT NULL,
    estudio_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    disponible BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (recurso_id) REFERENCES recursos(id),
    FOREIGN KEY (estudio_id) REFERENCES estudios(id)
);

INSERT INTO agenda_estudios (recurso_id, estudio_id, fecha, hora_inicio, hora_fin, disponible) 
VALUES 
    (2, 1, '2025-09-22', '08:00:00', '08:45:00', TRUE),
    (2, 1, '2025-09-22', '09:00:00', '09:45:00', TRUE),
    (2, 1, '2025-09-22', '10:00:00', '10:45:00', TRUE),
    (3, 4, '2025-09-22', '08:00:00', '08:45:00', TRUE),
    (3, 4, '2025-09-23', '09:00:00', '09:45:00', FALSE),
    (3, 4, '2025-09-24', '10:00:00', '10:45:00', FALSE),
    (3, 4, '2025-10-25', '08:00:00', '08:45:00', TRUE),
    (3, 4, '2025-09-26', '09:00:00', '09:45:00', TRUE),
    (3, 4, '2025-09-27', '10:00:00', '10:45:00', TRUE);

CREATE TABLE medicos (
  id_medico INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  apellido VARCHAR(50) NOT NULL,
  numero_documento VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  telefono VARCHAR(100) NOT NULL,
  matricula VARCHAR(255) NOT NULL,
  id_perfil INT,
  FOREIGN KEY (id_perfil) REFERENCES perfiles(id_perfil)
);

INSERT INTO medicos (nombre, apellido, numero_documento, email, telefono, matricula)
VALUES
('Juan', 'Pérez', '30123456', 'juan.perez@clinica.com', '1123456789', 'MAT-1001'),
('Ana', 'Gómez', '28987654', 'ana.gomez@clinica.com', '1134567890', 'MAT-1002'),
('Carlos', 'Rodríguez', '31543210', 'carlos.rodriguez@clinica.com', '1145678901', 'MAT-1003');

CREATE TABLE agenda_medica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_medico INT NOT NULL,
    fecha DATE NOT NULL,  -- Nueva columna fecha
    dia_semana ENUM('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    intervalo_minutos INT NOT NULL DEFAULT 30,
    sede_id INT NOT NULL,
    disponible TINYINT(1) DEFAULT 1,  -- Nueva columna disponible
    FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
    FOREIGN KEY (sede_id) REFERENCES sedes(id)
);

INSERT INTO agenda_medica (id_medico, fecha, dia_semana, hora_inicio, hora_fin, intervalo_minutos, sede_id, disponible)
VALUES 
    (1, '2025-09-22', 'Viernes', '09:00:00', '09:30:00', 30, 1, 1),
    (1, '2025-09-22', 'Viernes', '09:30:00', '10:00:00', 30, 1, 1),
    (1, '2025-09-23', 'Sabado', '10:00:00', '10:30:00', 30, 1, 0),  -- No disponible
    (2, '2025-09-22', 'Viernes', '09:00:00', '09:30:00', 30, 1, 1),
    (2, '2025-09-25', 'Lunes', '09:30:00', '10:00:00', 30, 1, 1),
    (2, '2025-09-24', 'Domingo', '10:00:00', '10:30:00', 30, 1, 0);  -- No disponible

CREATE TABLE especialidades (
  id_especialidad INT AUTO_INCREMENT PRIMARY KEY,
  nombre_especialidad VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO especialidades (nombre_especialidad)
VALUES
('Cardiología'),
('Dermatología'),
('Pediatría'),
('Neurología'),
('Traumatología');

CREATE TABLE medico_especialidad (
  id_medico INT NOT NULL,
  id_especialidad INT NOT NULL,
  PRIMARY KEY (id_medico, id_especialidad),
  FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
  FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
);

INSERT INTO medico_especialidad (id_medico, id_especialidad) 
VALUES 
    (1, 1), (1, 4),  -- Juan Pérez → Cardiología (1), Neurología (4)
    (2, 2),          -- Ana Gómez → Dermatología (2)
    (3, 3), (3, 5);  -- Carlos Rodríguez → Pediatría (3), Traumatología (5)

CREATE TABLE pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    tipo_documento ENUM('DNI', 'Pasaporte', 'Otro') NOT NULL,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    img_dni LONGTEXT NOT NULL,
    genero ENUM('Masculino', 'Femenino', 'Otro') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    domicilio VARCHAR(100) NOT NULL,
    numero_contacto VARCHAR(20) NOT NULL,
    cobertura_salud ENUM('UOM', 'OSDE', 'Swiss Medical', 'Galeno', 'Otra') NOT NULL,
    numero_afiliado VARCHAR(30) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,    
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_afiliado INT,
    token_qr VARCHAR(255) UNIQUE,
    id_perfil INT,
    FOREIGN KEY (id_afiliado) REFERENCES afiliados(id),
    FOREIGN KEY (id_perfil) REFERENCES perfiles(id_perfil)
);

CREATE TABLE recuperacion_password (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    fecha_expiracion DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
);

CREATE TABLE ordenes_estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    estudio_id INT NOT NULL,
    fecha_emision DATE NOT NULL,
    medico_derivante VARCHAR(100) NOT NULL,
    observaciones TEXT,
    archivo_orden LONGTEXT NOT NULL, -- Ruta de archivo en el servidor o base64
    estado ENUM('pendiente', 'validada', 'rechazada'),
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (estudio_id) REFERENCES estudios(id)
);
--REVISAR PARA EVALUAR CREAR TURNOS_ESTUDIOS Y TURNOS_MEDICOS
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    estudio_id INT NULL,  
    recurso_id INT NULL,  
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('pendiente', 'confirmado', 'cancelado'),
    copago DECIMAL(10,2) DEFAULT 0.00,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    orden_estudio_id INT NULL,  -- Puede ser NULL ahora
    medico_id INT NULL,  -- Puede ser NULL ahora
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (estudio_id) REFERENCES estudios(id),
    FOREIGN KEY (recurso_id) REFERENCES recursos(id),
    FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id),
    FOREIGN KEY (medico_id) REFERENCES medicos(id_medico)
);
