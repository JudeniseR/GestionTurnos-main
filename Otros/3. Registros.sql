/*---------------
-- REGISTROS 
-- 06/11
---------------
*/

use gestionturnos;

/*--------------------------------------------------------------
--                       AFILIADOS                          --
--------------------------------------------------------------*/
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

/*---------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        SEDES                             --
--------------------------------------------------------------*/
INSERT INTO sedes (nombre, direccion) VALUES
				('Policlínico Regional Avellaneda OUM', 'Av. Hipólito Yrigoyen 670'),
				('Policlínico Central UOM', 'Av. Hipólito Yrigoyen 3352');

/*----------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        ROLES                             --
--------------------------------------------------------------*/
INSERT INTO roles (nombre_rol) VALUES
					('Paciente'),
					('Medico'),
					('Administrador'),
					('Tecnico'),
					('Administrativo');

/*---------------------------------------------------------------

--------------------------------------------------------------
--                        PACIENTES                         --
--------------------------------------------------------------*/

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


/*--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                        MEDICOS                         --
--------------------------------------------------------------*/
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
   
/*-----------------------------------------------------------------------------------

--------------------------------------------------------------
--                     ESPECIALIDADES                       --
--------------------------------------------------------------*/
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
 
/*-------------------------------------------------------------

--------------------------------------------------------------
--                 MEDICO_ESPECIALIDAD                      --
--------------------------------------------------------------*/
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

/*------------------------------------------------------------------------

--------------------------------------------------------------
--                 RECURSOS: para medicos                   --
--------------------------------------------------------------*/
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
/*------------------------------------------------------------------

--------------------------------------------------------------
--                 MEDICO_RECURSOS                          --
--------------------------------------------------------------*/
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


/*--------------------------------------------------------------
--                    ADMINISTRADORES                       --
--------------------------------------------------------------*/
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

/*-----------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                 RECURSOS: para tecnicos                  --
--------------------------------------------------------------*/
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


/*----------------------------------------------------------------------------------------


--------------------------------------------------------------
--                     TECNICOS                             --
--------------------------------------------------------------*/
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

/*---------------------------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                    ESTUDIOS                              --
--------------------------------------------------------------*/
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

/*-------------------------------------------------------------------------------------



--------------------------------------------------------------
--                 TECNICO_ESTUDIO                          --
--------------------------------------------------------------*/
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
 
/*---------------------------------------------------------------------------------------

--------------------------------------------------------------
--                     ESTADOS                             --
--------------------------------------------------------------*/
INSERT INTO estados (nombre_estado) 
VALUES 
  ('Pendiente'),
  ('Confirmado'),
  ('Atendido'),
  ('Cancelado'),
  ('Reprogramado'),
  ('Derivado');
 
 /*----------------------------------------------------------------------------------------
   
--------------------------------------------------------------
--                     FERIADOS                             --
--------------------------------------------------------------*/
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

/*-------------------------------------------------------------------------------------------------------

--------------------------------------------------------------
--                     TIPO_NOTIFICACIONES                  --
--------------------------------------------------------------*/
INSERT INTO tipo_notificaciones (nombre, descripcion) VALUES
  ('registro', 'Registro exitoso de usuario'),
  ('login', 'Inicio de sesión del usuario'),
  ('recupero', 'Solicitud de recuperación de contraseña'),
  ('restablecido', 'Contraseña restablecida exitosamente'),
  ('turno_medico', 'Confirmación de turno médico'),
  ('turno_estudio', 'Confirmación de turno de estudio'),
  ('cancelar_turno', 'Notificación de cancelación de turno'),
  ('recordatorio_turno', 'Recordatorio automático de turno');

/*-------------------------------------------------------------------*/




/* $2y$10$J9/Lns4LdoMoM/Q528NZeOyHQiUqxqFsKi56JvkvlCIv8Ol7qv83m -- contraseña: 123456 */




 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 use gestionturnos;
 

 
 -- ================================================================

 
 -- 					AFILIADOS

 
 -- ================================================================
 
 -- ================================================================
--                   GRUPO FAMILIAR 1
--                  (Titular + conyuge)
-- ================================================================

-- TITULAR
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, tipo_beneficiario, seccional
) VALUES (
    '40111222', '40111222-00', 'Juan', 'Pérez', '1980-05-10',
    'UOM', 'activo', 'titular', 'Avellaneda'
);

SET @id_titular := LAST_INSERT_ID();


-- CÓNYUGE
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, seccional
) VALUES (
    '40111223', '40111223-01', 'María', 'González', '1982-03-18',
    'UOM', 'activo', @id_titular, 'conyuge', 'Avellaneda'
);

-- HIJO MENOR (cursa estudios)
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, cursa_estudios, seccional
) VALUES (
    '40111225', '40111225-03', 'Lucía', 'Pérez', '2012-11-02',
    'UOM', 'activo', @id_titular, 'hijo menor', 1, 'Avellaneda'
);


-- HIJO MAYOR (cursa estudios)
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, cursa_estudios, seccional
) VALUES (
    '40111226', '40111226-04', 'Carlos', 'Pérez', '2004-01-15',
    'UOM', 'activo', @id_titular, 'hijo mayor', 1, 'Avellaneda'
);

 

-- ================================================================
--                     GRUPO FAMILIAR 2 
--                  (Titular + Conviviente)
-- ================================================================

-- TITULAR
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, tipo_beneficiario, seccional
) VALUES (
    '50111222', '50111222-00', 'Carlos', 'Ramírez', '1978-02-20',
    'UOM', 'activo', 'titular', 'Avellaneda'
);

SET @id_titular := LAST_INSERT_ID();


-- CONVIVIENTE
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, seccional
) VALUES (
    '50111223', '50111223-02', 'Laura', 'Sosa', '1985-09-14',
    'UOM', 'activo', @id_titular, 'conviviente', 'Avellaneda'
);


-- HIJO MENOR
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, cursa_estudios, seccional
) VALUES (
    '50111224', '50111224-03', 'Martín', 'Ramírez', '2013-04-10',
    'UOM', 'activo', @id_titular, 'hijo menor', 1, 'Avellaneda'
);


-- HIJO MAYOR (cursa estudios)
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, cursa_estudios, seccional
) VALUES (
    '50111225', '50111225-04', 'Agustina', 'Ramírez', '2003-07-22',
    'UOM', 'activo', @id_titular, 'hijo mayor', 1, 'Avellaneda'
);

 
 -- ================================================================
--     GRUPO FAMILIAR 3 — Titular + Cónyuge (sin hijos)
-- ================================================================

-- TITULAR
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, tipo_beneficiario, seccional
) VALUES (
    '60111222', '60111222-00', 'Ricardo', 'Méndez', '1981-11-12',
    'UOM', 'activo', 'titular', 'Avellaneda'
);

SET @id_titular := LAST_INSERT_ID();

-- CÓNYUGE
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, seccional
) VALUES (
    '60111223', '60111223-01', 'Patricia', 'Alvarez', '1984-05-21',
    'UOM', 'activo', @id_titular, 'conyuge', 'Avellaneda'
);


-- ================================================================
--     GRUPO FAMILIAR B — Titular + Conviviente (sin hijos)
-- ================================================================

-- TITULAR
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, tipo_beneficiario, seccional
) VALUES (
    '60111224', '60111224-00', 'Sergio', 'López', '1975-08-30',
    'UOM', 'activo', 'titular', 'Avellaneda'
);

SET @id_titular := LAST_INSERT_ID();

-- CONVIVIENTE
INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, id_titular, tipo_beneficiario, seccional
) VALUES (
    '60111225', '60111225-02', 'Carolina', 'Suárez', '1980-12-05',
    'UOM', 'activo', @id_titular, 'conviviente', 'Avellaneda'
);

 
 -- ================================================================
--     TITULARES INDEPENDIENTES 
-- ================================================================

INSERT INTO afiliados (
    numero_documento, numero_afiliado, nombre, apellido, fecha_nacimiento,
    cobertura_salud, estado, tipo_beneficiario, seccional
) VALUES
('70111222', '70111222-00', 'Marta',    'Quiroga',   '1990-02-14', 'UOM', 'activo', 'titular', 'Avellaneda'),
('70111223', '70111223-00', 'Diego',    'Romero',    '1988-09-09', 'UOM', 'activo', 'titular', 'Avellaneda'),
('70111224', '70111224-00', 'Vanesa',   'Paz',       '1977-03-28', 'UOM', 'activo', 'titular', 'Avellaneda'),
('70111225', '70111225-00', 'Jorge',    'Benítez',   '1982-06-22', 'UOM', 'activo', 'titular', 'Avellaneda'),
('70111226', '70111226-00', 'Verónica', 'Martínez',  '1995-10-31', 'UOM', 'activo', 'titular', 'Avellaneda');





 -- ================================================================

 
 -- 					SEDES

 
 -- ================================================================
INSERT INTO sedes (nombre, direccion) VALUES
				('Policlínico Regional Avellaneda OUM', 'Av. Hipólito Yrigoyen 670');
			
			
			
 -- ================================================================

 
 -- 					ROLES

 
 -- ================================================================
INSERT INTO roles (nombre_rol) VALUES
					('Paciente'),
					('Medico'),
					('Administrador'),
					('Tecnico'),
					('Administrativo');


 -- ================================================================

 
-- 						PACIENTES								 --
--				(datos para ingresarlos por la interfaz)         --
 
 -- ================================================================
 
  -- ================================================================
--                   GRUPO FAMILIAR 1
--                  (Titular + conyuge)
-- ================================================================

nombre: Juan
apellido: Perez
tipo de documento: dni 
nro de documento: 40111222
imagen del dni:
Genero: masculino
Fecha de nacimiento: 1980-05-10
Domicilio: Avellaneda 1234
Numero de contacto: 114001111
Cobertura de salud: UOM
Numero de afiliado: 40111222-00
Correo: juan.perez@gmail.com
Contraseña: 1234



nombre: Maria
apellido: Gonzalez
tipo de documento: dni 
nro de documento: 40111223
imagen del dni:
Genero: femenino
Fecha de nacimiento: 1982-03-18
Domicilio: Avellaneda 1234
Numero de contacto: 114001112
Cobertura de salud: UOM
Numero de afiliado: 40111223-01
Correo: maria.gonzalez@gmail.com
Contraseña: 1234



nombre: Lucia
apellido: Perez
tipo de documento: dni 
nro de documento: 40111225
imagen del dni: 
Genero: Femenino
Fecha de nacimiento: 2012-11-02
Domicilio: Avellaneda 1234
Numero de contacto: 114001111
Cobertura de salud: UOM
Numero de afiliado: 40111225-03
Correo: lucia.perez@gmail.com
Contraseña: 1234 

nombre: Carlos 
apellido: Perez
tipo de documento: dni 
nro de documento: 40111226
imagen del dni:
Genero: Masculino
Fecha de nacimiento: 2004-01-15
Domicilio: Avellaneda 1234
Numero de contacto: 114001113
Cobertura de salud: UOM
Numero de afiliado: 40111226-04
Correo: carlos.perez@gmail.com
Contraseña: 1234 

-- ================================================================
--                     GRUPO FAMILIAR 2 
--                  (Titular + Conviviente)
-- ================================================================

nombre: Carlos
apellido: Ramirez
tipo de documento: dni 
nro de documento: 50111222
imagen del dni:
Genero: Masculino
Fecha de nacimiento:  1978-02-20
Domicilio: Avellaneda 5678
Numero de contacto: 1140002222
Cobertura de salud: UOM
Numero de afiliado: 50111222-00
Correo: carlos.ramirez@gmail.com
Contraseña: 1234 

nombre: Laura 
apellido: Sosa
tipo de documento: dni 
nro de documento: 50111223
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1985-09-14
Domicilio: Avellaneda 5678
Numero de contacto: 1140002223
Cobertura de salud: UOM
Numero de afiliado: 50111223-02
Correo: laura.sosa@gmail.com
Contraseña: 1234

nombre: Martin 
apellido: Ramirez 
tipo de documento: dni
nro de documento: 50111224
imagen del dni:
Genero: Masculino
Fecha de nacimiento: 2013-04-10
Domicilio: Avellaneda 5678
Numero de contacto: 1140002222
Cobertura de salud: UOM
Numero de afiliado: 50111224-03
Correo: martin.ramirez@gmail.com
Contraseña: 1234

nombre: Agustina
apellido: Ramirez
tipo de documento: dni 
nro de documento: 50111225
imagen del dni:
Genero: Femenino 
Fecha de nacimiento: 2003-07-22
Domicilio: Avellaneda 5678
Numero de contacto: 1140002224
Cobertura de salud: UOM
Numero de afiliado: 50111225-04
Correo: agustina.ramirez@gmail.com
Contraseña: 1234

 -- ================================================================
--     GRUPO FAMILIAR 3 — Titular + Cónyuge (sin hijos)
-- ================================================================


nombre: Ricardo
apellido: Mendez
tipo de documento: dni 
nro de documento: 60111222
imagen del dni:
Genero: Masculino
Fecha de nacimiento: 1981-11-12
Domicilio: Avellaneda 9012
Numero de contacto: 1140003333
Cobertura de salud: UOM
Numero de afiliado: 60111222-00
Correo: ricardo.mendez@gmail.com
Contraseña: 1234


nombre: Patricia
apellido: Alvarez
tipo de documento: dni 
nro de documento: 60111223
imagen del dni:
Genero: Femenino 
Fecha de nacimiento: 1984-05-21
Domicilio: Avellaneda 9012
Numero de contacto: 1140003334
Cobertura de salud: UOM
Numero de afiliado: 60111223-01
Correo: patricia.alvarez@gmail.com
Contraseña: 1234



-- ================================================================
--     GRUPO FAMILIAR B — Titular + Conviviente (sin hijos)
-- ================================================================

nombre: Sergio
apellido: López
tipo de documento: dni 
nro de documento: 60111224
imagen del dni:
Genero: Masculino
Fecha de nacimiento: 1975-08-30
Domicilio: Avellaneda 3456
Numero de contacto: 1140004444
Cobertura de salud: UOM
Numero de afiliado: 60111224-00
Correo: sergio.lopez@gmail.com
Contraseña: 1234

nombre: Carolina
apellido: Suárez
tipo de documento: dni 
nro de documento: 60111225
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1980-12-05
Domicilio: Avellaneda 3456
Numero de contacto: 1140004445
Cobertura de salud: UOM
Numero de afiliado: 60111225-02
Correo: carolina.suarez@gmail.com
Contraseña: 1234

 
 -- ================================================================
--     TITULARES INDEPENDIENTES 
-- ================================================================

nombre: Marta
apellido: Quiroga
tipo de documento: dni 
nro de documento: 70111222
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1990-02-14
Domicilio: Avellaneda 7890
Numero de contacto: 1140005551
Cobertura de salud: UOM
Numero de afiliado: 70111222-00
Correo: marta.quiroga@gmail.com
Contraseña: 1234

nombre: Diego
apellido: Romero
tipo de documento: dni 
nro de documento: 70111223
imagen del dni:
Genero: Masculino
Fecha de nacimiento: 1988-09-09
Domicilio: Avellaneda 1123
Numero de contacto: 1140005552
Cobertura de salud: UOM
Numero de afiliado: 70111223-00
Correo: diego.romero@gmail.com
Contraseña: 1234

nombre: Vanesa 
apellido: Paz
tipo de documento: dni 
nro de documento: 70111224
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1977-03-28
Domicilio: Avellaneda 4456
Numero de contacto: 1140005553
Cobertura de salud: UOM
Numero de afiliado: 70111224-00
Correo: vanesa.paz@gmail.com
Contraseña: 1234

nombre: Jorge
apellido: Benitez
tipo de documento: dni 
nro de documento: 70111225
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1982-06-22
Domicilio: Avellaneda 7789
Numero de contacto: 1140005554
Cobertura de salud: UOM
Numero de afiliado: 70111225-00
Correo: jorge.benitez@gmail.com
Contraseña: 1234

nombre: Veronica
apellido: Martinez
tipo de documento: dni 
nro de documento: 70111226
imagen del dni:
Genero: Femenino
Fecha de nacimiento: 1995-10-31
Domicilio: Avellaneda 9901
Numero de contacto: 1140005555
Cobertura de salud: UOM
Numero de afiliado: 70111226-00
Correo: veronica.martinez@gmail.com
Contraseña: 1234
 
 
  -- ================================================================

 
-- 						MEDICOS								 --


 -- ================================================================
 -- ============================================
-- CARDIOLOGÍA — Martín Herrera
-- ============================================
CALL insertar_usuario_medico(
    'Martín', 'Herrera',
    'cardiologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',      -- 1234
    2, 1, 'Masculino', NULL,
    'CAR-1234', '1160112233'
);

-- ============================================
-- PEDIATRÍA — Daniela Muñoz
-- ============================================
CALL insertar_usuario_medico(
    'Daniela', 'Muñoz',
    'pediatria@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Femenino', NULL,
    'PED-1234', '1160223344'
);

-- ============================================
-- CLÍNICA MÉDICA — Luis Quintana
-- ============================================
CALL insertar_usuario_medico(
    'Luis', 'Quintana',
    'clinicomedico@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Masculino', NULL,
    'CLI-1234', '1160334455'
);

-- ============================================
-- DERMATOLOGÍA — Carla Benítez
-- ============================================
CALL insertar_usuario_medico(
    'Carla', 'Benítez',
    'dermatologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Femenino', NULL,
    'DER-1234', '1160445566'
);

-- ============================================
-- GASTROENTEROLOGÍA — Jorge Salvatierra
-- ============================================
CALL insertar_usuario_medico(
    'Jorge', 'Salvatierra',
    'gastroenterologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Masculino', NULL,
    'GAS-1234', '1160556677'
);

-- ============================================
-- GINECOLOGÍA — Valeria Castaño
-- ============================================
CALL insertar_usuario_medico(
    'Valeria', 'Castaño',
    'ginecologiao@clinicauom.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Femenino', NULL,
    'GIN-1234', '1160667788'
);

-- ============================================
-- NEUMOLOGÍA — Sebastián Delgado
-- ============================================
CALL insertar_usuario_medico(
    'Sebastián', 'Delgado',
    'neumologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Masculino', NULL,
    'NEU-1234', '1160778899'
);

-- ============================================
-- OTORRINOLARINGOLOGÍA — Natalia Rivas
-- ============================================
CALL insertar_usuario_medico(
    'Natalia', 'Rivas',
    'otorrinolaringologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Femenino', NULL,
    'OTO-1234', '1160889911'
);

-- ============================================
-- TRAUMATOLOGÍA — Rodrigo Ledesma
-- ============================================
CALL insertar_usuario_medico(
    'Rodrigo', 'Ledesma',
    'traumatologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Masculino', NULL,
    'TRA-1234', '1160991122'
);

-- ============================================
-- ODONTOLOGÍA — Julieta Fernández
-- ============================================
CALL insertar_usuario_medico(
    'Julieta', 'Fernández',
    'odontologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Femenino', NULL,
    'ODO-1234', '1170112233'
);

-- ============================================
-- OFTALMOLOGÍA — Gabriel Campos
-- ============================================
CALL insertar_usuario_medico(
    'Gabriel', 'Campos',
    'oftalmologia@gmail.com',
    '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq',
    2, 1, 'Masculino', NULL,
    'OFT-1234', '1170223344'
);

   -- ================================================================

 
-- 						ESPECIALIDADES								 --


 -- ================================================================
 INSERT INTO especialidades (nombre_especialidad) 
VALUES
    ('Cardiología'),
    ('Pediatría'),
    ('Clínica Médica'),
    ('Dermatología'),
    ('Gastroenterología'),
    ('Ginecología'),
    ('Neumología'),
    ('Otorrinolaringología'),
    ('Traumatología'),
    ('Odontología'),
    ('Oftalmología');

   
   
    -- ================================================================

 
-- 						MEDICO_ESPECIALIDAD						 --


 -- ================================================================
 
 INSERT INTO medico_especialidad (id_medico, id_especialidad)
VALUES
    (1, 1),   -- Martín Herrera → Cardiología
    (2, 2),   -- Daniela Muñoz → Pediatría
    (3, 3),   -- Luis Quintana → Clínica Médica
    (4, 4),   -- Carla Benítez → Dermatología
    (5, 5),   -- Jorge Salvatierra → Gastroenterología
    (6, 6),   -- Valeria Castaño → Ginecología
    (7, 7),   -- Sebastián Delgado → Neumología
    (8, 8),   -- Natalia Rivas → Otorrinolaringología
    (9, 9),   -- Rodrigo Ledesma → Traumatología
    (10, 10), -- Julieta Fernández → Odontología
    (11, 11); -- Gabriel Campos → Oftalmología

    
 -- ================================================================

 
-- 						RECURSOS						 --
--					(de tipo medico)					 --

 -- ================================================================
 
 INSERT INTO recursos (nombre, tipo, id_sede)
VALUES
    ('Dr. Martín Herrera', 'medico', 1),
    ('Dra. Daniela Muñoz', 'medico', 1),
    ('Dr. Luis Quintana', 'medico', 1),
    ('Dra. Carla Benítez', 'medico', 1),
    ('Dr. Jorge Salvatierra', 'medico', 1),
    ('Dra. Valeria Castaño', 'medico', 1),
    ('Dr. Sebastián Delgado', 'medico', 1),
    ('Dra. Natalia Rivas', 'medico', 1),
    ('Dr. Rodrigo Ledesma', 'medico', 1),
    ('Dra. Julieta Fernández', 'medico', 1),
    ('Dr. Gabriel Campos', 'medico', 1);
   
   
-- ================================================================

 
-- 						MEDICO_RECURSOS						 --


 -- ================================================================
 INSERT INTO medico_recursos (id_medico, id_recurso)
VALUES
    (1, 1),  -- Martín Herrera
    (2, 2),  -- Daniela Muñoz
    (3, 3),  -- Luis Quintana
    (4, 4),  -- Carla Benítez
    (5, 5),  -- Jorge Salvatierra
    (6, 6),  -- Valeria Castaño
    (7, 7),  -- Sebastián Delgado
    (8, 8),  -- Natalia Rivas
    (9, 9),  -- Rodrigo Ledesma
    (10, 10),-- Julieta Fernández
    (11, 11);-- Gabriel Campos

   
   
 -- ================================================================

 
-- 						ADMINISTRADORES						 --

   
 -- ================================================================
 INSERT INTO usuarios (
  nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni
)
VALUES
('Brisa', 'Arrom', 'brisa.arrom@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Femenino', NULL),
('Florencia', 'Garrafa', 'florencia.garrafa@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Femenino', NULL),
('Julieta', 'Rojas', 'julieta.rojas@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Femenino', NULL),
('Karin', 'Peña Baltodano', 'karin.penabaltodano@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Femenino', NULL),
('Yanina', 'Walendzik', 'yanina.walendzik@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Femenino', NULL),
('Brian', 'Ruiz', 'brian.ruiz@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Masculino', NULL),
('Carlos', 'Artaza', 'carlos.artaza@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Masculino', NULL),
('Javier', 'López', 'javier.lopez@gmail.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 3, 1, 'Masculino', NULL);
 

 -- ================================================================

 
-- 						RECURSOS						 --
--					(de tipo tecnico)					 --

 -- ================================================================

INSERT INTO recursos (nombre, tipo, id_sede)
VALUES
    ('Téc. Ana Lombardo', 'tecnico', 1),
    ('Téc. Pablo Giménez', 'tecnico', 1),
    ('Téc. Marcela Ruiz', 'tecnico', 1),
    ('Téc. Esteban Peralta', 'tecnico', 1),
    ('Téc. Rocío Torres', 'tecnico', 1),
    ('Téc. Diego Suárez', 'tecnico', 1),
    ('Téc. Florencia Acosta', 'tecnico', 1),
    ('Téc. Javier Montiel', 'tecnico', 1),
    ('Téc. Natalia Duarte', 'tecnico', 1),
    ('Téc. Leonardo Cabrera', 'tecnico', 1);
   
     -- ================================================================

 
-- 						TECNICOS						 --


 -- ================================================================
CALL insertar_usuario_tecnico('Ana', 'Lombardo', 'ana.lombardo@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Femenino', 'DNI Ana Lombardo', 'Téc. Ana Lombardo');
CALL insertar_usuario_tecnico('Pablo', 'Giménez', 'pablo.gimenez@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Masculino', 'DNI Pablo Giménez', 'Téc. Pablo Giménez');
CALL insertar_usuario_tecnico('Marcela', 'Ruiz', 'marcela.ruiz@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Femenino', 'DNI Marcela Ruiz', 'Téc. Marcela Ruiz');
CALL insertar_usuario_tecnico('Esteban', 'Peralta', 'esteban.peralta@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Masculino', 'DNI Esteban Peralta', 'Téc. Esteban Peralta');
CALL insertar_usuario_tecnico('Rocío', 'Torres', 'rocio.torres@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Femenino', 'DNI Rocío Torres', 'Téc. Rocío Torres');
CALL insertar_usuario_tecnico('Diego', 'Suárez', 'diego.suarez@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Masculino', 'DNI Diego Suárez', 'Téc. Diego Suárez');
CALL insertar_usuario_tecnico('Florencia', 'Acosta', 'florencia.acosta@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Femenino', 'DNI Florencia Acosta', 'Téc. Florencia Acosta');
CALL insertar_usuario_tecnico('Javier', 'Montiel', 'javier.montiel@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Masculino', 'DNI Javier Montiel', 'Téc. Javier Montiel');
CALL insertar_usuario_tecnico('Natalia', 'Duarte', 'natalia.duarte@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Femenino', 'DNI Natalia Duarte', 'Téc. Natalia Duarte');
CALL insertar_usuario_tecnico('Leonardo', 'Cabrera', 'leonardo.cabrera@ejemplo.com', '$2y$10$.0A2TZAEdN5CdEJWWjYK9ehKoIebf8ZG5RxWvXfZwb0ssTS6VmAOq', 1, 'Masculino', 'DNI Leonardo Cabrera', 'Téc. Leonardo Cabrera');

   

 
  -- ================================================================

 
-- 						ESTUDIOS						 --


 -- ================================================================
 INSERT INTO estudios (nombre, requiere_preparacion, instrucciones)
VALUES
    ('Electrocardiograma', 0, 'No requiere preparación especial.'),
    ('Control Pediátrico', 0, 'Traer carnet de vacunas'),
    ('Analisis de laboratorio', 1, 'Ayuno de 8 horas'),
    ('Dermatoscopia', 0, 'No aplicar cremas en la zona a evaluar.'),
    ('Endoscopia Digestiva', 1, 'Ayuno de 8 horas y suspender medicación según indicación.'),
    ('Ecografía Ginecológica', 1, 'Acudir con vejiga llena.'),
    ('Espirometría', 0, 'No fumar al menos 2 horas antes del estudio.'),
    ('Audiometría', 0, 'Evitar exposición a ruidos fuertes el día del estudio.'),
    ('Radiografía', 0, 'Quitar objetos metálicos en la zona a evaluar.'),
    ('Estudio Odontológico', 0, 'Mantener higiene bucal, sin enjuagues previos.'),
    ('Examen Oftalmológico', 0, 'Traer anteojos o lentes de contacto si se usan.');
   
   
   
  -- ================================================================

 
-- 						TECNICO_ESTUDIO						 --


 -- ================================================================
 INSERT INTO tecnico_estudio (id_tecnico, id_estudio) VALUES
(1, 1),  -- Ana Lombardo: Electrocardiograma
(2, 2),  -- Pablo Giménez: Control Pediátrico
(3, 4),  -- Marcela Ruiz: Dermatoscopia
(4, 5),  -- Esteban Peralta: Endoscopia Digestiva
(5, 7),  -- Rocío Torres: Espirometría
(6, 8),  -- Diego Suárez: Audiometría
(7, 9),  -- Florencia Acosta: Radiografía
(8, 10), -- Javier Montiel: Estudio Odontológico
(9, 11), -- Natalia Duarte: Examen Oftalmológico
(10, 3); -- Leonardo Cabrera: Analisis de laboratorio



    
  -- ================================================================

 
-- 						ESTADOS						 --


 -- ================================================================
 
 INSERT INTO estados (nombre_estado) 
VALUES 
  ('Pendiente'),
  ('Confirmado'),
  ('Atendido'),
  ('Cancelado'),
  ('Reprogramado'),
  ('Derivado');
 
  -- ================================================================

 
-- 						FERIADOS						 --


 -- ================================================================
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
					('2025-12-25', 'Navidad', ''),
					('2026-01-01', 'Año Nuevo', ''),
					('2026-03-24', 'Día Nacional de la Memoria por la Verdad y la Justicia', ''),
					('2026-03-03', 'Carnaval (Lunes)', ''),
					('2026-03-04', 'Carnaval (Martes)', ''),
					('2026-04-02', 'Día del Veterano y de los Caídos en la Guerra de Malvinas', ''),
					('2026-04-18', 'Viernes Santo', ''),
					('2026-05-01', 'Día del Trabajador', ''),
					('2026-05-25', 'Día de la Revolución de Mayo', ''),
					('2026-06-17', 'Paso a la Inmortalidad del Gral. Martín Miguel de Güemes', ''),
					('2026-06-20', 'Paso a la Inmortalidad del Gral. Manuel Belgrano', ''),
					('2026-07-09', 'Día de la Independencia', ''),
					('2026-08-17', 'Paso a la Inmortalidad del Gral. José de San Martín', ''),
					('2026-10-12', 'Día del Respeto a la Diversidad Cultural', ''),
					('2026-11-20', 'Día de la Soberanía Nacional', ''),
					('2026-12-08', 'Inmaculada Concepción de María', ''),
					('2026-12-25', 'Navidad', '');


  -- ================================================================

 
-- 						TIPO_NOTIFICACIONES						 --


 -- ================================================================
INSERT INTO tipo_notificaciones (nombre, descripcion) VALUES
  ('registro', 'Registro exitoso de usuario'),
  ('login', 'Inicio de sesión del usuario'),
  ('recupero', 'Solicitud de recuperación de contraseña'),
  ('restablecido', 'Contraseña restablecida exitosamente'),
  ('turno_medico', 'Confirmación de turno médico'),
  ('turno_estudio', 'Confirmación de turno de estudio'),
  ('cancelar_turno', 'Notificación de cancelación de turno'),
  ('recordatorio_turno', 'Recordatorio automático de turno');

