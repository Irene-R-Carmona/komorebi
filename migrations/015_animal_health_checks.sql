-- 015_animal_health_checks.sql
-- Sistema de chequeos de salud diarios para animales
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- ============================================================================
-- TABLA: animal_health_checks
-- ============================================================================
-- Almacena chequeos diarios de salud realizados por keepers
-- Permite tracking de métricas vitales y detección de alertas
CREATE TABLE IF NOT EXISTS animal_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Relaciones
    animal_id BIGINT UNSIGNED NOT NULL COMMENT 'Animal examinado',
    checked_by BIGINT UNSIGNED NOT NULL COMMENT 'Keeper que realizó el chequeo',
    -- Fecha y hora
    check_date DATE NOT NULL COMMENT 'Fecha del chequeo (formato YYYY-MM-DD)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de registro',
    -- Métricas físicas
    weight_kg DECIMAL(5, 2) NULL COMMENT 'Peso en kilogramos (ej: 4.50)',
    temperature_c DECIMAL(4, 2) NULL COMMENT 'Temperatura corporal en °C (ej: 38.5)',
    -- Estado general (ENUM para consistencia)
    appetite ENUM('normal', 'reduced', 'none') DEFAULT 'normal' COMMENT 'Nivel de apetito observado',
    energy_level ENUM('high', 'normal', 'low') DEFAULT 'normal' COMMENT 'Nivel de energía del animal',
    coat_condition ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good' COMMENT 'Condición del pelaje',
    -- Checks booleanos (observaciones binarias)
    eyes_clear BOOLEAN DEFAULT TRUE COMMENT 'Ojos claros sin secreciones',
    breathing_normal BOOLEAN DEFAULT TRUE COMMENT 'Respiración normal sin dificultad',
    mobility_normal BOOLEAN DEFAULT TRUE COMMENT 'Movilidad normal sin cojera',
    -- Observaciones textuales
    notes TEXT NULL COMMENT 'Notas adicionales del keeper',
    alerts JSON NULL COMMENT 'Array JSON de alertas detectadas automáticamente',
    -- Claves foráneas (Cascada: eliminar checks si se elimina animal; Restrict: no permitir eliminar keeper con checks registrados)
    CONSTRAINT fk_animal_health_checks_animals FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_health_checks_users FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE RESTRICT,
    -- Índices para performance
    INDEX idx_animal_date (animal_id, check_date),
    INDEX idx_check_date (check_date),
    INDEX idx_checked_by (checked_by, check_date),
    INDEX idx_health_check_date (check_date DESC, animal_id),
    -- Constraint: Un solo chequeo por animal por día
    UNIQUE KEY uk_animal_check_date (animal_id, check_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Chequeos diarios de salud animal realizados por keepers';
-- ============================================================================
-- VISTA: health_checks_today
-- ============================================================================
-- Vista auxiliar para dashboard: chequeos realizados hoy con info del animal
CREATE OR REPLACE VIEW health_checks_today AS
SELECT hc.id,
    hc.animal_id,
    a.name AS animal_name,
    a.species_type,
    a.current_status,
    hc.check_date,
    hc.checked_by,
    u.name AS keeper_name,
    hc.weight_kg,
    hc.temperature_c,
    hc.appetite,
    hc.energy_level,
    hc.coat_condition,
    hc.eyes_clear,
    hc.breathing_normal,
    hc.mobility_normal,
    hc.alerts,
    hc.created_at
FROM animal_health_checks hc
    INNER JOIN animals a ON hc.animal_id = a.id
    INNER JOIN users u ON hc.checked_by = u.id
WHERE hc.check_date = CURDATE()
    AND a.deleted_at IS NULL
ORDER BY hc.created_at DESC;
-- ============================================================================
-- VISTA: animals_pending_check_today
-- ============================================================================
-- Animales que aún no tienen chequeo registrado hoy
CREATE OR REPLACE VIEW animals_pending_check_today AS
SELECT a.id AS animal_id,
    a.name AS animal_name,
    a.species_type,
    a.current_status,
    a.cafe_id,
    c.name AS cafe_name,
    a.last_health_check
FROM animals a
    INNER JOIN cafes c ON a.cafe_id = c.id
    LEFT JOIN animal_health_checks hc ON hc.animal_id = a.id
    AND hc.check_date = CURDATE()
WHERE a.deleted_at IS NULL
    AND a.current_status IN ('active', 'monitoring')
    AND hc.id IS NULL
ORDER BY a.last_health_check IS NULL DESC,
    a.last_health_check,
    a.name;
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================================
-- FIN MIGRACIÓN 015
-- ============================================================================
