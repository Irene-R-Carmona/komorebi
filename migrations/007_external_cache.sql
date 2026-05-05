-- ============================================================================
-- MIGRACIÓN 007: AUDITORÍA DE APIS EXTERNAS
-- ============================================================================
-- Módulo: Auditoría de llamadas a APIs externas
-- Dependencias: 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
-- Nota: weather_cache y holiday_cache eliminados — usan Redis PSR-6 Cache
--       y constantes PHP in-memory respectivamente.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
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
