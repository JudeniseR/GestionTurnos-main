----------------------------------
-- FECHA: 25/09
----------------------------------

use gestionturnos;

ALTER TABLE turnos ADD COLUMN id_recurso INT DEFAULT NULL;
ALTER TABLE turnos ADD CONSTRAINT fk_turno_recurso FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso);





----------------------------------
-- FECHA: 1/10
----------------------------------

UPDATE agenda
SET disponible = 1
WHERE id_medico = 1 AND fecha = '2025-10-10';


UPDATE agenda
SET hora_inicio = '09:00:00', hora_fin = '13:00:00'
WHERE id_medico = 1 AND fecha = '2025-10-10';





INSERT INTO agenda (id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible)
VALUES
(1, 1, '2025-10-15', '09:00:00', '12:00:00', 1);


INSERT INTO agenda (id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible)
VALUES
(1, 1, '2025-10-18', '10:00:00', '12:00:00', 1);


INSERT INTO turnos (id_medico, id_paciente, fecha, hora, id_estado)
VALUES
(1, 1, '2025-10-18', '10:00:00', 2),  -- 2 = confirmado
(1, 2, '2025-10-18', '10:30:00', 2);


INSERT INTO agenda_bloqueos (id_medico, fecha, tipo, motivo)
VALUES (1, '2025-10-20', 'dia', 'Licencia');


----------------------------------
-- FECHA: 03/10
----------------------------------


-- ANALIZAR SI ES NECESARIA Y CUAL ES SU USO
CREATE TABLE IF NOT EXISTS estudios_recursos (
  id_estudio INT NOT NULL,
  id_recurso INT NOT NULL,
  PRIMARY KEY (id_estudio, id_recurso),
  CONSTRAINT fk_er_estudio FOREIGN KEY (id_estudio) REFERENCES estudios(id_estudio) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_er_recurso FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso) ON DELETE CASCADE ON UPDATE CASCADE
);

-- PARA SABER QUÉ TECNICOS PUEDEN ATENDER LOS ESTUDIOS
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

-- SE AGREGA LA RELACION ENTRE tecnicos Y recursos
ALTER TABLE tecnicos 
ADD COLUMN id_recurso INT DEFAULT NULL,
ADD CONSTRAINT fk_tecnico_id_recurso 
  FOREIGN KEY (id_recurso) 
  REFERENCES recursos(id_recurso)
  ON UPDATE CASCADE 
  ON DELETE SET NULL;

----------------------------------
-- PARA QUE FUNCIONE LA DISPONIBILIDAD DE UN ESTUDIO, PREVIAMENTE DEBEN ESTAR CARGADAS LAS SIGUIENTES TABLAS: 
-- tecnicos, estudios, tecnico_estudio, recursos y agenda.
----------------------------------


ALTER TABLE agenda ADD COLUMN id_estudio INT DEFAULT NULL;

ALTER TABLE agenda ADD CONSTRAINT fk_agenda_id_estudio
    FOREIGN KEY (id_estudio) REFERENCES estudios(id_estudio)
    ON DELETE SET NULL ON UPDATE CASCADE;