-- ============================================================================
-- MIGRACIÓN 001: INFRAESTRUCTURA BASE
-- ============================================================================
-- Módulo: Cafés, zonas, trackers, geolocalización
-- Dependencias: NINGUNA
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Tabla de cafés (14 sedes con 14 especies diferentes)
CREATE TABLE IF NOT EXISTS cafes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    japanese_name VARCHAR(100) DEFAULT NULL,
    slug VARCHAR(120) NOT NULL,
    location VARCHAR(255) NOT NULL,
    category ENUM ('lounge', 'playroom', 'farm', 'zen') NOT NULL,
    animal_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    price_per_hour INT UNSIGNED NOT NULL,
    rating_avg DECIMAL(3, 2) DEFAULT 0.00 COMMENT 'Calculado desde reviews aprobadas',
    rating_count INT UNSIGNED DEFAULT 0,
    opening_time TIME NOT NULL,
    closing_time TIME NOT NULL,
    capacity_max INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'Europe/Madrid',
    city VARCHAR(100) DEFAULT NULL COMMENT 'Ciudad española donde se ubica el café',
    themed_district VARCHAR(50) DEFAULT NULL COMMENT 'Barrio de Tokio de referencia estética',
    min_age_years TINYINT UNSIGNED DEFAULT NULL COMMENT 'Edad mínima para visitar (NULL = sin restricción)',
    is_active BOOLEAN DEFAULT TRUE,
    has_reservations BOOLEAN DEFAULT TRUE,
    image_url VARCHAR(255) DEFAULT NULL,
    deleted_at TIMESTAMP NULL COMMENT 'Soft delete RGPD: purga 30 días',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cafes_slug (slug),
    INDEX idx_cafes_category (category),
    INDEX idx_cafes_public_search (is_active, category, animal_type),
    INDEX idx_cafes_coordinates (latitude, longitude)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Zonas operativas dentro de cada café
CREATE TABLE IF NOT EXISTS cafe_zones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cafe_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    type ENUM (
        'reception',
        'cafe',
        'interaction',
        'rest',
        'quarantine'
    ) NOT NULL,
    status ENUM ('clean', 'occupied', 'dirty', 'maintenance') DEFAULT 'clean',
    capacity INT UNSIGNED NOT NULL DEFAULT 0,
    requires_briefing BOOLEAN DEFAULT FALSE,
    requires_shoes_off BOOLEAN DEFAULT FALSE,
    sort_order TINYINT UNSIGNED DEFAULT 0 COMMENT 'Orden UI recepción',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_zones_cafe (cafe_id),
    INDEX idx_zones_available (cafe_id, status) COMMENT 'Búsqueda zonas limpias check-in',
    CONSTRAINT fk_cafe_zones_cafes FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Trackers (tokens, beepers, NFC) para sistema de comandas
CREATE TABLE IF NOT EXISTS trackers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cafe_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    type ENUM ('token', 'beeper', 'nfc', 'qr') NOT NULL DEFAULT 'token' COMMENT 'token=físico, beeper=sonoro, nfc=tarjeta, qr=impreso',
    status ENUM ('available', 'in_use', 'lost') DEFAULT 'available',
    last_assigned_at TIMESTAMP NULL COMMENT 'Última asignación',
    last_assigned_reservation_id BIGINT UNSIGNED NULL COMMENT 'Última reserva asignada',
    notes TEXT NULL COMMENT 'Mantenimiento o pérdidas',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_trackers_cafe_code (cafe_id, code),
    INDEX idx_trackers_cafe (cafe_id),
    INDEX idx_trackers_available (cafe_id, status) COMMENT 'Asignación rápida disponibles',
    CONSTRAINT fk_trackers_cafes FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Evento MySQL 8.4: Purga automática cafés eliminados (RGPD 30 días)
DROP EVENT IF EXISTS evt_cleanup_deleted_cafes;
CREATE EVENT evt_cleanup_deleted_cafes ON SCHEDULE EVERY 1 DAY DO
DELETE FROM cafes
WHERE deleted_at IS NOT NULL
    AND deleted_at < NOW() - INTERVAL 30 DAY;
SET FOREIGN_KEY_CHECKS = 1;
