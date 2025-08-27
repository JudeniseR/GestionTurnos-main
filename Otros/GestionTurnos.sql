-- Creacion de la base de datos 
CREATE DATABASE GestionTurnos;

USE GestionTurnos;

-- Creacion de las tablas
CREATE TABLE pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    tipo_documento ENUM('DNI', 'Pasaporte', 'Otro') NOT NULL,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    img_dni LONGTEXT NOT NULL, -- Ruta o nombre del archivo
    genero ENUM('Masculino', 'Femenino', 'Otro') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    domicilio VARCHAR(100) NOT NULL,
    numero_contacto VARCHAR(20) NOT NULL,
    cobertura_salud ENUM('UOM', 'OSDE', 'Swiss Medical', 'Galeno', 'Otra') NOT NULL,
    numero_afiliado VARCHAR(30) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Se recomienda guardar un hash, no la contraseña en texto
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE afiliados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    numero_afiliado VARCHAR(30) NOT NULL,
    cobertura_salud ENUM('UOM', 'OSDE', 'Swiss Medical', 'Galeno', 'Otra') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo'
);

INSERT INTO afiliados (
  numero_documento, numero_afiliado, cobertura_salud, estado,
  tipo_beneficiario, cursa_estudios, seccional
) VALUES
-- Titular
('22000001', '22018515933-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda'),

-- Cónyuge
('22000002', '22018515933-01', 'UOM', 'activo', 'conyuge', FALSE, 'Avellaneda'),

-- Conviviente
('22000003', '22018515933-02', 'UOM', 'activo', 'conviviente', FALSE, 'Avellaneda'),

-- Hijo menor de 21
('22000004', '22018515933-03', 'UOM', 'activo', 'hijo menor', FALSE, 'Avellaneda'),

-- Hijo mayor de 22 años que cursa estudios
('22000005', '22018515933-04', 'UOM', 'activo', 'hijo mayor', TRUE, 'Avellaneda'),

-- Hijo mayor de 24 años que NO cursa estudios (debería ser inválido)
('22000006', '22018515933-04', 'UOM', 'activo', 'hijo mayor', FALSE, 'Avellaneda');

INSERT INTO afiliados (
  numero_documento, numero_afiliado, cobertura_salud, estado,
  tipo_beneficiario, cursa_estudios, seccional
) VALUES
-- Titular
('22000007', '23018515933-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda');

INSERT INTO afiliados (
  numero_documento, numero_afiliado, cobertura_salud, estado,
  tipo_beneficiario, cursa_estudios, seccional
) VALUES
-- Titular
('22000008', '24018515933-00', 'UOM', 'activo', 'titular', FALSE, 'Avellaneda');


-- 24/07
ALTER TABLE afiliados 
ADD tipo_beneficiario ENUM('titular', 'conyuge', 'conviviente', 'hijo menor', 'hijo mayor') NOT NULL,
ADD cursa_estudios BOOLEAN DEFAULT FALSE,
ADD seccional VARCHAR(50);

ALTER TABLE pacientes 
ADD id_afiliado INT,
ADD FOREIGN KEY (id_afiliado) REFERENCES afiliados(id);

ALTER TABLE pacientes ADD COLUMN token_qr VARCHAR(255) UNIQUE;



DELETE FROM pacientes;
DELETE FROM afiliados;

ALTER TABLE pacientes AUTO_INCREMENT = 1;
ALTER TABLE afiliados AUTO_INCREMENT = 1;


-- 28/07 
CREATE TABLE tipos_estudio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    requiere_preparacion BOOLEAN DEFAULT FALSE
);


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

CREATE TABLE sedes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(200) NOT NULL
);

CREATE TABLE recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('medico', 'tecnico', 'equipo') NOT NULL,
    sede_id INT NOT NULL,
    FOREIGN KEY (sede_id) REFERENCES sedes(id)
);

CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    estudio_id INT NOT NULL,
    recurso_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('pendiente', 'confirmado', 'cancelado') DEFAULT 'pendiente',
    copago DECIMAL(10,2) DEFAULT 0.00,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (estudio_id) REFERENCES estudios(id),
    FOREIGN KEY (recurso_id) REFERENCES recursos(id)
);

ALTER TABLE turnos
ADD COLUMN orden_estudio_id INT NOT NULL,
ADD FOREIGN KEY (orden_estudio_id) REFERENCES ordenes_estudios(id);

ALTER TABLE turnos
ADD COLUMN medico_id INT;

ALTER TABLE turnos 
ADD CONSTRAINT fk_turnos_medico
FOREIGN KEY (medico_id) REFERENCES medicos(id_medico);

ALTER TABLE turnos MODIFY estudio_id INT NULL;

ALTER TABLE turnos
  MODIFY COLUMN recurso_id INT NULL,
  MODIFY COLUMN orden_estudio_id INT NULL;





-- CAMBIAR EL NOMBRE DE LA TABLA A: agenda_estudios Y ADAPTARLO A LA LOGICA
CREATE TABLE agenda (
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

CREATE TABLE ordenes_estudios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    estudio_id INT NOT NULL,
    fecha_emision DATE NOT NULL,
    medico_derivante VARCHAR(100) NOT NULL,
    observaciones TEXT,
    archivo_orden LONGTEXT NOT NULL, -- Ruta de archivo en el servidor o base64
    estado ENUM('pendiente', 'validada', 'rechazada') DEFAULT 'pendiente',
    fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (estudio_id) REFERENCES estudios(id)
);


INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) VALUES (1, 'Laboratorio', TRUE);
INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) VALUES (2, 'Rayos X', FALSE);
INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) VALUES (3, 'Tomografía', TRUE);
INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) VALUES (4, 'Resonancia Magnética', TRUE);
INSERT INTO tipos_estudio (id, nombre, requiere_preparacion) VALUES (5, 'Ecografía', FALSE);

INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion)
VALUES (1, 'Análisis de Sangre', 1, 15, FALSE, TRUE, TRUE, 'Presentarse en ayunas de 8 horas.');
INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion)
VALUES (2, 'Radiografía de Tórax', 2, 20, FALSE, FALSE, TRUE, 'Quitar objetos metálicos.');
INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion)
VALUES (3, 'Tomografía de Abdomen', 3, 30, FALSE, TRUE, TRUE, 'Beber 1 litro de agua antes del estudio.');
INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion)
VALUES (4, 'Resonancia de Columna', 4, 45, TRUE, TRUE, TRUE, 'No usar elementos metálicos.');
INSERT INTO estudios (id, nombre, tipo_estudio_id, duracion_min, requiere_acompaniante, requiere_ayuno, requiere_orden_medica, instrucciones_preparacion)
VALUES (5, 'Ecografía Abdominal', 5, 25, FALSE, FALSE, FALSE, 'No requiere preparación especial.');

INSERT INTO sedes (id, nombre, direccion) VALUES (1, 'Centro Médico Central', 'Av. Siempre Viva 123');
INSERT INTO sedes (id, nombre, direccion) VALUES (2, 'Policlínico Norte', 'Calle Falsa 456');
INSERT INTO sedes (id, nombre, direccion) VALUES (3, 'Sucursal Oeste', 'Ruta 9 Km 12.5');

INSERT INTO recursos (id, nombre, tipo, sede_id) VALUES (1, 'Dr. Juan Pérez', 'medico', 1);
INSERT INTO recursos (id, nombre, tipo, sede_id) VALUES (2, 'Técnico Luis Gómez', 'tecnico', 1);
INSERT INTO recursos (id, nombre, tipo, sede_id) VALUES (3, 'Resonador 3T GE', 'equipo', 1);
INSERT INTO recursos (id, nombre, tipo, sede_id) VALUES (4, 'Tomógrafo Siemens 64', 'equipo', 2);
INSERT INTO recursos (id, nombre, tipo, sede_id) VALUES (5, 'Ecógrafo Toshiba X100', 'equipo', 3);

INSERT INTO agenda (recurso_id, estudio_id, fecha, hora_inicio, hora_fin, disponible)
VALUES (3, 4, '2025-07-29', '08:00:00', '08:45:00', TRUE);
INSERT INTO agenda (recurso_id, estudio_id, fecha, hora_inicio, hora_fin, disponible)
VALUES (3, 4, '2025-07-29', '09:00:00', '09:45:00', TRUE);
INSERT INTO agenda (recurso_id, estudio_id, fecha, hora_inicio, hora_fin, disponible)
VALUES (3, 4, '2025-07-29', '10:00:00', '10:45:00', TRUE);

INSERT INTO agenda (recurso_id, estudio_id, fecha, hora_inicio, hora_fin, disponible)
VALUES
(3, 4, '2025-08-03', '08:00:00', '08:45:00', TRUE),
(3, 4, '2025-08-04', '09:00:00', '09:45:00', TRUE),
(3, 4, '2025-08-05', '10:00:00', '10:45:00', TRUE);


UPDATE agenda SET disponible = TRUE;
UPDATE agenda_medica SET disponible = TRUE;


--- 03/08

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


CREATE TABLE medicos (
  id_medico INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  apellido VARCHAR(50) NOT NULL,
  numero_documento VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  telefono VARCHAR(100) NOT NULL,
  matricula VARCHAR(255) NOT NULL
);

INSERT INTO medicos (nombre, apellido, numero_documento, email, telefono, matricula)
VALUES
('Juan', 'Pérez', '30123456', 'juan.perez@clinica.com', '1123456789', 'MAT-1001'),
('Ana', 'Gómez', '28987654', 'ana.gomez@clinica.com', '1134567890', 'MAT-1002'),
('Carlos', 'Rodríguez', '31543210', 'carlos.rodriguez@clinica.com', '1145678901', 'MAT-1003');

CREATE TABLE medico_especialidad (
  id_medico INT NOT NULL,
  id_especialidad INT NOT NULL,
  PRIMARY KEY (id_medico, id_especialidad),
  FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
  FOREIGN KEY (id_especialidad) REFERENCES especialidades(id_especialidad)
);

-- Relacionar médicos con especialidades (medico_especialidad)
-- Juan Pérez → Cardiología (1), Neurología (4)
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (1, 1), (1, 4);

-- Ana Gómez → Dermatología (2)
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (2, 2);

-- Carlos Rodríguez → Pediatría (3), Traumatología (5)
INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (3, 3), (3, 5);


CREATE TABLE agenda_medica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_medico INT NOT NULL,
    dia_semana ENUM('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    intervalo_minutos INT NOT NULL DEFAULT 30,
    sede_id INT NOT NULL,
    FOREIGN KEY (id_medico) REFERENCES medicos(id_medico),
    FOREIGN KEY (sede_id) REFERENCES sedes(id)
);

ALTER TABLE agenda_medica
ADD COLUMN fecha DATE NOT NULL AFTER id_medico,
ADD COLUMN disponible TINYINT(1) DEFAULT 1 AFTER fecha;


INSERT INTO agenda_medica (id_medico, fecha, dia_semana, hora_inicio, hora_fin, intervalo_minutos, sede_id, disponible)
VALUES
(1, '2025-08-05', 'martes', '09:00:00', '09:30:00', 30, 1, 1),
(1, '2025-08-05', 'martes', '09:30:00', '10:00:00', 30, 1, 1),
(1, '2025-08-06', 'miércoles', '10:00:00', '10:30:00', 30, 1, 0); -- No disponible














- PRUEBAS 
USE Prueba;

CREATE TABLE IF NOT EXISTS imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    img_dni LONGTEXT NOT NULL
);


SELECT 
    p.nombre, p.apellido, p.numero_afiliado, 
    a.seccional, a.estado
FROM pacientes p
INNER JOIN afiliados a ON p.id_afiliado = a.id
WHERE p.id = 1; -- o el idPaciente que tengas


DESCRIBE pacientes;

DELETE FROM turnos;
DELETE FROM agenda_medica;






