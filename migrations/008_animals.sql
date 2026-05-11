-- ============================================================================
-- MIGRACIÓN 008: ANIMALES Y BIENESTAR
-- ============================================================================
-- Módulo: Animales, especies, reglas, interacciones, salud
-- Dependencias: 001_infrastructure.sql, 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Reglas por especie (relaciones human:animal, tiempo máximo, descanso)
CREATE TABLE IF NOT EXISTS species_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    species_key VARCHAR(50) NOT NULL,
    human_ratio DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    max_consecutive_minutes INT UNSIGNED NOT NULL,
    min_rest_minutes INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_species_rules_species_key (species_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Animales en los cafés
CREATE TABLE IF NOT EXISTS animals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cafe_id BIGINT UNSIGNED NOT NULL,
    current_zone_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    species_type VARCHAR(50) NOT NULL,
    birth_date DATE NULL COMMENT 'Fecha de nacimiento. NULL si se desconoce.',
    age_years TINYINT UNSIGNED NULL COMMENT 'Edad en años. NULL si sin birth_date. Se calcula externamente.',
    personality VARCHAR(100),
    description TEXT,
    interaction_level TINYINT UNSIGNED DEFAULT 3,
    attributes JSON,
    image_url VARCHAR(255),
    current_status ENUM ('active', 'resting', 'sick', 'on_vacation', 'retired', 'quarantine', 'monitoring') NOT NULL DEFAULT 'active',
    last_health_check TIMESTAMP NULL COMMENT 'Última revisión veterinaria o checklist keeper',
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete RGPD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_animals_cafe (cafe_id),
    INDEX idx_animals_species (species_type),
    INDEX idx_animals_keeper_dashboard (cafe_id, current_status, last_health_check) COMMENT 'Alertas keeper',
    INDEX idx_animals_active (cafe_id, deleted_at) COMMENT 'Filtro activos',
    CONSTRAINT fk_animals_cafe FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE,
    CONSTRAINT fk_animals_zone FOREIGN KEY (current_zone_id) REFERENCES cafe_zones (id) ON DELETE
    SET NULL,
    CONSTRAINT fk_animals_species_rules FOREIGN KEY (species_type) REFERENCES species_rules (species_key) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Log de cambios de estado de animales
CREATE TABLE IF NOT EXISTS animal_status_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM ('active', 'resting', 'sick', 'on_vacation', 'retired', 'quarantine', 'monitoring') NULL,
    new_status ENUM ('active', 'resting', 'sick', 'on_vacation', 'retired', 'quarantine', 'monitoring') NOT NULL,
    reason TEXT,
    logged_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status_timeline (animal_id, created_at DESC) COMMENT 'Timeline historial salud',
    CONSTRAINT fk_animal_status_log_animals FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_status_log_users FOREIGN KEY (logged_by) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Incidentes con animales
CREATE TABLE IF NOT EXISTS animal_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NULL COMMENT 'Reserva durante la que ocurrió el incidente (opcional).',
    incident_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    resolution TEXT NULL COMMENT 'Notas de resolución del incidente',
    severity ENUM ('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM ('open', 'resolved', 'monitoring', 'archived') NOT NULL DEFAULT 'open',
    resolved_at TIMESTAMP NULL COMMENT 'Fecha resolución incidente',
    reported_by BIGINT UNSIGNED,
    resolved_by BIGINT UNSIGNED NULL COMMENT 'Usuario que resolvió',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_animal_incidents_animal (animal_id),
    INDEX idx_incidents_open (severity, created_at DESC, resolved_at) COMMENT 'Alertas pendientes',
    INDEX idx_animal_incidents_status (animal_id, status),
    CONSTRAINT fk_animal_incidents_animals FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_incidents_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_animal_incidents_reporter FOREIGN KEY (reported_by) REFERENCES users (id) ON DELETE
    SET NULL,
        CONSTRAINT fk_animal_incidents_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Relaciones entre animales (amistad, familia, enemistad)
CREATE TABLE IF NOT EXISTS animal_relationships (
    animal_a BIGINT UNSIGNED NOT NULL,
    animal_b BIGINT UNSIGNED NOT NULL,
    type ENUM ('friendly', 'hostile', 'family') NOT NULL,
    PRIMARY KEY (animal_a, animal_b),
    CONSTRAINT fk_animal_relationships_a FOREIGN KEY (animal_a) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_relationships_b FOREIGN KEY (animal_b) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Sesiones de interacción usuario-animal
CREATE TABLE IF NOT EXISTS interaction_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED DEFAULT NULL,
    start_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME,
    intensity ENUM ('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sess_animal (animal_id),
    INDEX idx_sess_res (reservation_id),
    INDEX idx_sessions_daily_calc (animal_id, start_time, end_time) COMMENT 'Cálculo duración vs reglas',
    CONSTRAINT fk_interaction_sessions_animals FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE,
    CONSTRAINT fk_interaction_sessions_reservations FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Evento MySQL 8.4: Purga automática animals eliminados (RGPD 30 días)
DROP EVENT IF EXISTS evt_cleanup_old_animals;
CREATE EVENT evt_cleanup_old_animals ON SCHEDULE EVERY 1 MONTH DO
DELETE FROM animals
WHERE deleted_at IS NOT NULL
    AND deleted_at < NOW() - INTERVAL 30 DAY;
SET FOREIGN_KEY_CHECKS = 1;
