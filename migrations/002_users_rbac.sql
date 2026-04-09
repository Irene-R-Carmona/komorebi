-- ============================================================================
-- MIGRACIÓN 002: USUARIOS Y RBAC PURO
-- ============================================================================
-- Módulo: Usuarios, roles, permisos
-- Dependencias: 001_infrastructure.sql (users.cafe_id FK)
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    cafe_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    admin_notes TEXT DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    login_attempts TINYINT UNSIGNED DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    last_ip_address VARCHAR(45) DEFAULT NULL,
    deleted_at TIMESTAMP NULL COMMENT 'RGPD: soft delete, purga 30 días',
    anonymized_at TIMESTAMP NULL COMMENT 'Marca anonización email->deleted_{id}@anonymous.local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email),
    UNIQUE KEY users_uuid_unique (uuid),
    INDEX idx_users_cafe (cafe_id),
    INDEX idx_users_staff (cafe_id, is_active) COMMENT 'Listados personal por café',
    CONSTRAINT fk_users_cafe FOREIGN KEY (cafe_id) REFERENCES cafes (id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla de roles RBAC
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla de permisos RBAC
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    resource VARCHAR(50),
    action VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla relacional: roles <-> permisos (N:N)
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Tabla relacional: usuarios <-> roles (N:N)
CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by BIGINT UNSIGNED NULL COMMENT 'Usuario que asignó el rol (auditoría)',
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE
    SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_role (role_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Evento MySQL 8.4: Purga automática usuarios eliminados (RGPD 30 días)
DROP EVENT IF EXISTS evt_gdpr_purge_users;
-- Crear evento para purga diaria de usuarios eliminados (evitar BEGIN/END para compatibilidad con exec en PDO)
CREATE EVENT IF NOT EXISTS evt_gdpr_purge_users
ON SCHEDULE EVERY 1 DAY
DO
    DELETE FROM users
    WHERE deleted_at IS NOT NULL
      AND deleted_at < NOW() - INTERVAL 30 DAY;
SET FOREIGN_KEY_CHECKS = 1;
