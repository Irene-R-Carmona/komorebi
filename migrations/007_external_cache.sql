-- ============================================================================
-- MIGRACIÓN 007: CACHÉ EXTERNAS (Open-Meteo, Nager.Date)
-- ============================================================================
-- Módulo: Cache de APIs externas, auditoría de llamadas
-- Dependencias: 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Caché de datos meteorológicos (Open-Meteo)
CREATE TABLE IF NOT EXISTS weather_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10, 6) NOT NULL,
    longitude DECIMAL(10, 6) NOT NULL,
    timezone VARCHAR(50),
    data JSON NOT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_coords (latitude, longitude),
    INDEX idx_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Caché de festivos por país (Nager.Date)
CREATE TABLE IF NOT EXISTS holiday_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year INT UNSIGNED NOT NULL,
    country VARCHAR(2) NOT NULL DEFAULT 'JP',
    holidays JSON NOT NULL,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE KEY uk_year_country (year, country),
    INDEX idx_expires (expires_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Auditoría de llamadas a APIs externas
CREATE TABLE IF NOT EXISTS api_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INT UNSIGNED,
    response_time INT UNSIGNED,
    cached BOOLEAN DEFAULT FALSE,
    user_id BIGINT UNSIGNED,
    ip_address VARCHAR(45),
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint_date (endpoint, requested_at),
    INDEX idx_api_audit_logs_user (user_id),
    CONSTRAINT fk_api_audit_logs_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
