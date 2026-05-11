-- ============================================================================
-- MIGRACIÓN 003: RESEÑAS Y AUDITORÍA
-- ============================================================================
-- Módulo: Reviews, calificaciones, logs de auditoría
-- Dependencias: 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Tabla de reseñas (1 reseña por usuario + café)
CREATE TABLE IF NOT EXISTS reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cafe_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    reservation_id BIGINT UNSIGNED NOT NULL COMMENT 'Reserva completada a la que pertenece esta reseña',
    rating TINYINT UNSIGNED NOT NULL CHECK (
        rating >= 1
        AND rating <= 5
    ),
    title VARCHAR(100),
    body TEXT,
    status ENUM ('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_review_per_reservation (reservation_id),
    INDEX idx_reviews_moderation_queue (status, created_at DESC) COMMENT 'Cola moderación',
    INDEX idx_reviews_cafe_rating (cafe_id, status, rating) COMMENT 'Cálculo rating_avg',
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_reservation (reservation_id),
    CONSTRAINT fk_reviews_cafes FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla de auditoría general (cambios críticos)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id BIGINT UNSIGNED,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retention_until TIMESTAMP AS (DATE_ADD(created_at, INTERVAL 1 YEAR)) STORED COMMENT 'RGPD: purga automática 1 año',
    INDEX idx_audit_logs_user (user_id),
    INDEX idx_audit_logs_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_timestamp (created_at DESC),
    INDEX idx_retention (retention_until) COMMENT 'Job purga RGPD',
    CONSTRAINT fk_audit_logs_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla de logs de autenticación
CREATE TABLE IF NOT EXISTS auth_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED,
    event_type ENUM (
        'login',
        'logout',
        'failed_login',
        'password_reset',
        'email_verified',
        'session_revoked',
        'lockout'
    ) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_name VARCHAR(100),
    success BOOLEAN DEFAULT TRUE,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retention_until TIMESTAMP AS (DATE_ADD(created_at, INTERVAL 1 YEAR)) STORED COMMENT 'RGPD: purga automática 1 año',
    INDEX idx_auth_audit_logs_user (user_id),
    INDEX idx_auth_audit_logs_event (event_type),
    INDEX idx_created (created_at DESC),
    INDEX idx_retention (retention_until) COMMENT 'Job purga RGPD',
    CONSTRAINT fk_auth_audit_logs_users FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Eventos MySQL 8.4: Purga automática logs (RGPD 1 año retención)
DROP EVENT IF EXISTS evt_purge_audit_logs;
CREATE EVENT evt_purge_audit_logs ON SCHEDULE EVERY 1 WEEK DO
DELETE FROM audit_logs
WHERE retention_until < NOW();
DROP EVENT IF EXISTS evt_purge_auth_logs;
CREATE EVENT evt_purge_auth_logs ON SCHEDULE EVERY 1 WEEK DO
DELETE FROM auth_audit_logs
WHERE retention_until < NOW();
SET FOREIGN_KEY_CHECKS = 1;
