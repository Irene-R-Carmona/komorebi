-- 019: Añadir columnas status y resolved_at a animal_incidents
-- Necesario para eliminar la introspección dinámica de information_schema
-- en Animal::resolveIncident() — riesgo de seguridad activo.

ALTER TABLE animal_incidents
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'open',
    ADD COLUMN IF NOT EXISTS resolved_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_animal_incidents_status ON animal_incidents(status);
