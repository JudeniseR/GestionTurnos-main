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
