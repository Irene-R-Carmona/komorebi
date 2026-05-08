-- ============================================
-- Migration: 009 - Sistema de Configuración
-- Descripción: Sistema centralizado de configuración key-value
--
-- FUENTE DE VERDAD (source of truth):
--   Los valores de configuración dinámica de la aplicación se leen
--   SIEMPRE desde esta tabla `settings`, NO desde los DEFAULT de columna
--   del esquema. Los DEFAULT SQL en otras migraciones son únicamente
--   valores de arranque estructurales para inserciones directas en DB.
--
--   Referencias cruzadas:
--     002_users_rbac.sql  → login_attempts DEFAULT 0     ← ver settings: max_login_attempts, lockout_duration
--     004_reservations.sql → guest_count DEFAULT 1       ← ver settings: max_guests_per_reservation
--     004_reservations.sql → reservations parámetros     ← ver settings group 'reservations'
-- ============================================

-- ============================================
-- UP: Aplicar migración
-- ============================================

-- Tabla de configuraciones del sistema
CREATE TABLE IF NOT EXISTS settings
(
    `key`         VARCHAR(100) PRIMARY KEY COMMENT 'Clave única de configuración',
    `value`       TEXT         NOT NULL COMMENT 'Valor de la configuración (JSON para tipos complejos)',
    `type`        ENUM ('string', 'integer', 'boolean', 'json') DEFAULT 'string' COMMENT 'Tipo de dato',
    `group_name`  VARCHAR(50)  NOT NULL COMMENT 'Grupo de configuración (general, email, reservations, security)',
    `description` VARCHAR(255) COMMENT 'Descripción del setting',
    `is_public`   TINYINT(1)            DEFAULT 0 COMMENT 'Si es visible en frontend (0=solo admin)',
    `created_at`  TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_settings_group (group_name),
    INDEX idx_settings_public (is_public)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
    COMMENT = 'Configuraciones globales del sistema';

-- Insertar configuraciones por defecto (idempotente)
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group_name`, `description`, `is_public`)
VALUES
    -- General
    ('site_name', 'Komorebi Café', 'string', 'general', 'Nombre del sitio', 1),
    ('site_description', 'Tu café de animales favorito', 'string', 'general', 'Descripción del sitio', 1),
    ('maintenance_mode', '0', 'boolean', 'general', 'Modo mantenimiento (1=activado, 0=desactivado)', 0),
    ('timezone', 'Europe/Madrid', 'string', 'general', 'Zona horaria del sistema', 0),
    ('default_language', 'es', 'string', 'general', 'Idioma por defecto', 1),
    ('items_per_page', '10', 'integer', 'general', 'Elementos por página en listados', 0),

    -- Email
    ('smtp_enabled', '0', 'boolean', 'email', 'Activar envío de emails', 0),
    ('smtp_host', '', 'string', 'email', 'Servidor SMTP', 0),
    ('smtp_port', '587', 'integer', 'email', 'Puerto SMTP', 0),
    ('smtp_username', '', 'string', 'email', 'Usuario SMTP', 0),
    ('smtp_password', '', 'string', 'email', 'Contraseña SMTP (encriptada)', 0),
    ('smtp_encryption', 'tls', 'string', 'email', 'Encriptación (tls/ssl/none)', 0),
    ('from_email', 'noreply@komorebi.cafe', 'string', 'email', 'Email remitente', 0),
    ('from_name', 'Komorebi Café', 'string', 'email', 'Nombre remitente', 0),
    ('support_email', 'soporte@komorebi.cafe', 'string', 'email', 'Email de soporte', 1),

    -- Reservas
    ('reservations_enabled', '1', 'boolean', 'reservations', 'Activar sistema de reservas', 1),
    ('max_advance_days', '30', 'integer', 'reservations', 'Días máximos de antelación para reservar', 0),
    ('min_advance_hours', '2', 'integer', 'reservations', 'Horas mínimas de antelación', 0),
    ('cancellation_hours', '24', 'integer', 'reservations', 'Horas mínimas para cancelar sin penalización', 0),
    ('max_guests_per_reservation', '10', 'integer', 'reservations', 'Máximo de personas por reserva', 1),
    ('require_deposit', '0', 'boolean', 'reservations', 'Requerir depósito para reservar', 0),
    ('deposit_percentage', '20', 'integer', 'reservations', 'Porcentaje de depósito requerido', 0),
    ('reservation_duration', '60', 'integer', 'reservations', 'Duración por defecto en minutos', 1),

    -- Seguridad
    ('session_lifetime', '120', 'integer', 'security', 'Duración de sesión en minutos', 0),
    ('max_login_attempts', '5', 'integer', 'security', 'Máximo intentos de login fallidos', 0),
    ('lockout_duration', '15', 'integer', 'security', 'Duración del bloqueo en minutos', 0),
    ('require_email_verification', '1', 'boolean', 'security', 'Requerir verificación de email', 0),
    ('password_min_length', '8', 'integer', 'security', 'Longitud mínima de contraseña', 0),
    ('password_require_special', '1', 'boolean', 'security', 'Requerir caracteres especiales en contraseña', 0),
    ('enable_2fa', '0', 'boolean', 'security', 'Activar autenticación de dos factores', 0),

    -- Reviews
    ('reviews_enabled', '1', 'boolean', 'reviews', 'Activar sistema de reseñas', 1),
    ('require_reservation_to_review', '0', 'boolean', 'reviews', 'Requerir reserva previa para reseñar', 0),
    ('moderate_reviews', '1', 'boolean', 'reviews', 'Moderar reseñas antes de publicar', 0),
    ('max_reviews_per_user', '3', 'integer', 'reviews', 'Máximo de reseñas por usuario por café', 0);

-- ============================================
-- DOWN: Revertir migración (comentado)
-- ============================================
-- DROP TABLE IF EXISTS settings;
