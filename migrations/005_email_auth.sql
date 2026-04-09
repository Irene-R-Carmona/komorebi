-- ============================================================================
-- MIGRACIÓN 005: EMAIL, AUTENTICACIÓN Y SESIONES
-- ============================================================================
-- Módulo: Email verification, password reset, session management, rate limiting
-- Dependencias: 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Tokens de verificación de email
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    CONSTRAINT fk_evt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tokens de recuperación de contraseña
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_unused (used_at),
    CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Sesiones activas (para listar y revocar)
CREATE TABLE IF NOT EXISTS active_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    device_name VARCHAR(100),
    last_activity DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revoke_reason VARCHAR(100),
    revoked_by BIGINT UNSIGNED,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity DESC),
    CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_as_revoked_by FOREIGN KEY (revoked_by) REFERENCES users (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Rate limiting (para login, registro, etc)
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    attempt_count INT UNSIGNED DEFAULT 1,
    last_attempt DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_action_identifier (action, identifier),
    INDEX idx_locked (locked_until),
    INDEX idx_action (action)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 1;
