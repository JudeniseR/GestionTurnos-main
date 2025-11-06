---------------
-- REGISTROS 
-- 06/11
---------------

use gestionturnos;

--------------------------------------------------------------
--                       AFILIADOS                          --
--------------------------------------------------------------
INSERT INTO afiliados 
(numero_documento, numero_afiliado, cobertura_salud, estado, tipo_beneficiario, cursa_estudios, seccional)
VALUES
-- === GRUPO FAMILIAR 1 (UOM - Avellaneda) ===
('30111222', '30111222-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('30111223', '30111223-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('30111224', '30111224-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('30111225', '30111225-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('30111226', '30111226-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'),

-- === GRUPO FAMILIAR 2 (UOM - Avellaneda) ===
('30222333', '30222333-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('30222334', '30222334-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('30222335', '30222335-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('30222336', '30222336-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('30222337', '30222337-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'),

-- === GRUPO FAMILIAR 3 (UOM - Avellaneda) ===
('30333444', '30333444-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('30333445', '30333445-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('30333446', '30333446-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('30333447', '30333447-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('30333448', '30333448-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'),

-- === GRUPO FAMILIAR 4 (UOM - Avellaneda) ===
('30444555', '30444555-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('30444556', '30444556-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('30444557', '30444557-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('30444558', '30444558-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('30444559', '30444559-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'),

-- === GRUPO FAMILIAR 5 (UOM - Avellaneda) ===
('30555666', '30555666-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('30555667', '30555667-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('30555668', '30555668-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('30555669', '30555669-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('30555670', '30555670-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'),

-- === AFILIADOS INDIVIDUALES (sin grupo familiar) ===
('40000111', '40000111-00', 'UOM', 'activo', 'titular', 0, 'Avellaneda'),
('40000112', '40000112-01', 'UOM', 'activo', 'conyuge', 0, 'Avellaneda'),
('40000113', '40000113-02', 'UOM', 'activo', 'conviviente', 0, 'Avellaneda'),
('40000114', '40000114-03', 'UOM', 'activo', 'hijo menor', 1, 'Avellaneda'),
('40000115', '40000115-04', 'UOM', 'activo', 'hijo mayor', 1, 'Avellaneda'), 

-- === AFILIADOS INDIVIDUALES INACTIVOS (sin grupo familiar) ===
('40000221', '40000221-00', 'UOM', 'inactivo', 'titular', 0, 'Avellaneda'),
('40000222', '40000222-01', 'UOM', 'inactivo', 'conyuge', 0, 'Avellaneda'),
('40000223', '40000223-02', 'UOM', 'inactivo', 'conviviente', 0, 'Avellaneda'),
('40000224', '40000224-03', 'UOM', 'inactivo', 'hijo menor', 1, 'Avellaneda'),
('40000225', '40000225-04', 'UOM', 'inactivo', 'hijo mayor', 1, 'Avellaneda');

---------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        SEDES                             --
--------------------------------------------------------------
INSERT INTO sedes (nombre, direccion) VALUES
				('Policlínico Regional Avellaneda OUM', 'Av. Hipólito Yrigoyen 670'),
				('Policlínico Central UOM', 'Av. Hipólito Yrigoyen 3352');

----------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        ROLES                             --
--------------------------------------------------------------
INSERT INTO roles (nombre_rol) VALUES
					('Paciente'),
					('Medico'),
					('Administrador'),
					('Tecnico');

---------------------------------------------------------------

--------------------------------------------------------------
--                        PACIENTES                         --
--------------------------------------------------------------

-- === GRUPO FAMILIAR 1 (UOM - Avellaneda) ===
CALL insertar_usuario_paciente('Juan', 'Pérez', 'juan.perez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30111222', '1980-05-10', 'Avellaneda 100', '1130111222', 'Casado', 'QR-30111222', '<BASE64_DNI_JUAN>');
CALL insertar_usuario_paciente('María', 'Gómez', 'maria.gomez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30111223', '1982-03-15', 'Avellaneda 100', '1130111223', 'Casado', 'QR-30111223', '<BASE64_DNI_MARIA>');
CALL insertar_usuario_paciente('Carlos', 'López', 'carlos.lopez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30111224', '1983-09-20', 'Avellaneda 100', '1130111224', 'Soltero', 'QR-30111224', '<BASE64_DNI_CARLOS>');
CALL insertar_usuario_paciente('Lucía', 'Pérez', 'lucia.perez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30111225', '2012-06-05', 'Avellaneda 100', '1130111225', 'Soltero', 'QR-30111225', '<BASE64_DNI_LUCIA>');
CALL insertar_usuario_paciente('Martín', 'Pérez', 'martin.perez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30111226', '2004-10-12', 'Avellaneda 100', '1130111226', 'Soltero', 'QR-30111226', '<BASE64_DNI_MARTIN>');

-- === GRUPO FAMILIAR 2 (UOM - Avellaneda) ===
CALL insertar_usuario_paciente('Roberto', 'Fernández', 'roberto.fernandez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30222333', '1978-07-25', 'Avellaneda 200', '1130222333', 'Casado', 'QR-30222333', '<BASE64_DNI_ROBERTO>');
CALL insertar_usuario_paciente('Laura', 'Martínez', 'laura.martinez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30222334', '1980-02-11', 'Avellaneda 200', '1130222334', 'Casado', 'QR-30222334', '<BASE64_DNI_LAURA>');
CALL insertar_usuario_paciente('Sergio', 'Luna', 'sergio.luna@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30222335', '1984-09-03', 'Avellaneda 200', '1130222335', 'Soltero', 'QR-30222335', '<BASE64_DNI_SERGIO>');
CALL insertar_usuario_paciente('Camila', 'Fernández', 'camila.fernandez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30222336', '2015-12-20', 'Avellaneda 200', '1130222336', 'Soltero', 'QR-30222336', '<BASE64_DNI_CAMILA>');
CALL insertar_usuario_paciente('Tomás', 'Fernández', 'tomas.fernandez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30222337', '2006-08-15', 'Avellaneda 200', '1130222337', 'Soltero', 'QR-30222337', '<BASE64_DNI_TOMAS>');

-- === GRUPO FAMILIAR 3 (UOM - Avellaneda) ===
CALL insertar_usuario_paciente('Diego', 'Rodríguez', 'diego.rodriguez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30333444', '1981-04-19', 'Avellaneda 300', '1130333444', 'Casado', 'QR-30333444', '<BASE64_DNI_DIEGO>');
CALL insertar_usuario_paciente('Patricia', 'Sosa', 'patricia.sosa@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30333445', '1984-09-07', 'Avellaneda 300', '1130333445', 'Casado', 'QR-30333445', '<BASE64_DNI_PATRICIA>');
CALL insertar_usuario_paciente('Héctor', 'Rivas', 'hector.rivas@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30333446', '1985-12-01', 'Avellaneda 300', '1130333446', 'Soltero', 'QR-30333446', '<BASE64_DNI_HECTOR>');
CALL insertar_usuario_paciente('Micaela', 'Rodríguez', 'micaela.rodriguez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30333447', '2014-02-18', 'Avellaneda 300', '1130333447', 'Soltero', 'QR-30333447', '<BASE64_DNI_MICAELA>');
CALL insertar_usuario_paciente('Julián', 'Rodríguez', 'julian.rodriguez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30333448', '2007-10-09', 'Avellaneda 300', '1130333448', 'Soltero', 'QR-30333448', '<BASE64_DNI_JULIAN>');

-- === GRUPO FAMILIAR 4 (UOM - Avellaneda) ===
CALL insertar_usuario_paciente('Andrés', 'Morales', 'andres.morales@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30444555', '1979-01-14', 'Avellaneda 400', '1130444555', 'Casado', 'QR-30444555', '<BASE64_DNI_ANDRES>');
CALL insertar_usuario_paciente('Silvina', 'Castro', 'silvina.castro@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30444556', '1983-11-21', 'Avellaneda 400', '1130444556', 'Casado', 'QR-30444556', '<BASE64_DNI_SILVINA>');
CALL insertar_usuario_paciente('Mario', 'Quiroga', 'mario.quiroga@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30444557', '1986-06-29', 'Avellaneda 400', '1130444557', 'Soltero', 'QR-30444557', '<BASE64_DNI_MARIO>');
CALL insertar_usuario_paciente('Paula', 'Morales', 'paula.morales@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30444558', '2010-03-03', 'Avellaneda 400', '1130444558', 'Soltero', 'QR-30444558', '<BASE64_DNI_PAULA>');
CALL insertar_usuario_paciente('Agustín', 'Morales', 'agustin.morales@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30444559', '2005-09-22', 'Avellaneda 400', '1130444559', 'Soltero', 'QR-30444559', '<BASE64_DNI_AGUSTIN>');

-- === GRUPO FAMILIAR 5 (UOM - Avellaneda) ===
CALL insertar_usuario_paciente('Federico', 'Navarro', 'federico.navarro@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30555666', '1982-08-17', 'Avellaneda 500', '1130555666', 'Casado', 'QR-30555666', '<BASE64_DNI_FEDERICO>');
CALL insertar_usuario_paciente('Verónica', 'Ortiz', 'veronica.ortiz@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30555667', '1984-01-12', 'Avellaneda 500', '1130555667', 'Casado', 'QR-30555667', '<BASE64_DNI_VERONICA>');
CALL insertar_usuario_paciente('Daniel', 'Correa', 'daniel.correa@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30555668', '1986-04-04', 'Avellaneda 500', '1130555668', 'Soltero', 'QR-30555668', '<BASE64_DNI_DANIEL>');
CALL insertar_usuario_paciente('Valentina', 'Navarro', 'valentina.navarro@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '30555669', '2011-07-14', 'Avellaneda 500', '1130555669', 'Soltero', 'QR-30555669', '<BASE64_DNI_VALENTINA>');
CALL insertar_usuario_paciente('Mateo', 'Navarro', 'mateo.navarro@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '30555670', '2008-12-30', 'Avellaneda 500', '1130555670', 'Soltero', 'QR-30555670', '<BASE64_DNI_MATEO>');

-- === AFILIADOS INDIVIDUALES ACTIVOS ===
CALL insertar_usuario_paciente('Alejandro', 'Suárez', 'alejandro.suarez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '40000111', '1985-02-10', 'Avellaneda 600', '1140000111', 'Soltero', 'QR-40000111', '<BASE64_DNI_ALEJANDRO>');
CALL insertar_usuario_paciente('Marta', 'Domínguez', 'marta.dominguez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '40000112', '1986-08-18', 'Avellaneda 600', '1140000112', 'Casado', 'QR-40000112', '<BASE64_DNI_MARTA>');
CALL insertar_usuario_paciente('Ricardo', 'Bravo', 'ricardo.bravo@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '40000113', '1987-11-05', 'Avellaneda 600', '1140000113', 'Soltero', 'QR-40000113', '<BASE64_DNI_RICARDO>');
CALL insertar_usuario_paciente('Sofía', 'Suárez', 'sofia.suarez@gmail.com', SHA2('123456', 256), 1, 'Femenino', 'DNI', '40000114', '2013-09-23', 'Avellaneda 600', '1140000114', 'Soltero', 'QR-40000114', '<BASE64_DNI_SOFIA>');
CALL insertar_usuario_paciente('Nicolás', 'Suárez', 'nicolas.suarez@gmail.com', SHA2('123456', 256), 1, 'Masculino', 'DNI', '40000115', '2005-01-08', 'Avellaneda 600', '1140000115', 'Soltero', 'QR-40000115', '<BASE64_DNI_NICOLAS>');

-- === AFILIADOS INDIVIDUALES INACTIVOS ===
CALL insertar_usuario_paciente('Raúl', 'Benítez', 'raul.benitez@gmail.com', SHA2('123456', 256), 0, 'Masculino', 'DNI', '40000221', '1970-03-11', 'Avellaneda 700', '1140000221', 'Casado', 'QR-40000221', '<BASE64_DNI_RAUL>');
CALL insertar_usuario_paciente('Elena', 'Benítez', 'elena.benitez@gmail.com', SHA2('123456', 256), 0, 'Femenino', 'DNI', '40000222', '1972-06-17', 'Avellaneda 700', '1140000222', 'Casado', 'QR-40000222', '<BASE64_DNI_ELENA>');
CALL insertar_usuario_paciente('Federico', 'Benítez', 'federico.benitez@gmail.com', SHA2('123456', 256), 0, 'Masculino', 'DNI', '40000223', '2000-09-25', 'Avellaneda 700', '1140000223', 'Soltero', 'QR-40000223', '<BASE64_DNI_FEDERICO>');
CALL insertar_usuario_paciente('Juliana', 'Benítez', 'juliana.benitez@gmail.com', SHA2('123456', 256), 0, 'Femenino', 'DNI', '40000224', '2003-02-12', 'Avellaneda 700', '1140000224', 'Soltero', 'QR-40000224', '<BASE64_DNI_JULIANA>');
CALL insertar_usuario_paciente('Tomás', 'Benítez', 'tomas.benitez@gmail.com', SHA2('123456', 256), 0, 'Masculino', 'DNI', '40000225', '2005-07-30', 'Avellaneda 700', '1140000225', 'Soltero', 'QR-40000225', '<BASE64_DNI_TOMAS>');


--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        MEDICOS                         --
--------------------------------------------------------------
-- Cardiología
CALL insertar_usuario_medico('Laura', 'Gomez', 'laura.cardiologia@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'CARD-001', '1140001001');

-- Pediatría
CALL insertar_usuario_medico('Mariano', 'Ruiz', 'mariano.pediatria@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'PED-002', '1140001002');

-- Neurología
CALL insertar_usuario_medico('Carla', 'Lopez', 'carla.neuro@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'NEU-003', '1140001003');

-- Ginecología
CALL insertar_usuario_medico('Fernanda', 'Sosa', 'fernanda.gineco@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'GIN-004', '1140001004');

-- Dermatología
CALL insertar_usuario_medico('Sofia', 'Perez', 'sofia.derma@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'DER-005', '1140001005');

-- Endocrinología
CALL insertar_usuario_medico('Ignacio', 'Blanco', 'ignacio.endo@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'END-006', '1140001006');

-- Oftalmología
CALL insertar_usuario_medico('Lucia', 'Vega', 'lucia.oftalmo@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'OFT-007', '1140001007');

-- Ortopedia
CALL insertar_usuario_medico('Andres', 'Morales', 'andres.orto@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'ORT-008', '1140001008');

-- Psicología
CALL insertar_usuario_medico('Veronica', 'Castro', 'veronica.psico@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'PSI-009', '1140001009');

-- Psiquiatría
CALL insertar_usuario_medico('Hernan', 'Mendez', 'hernan.psiquiatria@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'PSQ-010', '1140001010');

-- Traumatología
CALL insertar_usuario_medico('Jorge', 'Paz', 'jorge.trauma@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'TRA-011', '1140001011');

-- Oncología
CALL insertar_usuario_medico('Marisa', 'Torres', 'marisa.onco@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'ONC-012', '1140001012');

-- Reumatología
CALL insertar_usuario_medico('Roberto', 'Suarez', 'roberto.reuma@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'REU-013', '1140001013');

-- Infectología
CALL insertar_usuario_medico('Florencia', 'Acosta', 'florencia.infecto@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'INF-014', '1140001014');

-- Urología
CALL insertar_usuario_medico('Esteban', 'Quiroga', 'esteban.uro@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'URO-015', '1140001015');

-- Otorrinolaringología
CALL insertar_usuario_medico('Cecilia', 'Martinez', 'cecilia.oto@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'OTO-016', '1140001016');

-- Neumología
CALL insertar_usuario_medico('Gabriel', 'Romero', 'gabriel.neumo@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'NEU-017', '1140001017');

-- Cirugía general
CALL insertar_usuario_medico('Patricia', 'Campos', 'patricia.cirugia@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'CIR-018', '1140001018');

-- Cirugía plástica
CALL insertar_usuario_medico('Diego', 'Navarro', 'diego.plastica@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'PLA-019', '1140001019');

-- Gastroenterología
CALL insertar_usuario_medico('Natalia', 'Perez', 'natalia.gastro@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'GAS-020', '1140001020');

-- Maternidad y obstetricia
CALL insertar_usuario_medico('Julieta', 'Sanchez', 'julieta.materno@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'MAT-021', '1140001021');

-- Odontología
CALL insertar_usuario_medico('Raul', 'Ibarra', 'raul.odon@gmail.com',
    SHA2('123456', 256), 2, 1, 'Masculino', NULL, 'ODO-022', '1140001022');

-- Alergología
CALL insertar_usuario_medico('Daniela', 'Lopez', 'daniela.alergia@gmail.com',
    SHA2('123456', 256), 2, 1, 'Femenino', NULL, 'ALE-023', '1140001023');
   
-----------------------------------------------------------------------------------

--------------------------------------------------------------
--                     ESPECIALIDADES                       --
--------------------------------------------------------------
INSERT INTO especialidades (nombre_especialidad) 
VALUES 
  ('Cardiología'),
  ('Pediatría'),
  ('Neurología'),
  ('Ginecología'),
  ('Dermatología'),
  ('Endocrinología'),
  ('Oftalmología'),
  ('Ortopedia'),
  ('Psicología'),
  ('Psiquiatría'),
  ('Traumatología'),
  ('Oncología'),
  ('Reumatología'),
  ('Infectología'),
  ('Urología'),
  ('Otorrinolaringología'),
  ('Neumología'),
  ('Cirugía general'),
  ('Cirugía plástica'),
  ('Gastroenterología'),
  ('Maternidad y obstetricia'),
  ('Odontología'),
  ('Alergología'); 
 
 -------------------------------------------------------------

--------------------------------------------------------------
--                 MEDICO_ESPECIALIDAD                      --
--------------------------------------------------------------
INSERT INTO medico_especialidad (id_medico, id_especialidad)
VALUES
(1, 1),   -- Cardiología
(2, 2),   -- Pediatría
(3, 3),   -- Neurología
(4, 4),   -- Ginecología
(5, 5),   -- Dermatología
(6, 6),   -- Endocrinología
(7, 7),   -- Oftalmología
(8, 8),   -- Ortopedia
(9, 9),   -- Psicología
(10, 10), -- Psiquiatría
(11, 11), -- Traumatología
(12, 12), -- Oncología
(13, 13), -- Reumatología
(14, 14), -- Infectología
(15, 15), -- Urología
(16, 16), -- Otorrinolaringología
(17, 17), -- Neumología
(18, 18), -- Cirugía general
(19, 19), -- Cirugía plástica
(20, 20), -- Gastroenterología
(21, 21), -- Maternidad y obstetricia
(22, 22), -- Odontología
(23, 23); -- Alergología

------------------------------------------------------------------------

--------------------------------------------------------------
--                 RECURSOS: para medicos                   --
--------------------------------------------------------------
INSERT INTO recursos (nombre, tipo, id_sede)
VALUES
('Laura Gomez', 'medico', 1),        -- Cardiología
('Mariano Ruiz', 'medico', 1),       -- Pediatría
('Carla Lopez', 'medico', 1),        -- Neurología
('Fernanda Sosa', 'medico', 1),      -- Ginecología
('Sofia Perez', 'medico', 1),        -- Dermatología
('Ignacio Blanco', 'medico', 1),     -- Endocrinología
('Lucia Vega', 'medico', 1),         -- Oftalmología
('Andres Morales', 'medico', 1),     -- Ortopedia
('Verónica Castro', 'medico', 1),    -- Psicología
('Hernán Mendez', 'medico', 1),      -- Psiquiatría
('Jorge Paz', 'medico', 1),          -- Traumatología
('Marisa Torres', 'medico', 1),      -- Oncología
('Roberto Suarez', 'medico', 1),     -- Reumatología
('Florencia Acosta', 'medico', 1),   -- Infectología
('Esteban Quiroga', 'medico', 1),    -- Urología
('Cecilia Martinez', 'medico', 1),   -- Otorrinolaringología
('Gabriel Romero', 'medico', 1),     -- Neumología
('Patricia Campos', 'medico', 1),    -- Cirugía general
('Diego Navarro', 'medico', 1),      -- Cirugía plástica
('Natalia Perez', 'medico', 1),      -- Gastroenterología
('Julieta Sanchez', 'medico', 1),    -- Maternidad y obstetricia
('Raul Ibarra', 'medico', 1),        -- Odontología
('Daniela Lopez', 'medico', 1);      -- Alergología
------------------------------------------------------------------

--------------------------------------------------------------
--                 MEDICO_RECURSOS                          --
--------------------------------------------------------------
INSERT INTO medico_recursos (id_medico, id_recurso) VALUES
(1, 1),   -- Laura Gomez - Cardiología
(2, 2),   -- Mariano Ruiz - Pediatría
(3, 3),   -- Carla Lopez - Neurología
(4, 4),   -- Fernanda Sosa - Ginecología
(5, 5),   -- Sofia Perez - Dermatología
(6, 6),   -- Ignacio Blanco - Endocrinología
(7, 7),   -- Lucia Vega - Oftalmología
(8, 8),   -- Andres Morales - Ortopedia
(9, 9),   -- Veronica Castro - Psicología
(10, 10), -- Hernan Mendez - Psiquiatría
(11, 11), -- Jorge Paz - Traumatología
(12, 12), -- Marisa Torres - Oncología
(13, 13), -- Roberto Suarez - Reumatología
(14, 14), -- Florencia Acosta - Infectología
(15, 15), -- Esteban Quiroga - Urología
(16, 16), -- Cecilia Martinez - Otorrinolaringología
(17, 17), -- Gabriel Romero - Neumología
(18, 18), -- Patricia Campos - Cirugía general
(19, 19), -- Diego Navarro - Cirugía plástica
(20, 20), -- Natalia Perez - Gastroenterología
(21, 21), -- Julieta Sanchez - Maternidad y obstetricia
(22, 22), -- Raul Ibarra - Odontología
(23, 23); -- Daniela Lopez - Alergología







--------------------------------------------------------------
--                    ADMINISTRADORES                       --
--------------------------------------------------------------
INSERT INTO usuarios (
  nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni
)
VALUES
('Brisa', 'Arrom', 'brisa.arrom@gmail.com', SHA2('123456', 256), 3, 1, 'Femenino', NULL),
('Florencia', 'Garrafa', 'florencia.garrafa@gmail.com', SHA2('123456', 256), 3, 1, 'Femenino', NULL),
('Julieta', 'Rojas', 'julieta.rojas@gmail.com', SHA2('123456', 256), 3, 1, 'Femenino', NULL),
('Karin', 'Peña Baltodano', 'karin.penabaltodano@gmail.com', SHA2('123456', 256), 3, 1, 'Femenino', NULL),
('Yanina', 'Walendzik', 'yanina.walendzik@gmail.com', SHA2('123456', 256), 3, 1, 'Femenino', NULL),
('Brian', 'Ruiz', 'brian.ruiz@gmail.com', SHA2('123456', 256), 3, 1, 'Masculino', NULL),
('Carlos', 'Artaza', 'carlos.artaza@gmail.com', SHA2('123456', 256), 3, 1, 'Masculino', NULL),
('Javier', 'López', 'javier.lopez@gmail.com', SHA2('123456', 256), 3, 1, 'Masculino', NULL);

-----------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                 RECURSOS: para tecnicos                  --
--------------------------------------------------------------
INSERT INTO recursos (nombre, tipo, id_sede)
VALUES
-- === Técnicos de Cardiología ===
('Ana Lopez - Técnico ECG', 'tecnico', 1),
('Diego Martinez - Técnico de esfuerzo', 'tecnico', 1),

-- === Técnicos de Pediatría ===
('Mariana Ruiz - Técnico de laboratorio', 'tecnico', 1),

-- === Técnicos de Neurología ===
('Carlos Lopez - Técnico de resonancia', 'tecnico', 1),

-- === Técnicos de Ginecología ===
('Fernanda Sosa - Técnico de ecografía', 'tecnico', 1),

-- === Técnicos de Dermatología ===
('Sofia Perez - Técnico asistente', 'tecnico', 1),

-- === Técnicos de Endocrinología ===
('Ignacio Blanco - Técnico de laboratorio', 'tecnico', 1),

-- === Técnicos de Oftalmología ===
('Lucia Vega - Técnico de fondo de ojo', 'tecnico', 1),

-- === Técnicos de Ortopedia ===
('Andres Morales - Técnico de rayos X', 'tecnico', 1),

-- === Técnicos de Psicología ===
('Veronica Castro - Asistente de test psicológicos', 'tecnico', 1),

-- === Técnicos de Psiquiatría ===
('Hernan Mendez - Asistente de evaluación psiquiátrica', 'tecnico', 1),

-- === Técnicos de Traumatología ===
('Jorge Paz - Técnico de resonancia', 'tecnico', 1),

-- === Técnicos de Oncología ===
('Marisa Torres - Técnico de biopsia', 'tecnico', 1),

-- === Técnicos de Reumatología ===
('Roberto Suarez - Técnico de laboratorio', 'tecnico', 1),

-- === Técnicos de Infectología ===
('Florencia Acosta - Técnico de laboratorio', 'tecnico', 1),

-- === Técnicos de Urología ===
('Esteban Quiroga - Técnico de ecografía', 'tecnico', 1),

-- === Técnicos de Otorrinolaringología ===
('Cecilia Martinez - Técnico de audiometría', 'tecnico', 1),

-- === Técnicos de Neumología ===
('Gabriel Romero - Técnico de espirometría', 'tecnico', 1),

-- === Técnicos de Cirugía general ===
('Patricia Campos - Asistente quirúrgico', 'tecnico', 1),

-- === Técnicos de Cirugía plástica ===
('Diego Navarro - Asistente quirúrgico', 'tecnico', 1),

-- === Técnicos de Gastroenterología ===
('Natalia Perez - Técnico de endoscopia', 'tecnico', 1),

-- === Técnicos de Maternidad y obstetricia ===
('Julieta Sanchez - Técnico de ecografía', 'tecnico', 1),

-- === Técnicos de Odontología ===
('Raul Ibarra - Técnico de radiología dental', 'tecnico', 1),

-- === Técnicos de Alergología ===
('Daniela Lopez - Técnico de pruebas cutáneas', 'tecnico', 1);


----------------------------------------------------------------------------------------


--------------------------------------------------------------
--                     TECNICOS                             --
--------------------------------------------------------------
-- === Técnicos de Cardiología ===
CALL insertar_usuario_tecnico('Ana', 'Lopez', 'ana.ecg@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico ECG - Ana Lopez');
CALL insertar_usuario_tecnico('Diego', 'Martinez', 'diego.esfuerzo@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Prueba de Esfuerzo - Diego Martinez');

-- === Técnicos de Pediatría ===
CALL insertar_usuario_tecnico('Mariana', 'Ruiz', 'mariana.lab.pedia@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Laboratorio Pediatría - Mariana Ruiz');

-- === Técnicos de Neurología ===
CALL insertar_usuario_tecnico('Carlos', 'Lopez', 'carlos.resonancia@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Resonancia - Carlos Lopez');

-- === Técnicos de Ginecología ===
CALL insertar_usuario_tecnico('Fernanda', 'Sosa', 'fernanda.eco@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Ecografía Ginecológica - Fernanda Sosa');

-- === Técnicos de Dermatología ===
CALL insertar_usuario_tecnico('Sofia', 'Perez', 'sofia.derma@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Dermatología - Sofia Perez');

-- === Técnicos de Endocrinología ===
CALL insertar_usuario_tecnico('Ignacio', 'Blanco', 'ignacio.endocrino@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Endocrinología - Ignacio Blanco');

-- === Técnicos de Oftalmología ===
CALL insertar_usuario_tecnico('Lucia', 'Vega', 'lucia.oftalmo@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Oftalmología - Lucia Vega');

-- === Técnicos de Ortopedia ===
CALL insertar_usuario_tecnico('Andres', 'Morales', 'andres.ortopedia@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Ortopedia - Andres Morales');

-- === Técnicos de Psicología ===
CALL insertar_usuario_tecnico('Veronica', 'Castro', 'veronica.psico@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Psicología - Veronica Castro');

-- === Técnicos de Psiquiatría ===
CALL insertar_usuario_tecnico('Hernan', 'Mendez', 'hernan.psi@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Psiquiatría - Hernan Mendez');

-- === Técnicos de Traumatología ===
CALL insertar_usuario_tecnico('Jorge', 'Paz', 'jorge.trauma@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Traumatología - Jorge Paz');

-- === Técnicos de Oncología ===
CALL insertar_usuario_tecnico('Marisa', 'Torres', 'marisa.onco@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Oncología - Marisa Torres');

-- === Técnicos de Reumatología ===
CALL insertar_usuario_tecnico('Roberto', 'Suarez', 'roberto.reuma@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Reumatología - Roberto Suarez');

-- === Técnicos de Infectología ===
CALL insertar_usuario_tecnico('Florencia', 'Acosta', 'florencia.infecto@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Infectología - Florencia Acosta');

-- === Técnicos de Urología ===
CALL insertar_usuario_tecnico('Esteban', 'Quiroga', 'esteban.urologo@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Urología - Esteban Quiroga');

-- === Técnicos de Otorrinolaringología ===
CALL insertar_usuario_tecnico('Cecilia', 'Martinez', 'cecilia.otorri@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Otorrinolaringología - Cecilia Martinez');

-- === Técnicos de Neumología ===
CALL insertar_usuario_tecnico('Gabriel', 'Romero', 'gabriel.neumo@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Neumología - Gabriel Romero');

-- === Técnicos de Cirugía general ===
CALL insertar_usuario_tecnico('Patricia', 'Campos', 'patricia.cirgen@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Cirugía General - Patricia Campos');

-- === Técnicos de Cirugía plástica ===
CALL insertar_usuario_tecnico('Diego', 'Navarro', 'diego.cirplas@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Cirugía Plástica - Diego Navarro');

-- === Técnicos de Gastroenterología ===
CALL insertar_usuario_tecnico('Natalia', 'Perez', 'natalia.gastro@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Gastroenterología - Natalia Perez');

-- === Técnicos de Maternidad y obstetricia ===
CALL insertar_usuario_tecnico('Julieta', 'Sanchez', 'julieta.obst@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Obstetricia - Julieta Sanchez');

-- === Técnicos de Odontología ===
CALL insertar_usuario_tecnico('Raul', 'Ibarra', 'raul.odont@email.com', SHA2('123456', 256), 1, 'Masculino', NULL, 'Técnico Odontología - Raul Ibarra');

-- === Técnicos de Alergología ===
CALL insertar_usuario_tecnico('Daniela', 'Lopez', 'daniela.alerg@email.com', SHA2('123456', 256), 1, 'Femenino', NULL, 'Técnico Alergología - Daniela Lopez');

---------------------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                    ESTUDIOS                              --
--------------------------------------------------------------
INSERT INTO estudios (nombre, requiere_preparacion, instrucciones)
VALUES
-- === Cardiología ===
('Electrocardiograma (ECG)', 0, 'Realizar en ayunas si es posible'),
('Prueba de esfuerzo', 1, 'Ayuno de 4 horas, ropa cómoda'),

-- === Pediatría ===
('Hemograma completo', 0, NULL),
('Orina completa', 0, NULL),

-- === Neurología ===
('Resonancia magnética cerebral', 1, 'No llevar objetos metálicos'),

-- === Ginecología ===
('Papanicolaou', 0, 'Evitar relaciones sexuales y uso de cremas 48h antes'),
('Ecografía transvaginal', 1, 'Vejiga vacía'),

-- === Dermatología ===
('Biopsia de piel', 0, 'Seguir indicaciones del médico'),

-- === Endocrinología ===
('Glucemia', 1, 'Ayuno de 8 horas'),
('Perfil lipídico', 1, 'Ayuno de 12 horas'),

-- === Oftalmología ===
('Fondo de ojo', 0, 'Evitar conducir tras dilatación'),

-- === Ortopedia ===
('Radiografía de columna', 0, NULL),

-- === Psicología ===
('Test psicológico estandarizado', 0, NULL),

-- === Psiquiatría ===
('Evaluación psiquiátrica', 0, NULL),

-- === Traumatología ===
('Resonancia magnética de rodilla', 1, 'No llevar objetos metálicos'),

-- === Oncología ===
('Biopsia de tejido tumoral', 0, 'Seguir indicaciones del médico'),

-- === Reumatología ===
('Factor reumatoide', 0, 'Tomar muestra de sangre en ayunas'),

-- === Infectología ===
('Serología para virus', 0, 'Muestra de sangre, no requiere ayuno'),

-- === Urología ===
('Ecografía renal', 1, 'Tomar 1 litro de agua 1 hora antes'),

-- === Otorrinolaringología ===
('Audiometría', 0, NULL),

-- === Neumología ===
('Espirometría', 0, 'Evitar broncodilatadores 12h antes'),

-- === Cirugía general ===
('Tomografía computarizada abdominal', 1, 'Ayuno de 4 horas'),

-- === Cirugía plástica ===
('Evaluación preoperatoria', 0, 'Seguir indicaciones médicas'),

-- === Gastroenterología ===
('Colonoscopia', 1, 'Dieta líquida 24h, laxantes según indicación'),

-- === Maternidad y obstetricia ===
('Ecografía obstétrica', 0, 'Seguir indicaciones del médico'),

-- === Odontología ===
('Radiografía panorámica dental', 0, 'Retirar objetos metálicos de cabeza'),

-- === Alergología ===
('Pruebas cutáneas de alergia', 0, 'Evitar antihistamínicos 72h antes');

-------------------------------------------------------------------------------------



--------------------------------------------------------------
--                 TECNICO_ESTUDIO                          --
--------------------------------------------------------------
 -- Cardiología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(1, 1),  -- Técnico ECG - Cardiología -> Electrocardiograma
(2, 2);  -- Técnico de esfuerzo - Cardiología -> Prueba de esfuerzo

-- Pediatría
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(3, 3),  -- Técnico de laboratorio - Pediatría -> Hemograma completo
(3, 4);  -- Técnico de laboratorio - Pediatría -> Orina completa

-- Neurología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(4, 5);  -- Técnico de resonancia - Neurología -> Resonancia magnética cerebral

-- Ginecología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(5, 6),  -- Técnico de ecografía - Ginecología -> Papanicolaou
(5, 7);  -- Técnico de ecografía - Ginecología -> Ecografía transvaginal

-- Dermatología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(6, 8);  -- Técnico asistente - Dermatología -> Biopsia de piel

-- Endocrinología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(7, 9),  -- Técnico de laboratorio - Endocrinología -> Glucemia
(7, 10); -- Técnico de laboratorio - Endocrinología -> Perfil lipídico

-- Oftalmología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(8, 11); -- Técnico de fondo de ojo - Oftalmología -> Fondo de ojo

-- Ortopedia
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(9, 12); -- Técnico de rayos X - Ortopedia -> Radiografía de columna

-- Psicología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(10, 13); -- Asistente de test psicológicos -> Test psicológico estandarizado

-- Psiquiatría
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(11, 14); -- Asistente de evaluación psiquiátrica -> Evaluación psiquiátrica

-- Traumatología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(12, 15); -- Técnico de resonancia - Traumatología -> Resonancia de rodilla

-- Oncología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(13, 16); -- Técnico de biopsia - Oncología -> Biopsia de tejido tumoral

-- Reumatología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(14, 17); -- Técnico de laboratorio - Reumatología -> Factor reumatoide

-- Infectología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(15, 18); -- Técnico de laboratorio - Infectología -> Serología para virus

-- Urología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(16, 19); -- Técnico de ecografía - Urología -> Ecografía renal

-- Otorrinolaringología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(17, 20); -- Técnico de audiometría - Otorrinolaringología -> Audiometría

-- Neumología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(18, 21); -- Técnico de espirometría - Neumología -> Espirometría

-- Cirugía general
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(19, 22); -- Asistente quirúrgico - Cirugía general -> Tomografía computarizada abdominal

-- Cirugía plástica
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(20, 23); -- Asistente quirúrgico - Cirugía plástica -> Evaluación preoperatoria

-- Gastroenterología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(21, 24); -- Técnico de endoscopia - Gastroenterología -> Colonoscopia

-- Obstetricia
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(22, 25); -- Técnico de ecografía - Obstetricia -> Ecografía obstétrica

-- Odontología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(23, 26); -- Técnico de radiología dental -> Radiografía panorámica dental

-- Alergología
INSERT INTO tecnico_estudio (id_tecnico, id_estudio)
VALUES
(24, 27); -- Técnico de pruebas cutáneas -> Pruebas cutáneas de alergia
 
---------------------------------------------------------------------------------------

--------------------------------------------------------------
--                     ESTADOS                             --
--------------------------------------------------------------
INSERT INTO estados (nombre_estado) 
VALUES 
  ('Pendiente'),
  ('Confirmado'),
  ('Atendido'),
  ('Cancelado'),
  ('En curso');
 
 ----------------------------------------------------------------------------------------
   
--------------------------------------------------------------
--                     FERIADOS                             --
--------------------------------------------------------------
INSERT INTO feriados (fecha, motivo, descripcion) VALUES
					('2025-01-01', 'Año Nuevo', ''),
					('2025-03-24', 'Día Nacional de la Memoria por la Verdad y la Justicia', ''),
					('2025-03-03', 'Carnaval (Lunes)', ''),
					('2025-03-04', 'Carnaval (Martes)', ''),
					('2025-04-02', 'Día del Veterano y de los Caídos en la Guerra de Malvinas', ''),
					('2025-04-18', 'Viernes Santo', ''),
					('2025-05-01', 'Día del Trabajador', ''),
					('2025-05-25', 'Día de la Revolución de Mayo', ''),
					('2025-06-17', 'Paso a la Inmortalidad del Gral. Martín Miguel de Güemes', ''),
					('2025-06-20', 'Paso a la Inmortalidad del Gral. Manuel Belgrano', ''),
					('2025-07-09', 'Día de la Independencia', ''),
					('2025-08-17', 'Paso a la Inmortalidad del Gral. José de San Martín', ''),
					('2025-10-12', 'Día del Respeto a la Diversidad Cultural', ''),
					('2025-11-20', 'Día de la Soberanía Nacional', ''),
					('2025-12-08', 'Inmaculada Concepción de María', ''),
					('2025-12-25', 'Navidad', '');

-------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                     TIPO_NOTIFICACIONES                  --
--------------------------------------------------------------
INSERT INTO tipo_notificaciones (nombre, descripcion) VALUES
  ('registro', 'Registro exitoso de usuario'),
  ('login', 'Inicio de sesión del usuario'),
  ('recupero', 'Solicitud de recuperación de contraseña'),
  ('restablecido', 'Contraseña restablecida exitosamente'),
  ('turno_medico', 'Confirmación de turno médico'),
  ('turno_estudio', 'Confirmación de turno de estudio'),
  ('cancelar_turno', 'Notificación de cancelación de turno'),
  ('recordatorio_turno', 'Recordatorio automático de turno');

-------------------------------------------------------------------










