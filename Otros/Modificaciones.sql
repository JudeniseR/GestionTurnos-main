----------------------------------
-- FECHA: 25/09
----------------------------------

use gestionturnos;

ALTER TABLE turnos ADD COLUMN id_recurso INT DEFAULT NULL;
ALTER TABLE turnos ADD CONSTRAINT fk_turno_recurso FOREIGN KEY (id_recurso) REFERENCES recursos(id_recurso);
