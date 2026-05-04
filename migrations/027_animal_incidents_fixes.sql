-- Migration 027: Fix animal_incidents schema
-- Adds 'critical' severity, resolution notes column

ALTER TABLE animal_incidents
    MODIFY COLUMN severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    ADD COLUMN resolution TEXT NULL COMMENT 'Notas de resolución del incidente' AFTER description;
