-- 019: Añadir columnas status y resolved_at a animal_incidents
-- Necesario para eliminar la introspección dinámica de information_schema
-- en Animal::resolveIncident() — riesgo de seguridad activo.
-- Nota: ADD COLUMN IF NOT EXISTS no es soportado en MySQL 8.x (solo MariaDB).
-- apply-db.php captura "Duplicate column name" / "Duplicate key name" como idempotente.

ALTER TABLE animal_incidents
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open',
    ADD COLUMN resolved_at DATETIME NULL;

CREATE INDEX idx_animal_incidents_status ON animal_incidents(status);
