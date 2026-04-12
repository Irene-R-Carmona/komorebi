-- Fix: supervisor_assignments columnas INT → BIGINT para consistencia
-- Todas las demás tablas usan BIGINT UNSIGNED. Ver migration 016_supervisor_assignments.sql

ALTER TABLE supervisor_assignments
  MODIFY COLUMN id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  MODIFY COLUMN supervisor_id  BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN reservation_id BIGINT UNSIGNED NOT NULL,
  MODIFY COLUMN cafe_id        BIGINT UNSIGNED NOT NULL;
