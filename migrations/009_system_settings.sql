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

-- Limpiar claves renombradas de versiones anteriores (idempotente)
-- Se renombraron para coherencia con SystemSettingsSeeder.php
DELETE FROM settings WHERE `key` IN (
    'from_email', 'from_name',
    'reservation_duration',
    'password_min_length', 'password_require_special',
    'require_reservation_to_review', 'moderate_reviews',
    'smtp_enabled', 'require_deposit', 'deposit_percentage'
);

-- Insertar configuraciones de producción por defecto (idempotente)
-- SystemSettingsSeeder.php usa ON DUPLICATE KEY UPDATE → sobreescribe en dev (make db-seed).
INSERT IGNORE INTO settings (`key`, `value`, `type`, `group_name`, `description`, `is_public`)
VALUES
    -- General
    ('site_name',            'Komorebi Café',              'string',  'general',      'Nombre del sitio',                                        1),
    ('site_description',     'Red de cafeterías temáticas de animales con estética japonesa en España', 'string',  'general',      'Descripción del sitio',                                   1),
    ('maintenance_mode',     '0',                          'boolean', 'general',      'Modo mantenimiento (1=activado, 0=desactivado)',           0),
    ('timezone',             'Europe/Madrid',              'string',  'general',      'Zona horaria del sistema',                                0),
    ('default_language',     'es',                         'string',  'general',      'Idioma por defecto',                                      1),
    ('items_per_page',       '25',                         'integer', 'general',      'Elementos por página en listados',                        0),

    -- Email
    ('smtp_host',            '',                           'string',  'email',        'Servidor SMTP',                                           0),
    ('smtp_port',            '587',                        'integer', 'email',        'Puerto SMTP',                                             0),
    ('smtp_username',        '',                           'string',  'email',        'Usuario SMTP',                                            0),
    ('smtp_password',        '',                           'string',  'email',        'Contraseña SMTP',                                         0),
    ('smtp_encryption',      'tls',                        'string',  'email',        'Encriptación: none, tls, ssl',                            0),
    ('mail_from_address',    'noreply@komorebi.cafe',      'string',  'email',        'Email remitente',                                         0),
    ('mail_from_name',       'Komorebi Café',              'string',  'email',        'Nombre remitente',                                        0),
    ('support_email',        'soporte@komorebi.cafe',      'string',  'email',        'Email de soporte',                                        1),

    -- Reservas
    ('reservations_enabled',        '1',  'boolean', 'reservations', 'Activar sistema de reservas',                             1),
    ('max_advance_days',            '30', 'integer', 'reservations', 'Días máximos de antelación para reservar',                0),
    ('min_advance_hours',           '2',  'integer', 'reservations', 'Horas mínimas de antelación',                             0),
    ('cancellation_hours',          '24', 'integer', 'reservations', 'Horas de antelación necesarias para cancelar sin cargo. Por debajo de este umbral se aplica cancellation_fee_percentage.',            0),
    ('max_guests_per_reservation',  '10', 'integer', 'reservations', 'Máximo de personas por reserva',                         1),
    ('default_duration_minutes',    '60', 'integer', 'reservations', 'Duración por defecto en minutos',                        1),
    ('send_confirmation_email',     '1',  'boolean', 'reservations', 'Enviar email de confirmación al reservar',                0),
    ('send_reminder_email',         '1',  'boolean', 'reservations', 'Enviar email recordatorio 24h antes',                    0),

    -- Seguridad
    ('session_lifetime',            '120', 'integer', 'security', 'Duración de sesión en minutos',                            0),
    ('max_login_attempts',          '5',   'integer', 'security', 'Máximo intentos de login fallidos antes de bloquear',      0),
    ('lockout_duration',            '15',  'integer', 'security', 'Duración del bloqueo de cuenta en minutos',                0),
    ('require_email_verification',  '1',   'boolean', 'security', 'Requerir verificación de email al registrarse',            0),
    ('min_password_length',         '8',   'integer', 'security', 'Longitud mínima de contraseña',                           0),
    ('password_requires_uppercase', '1',   'boolean', 'security', 'Contraseña requiere al menos una mayúscula',               0),
    ('password_requires_number',    '1',   'boolean', 'security', 'Contraseña requiere al menos un número',                   0),
    ('password_requires_special',   '1',   'boolean', 'security', 'Contraseña requiere al menos un carácter especial',        0),
    ('enable_2fa',                  '0',   'boolean', 'security', 'Autenticación de dos factores (roadmap)',                  0),

    -- Reviews
    ('reviews_enabled',             '1',    'boolean', 'reviews', 'Activar sistema de reseñas',                               1),
    ('review_requires_reservation', '1',    'boolean', 'reviews', 'Requiere reserva completada para poder reseñar',           0),
    ('review_moderation_enabled',   '1',    'boolean', 'reviews', 'Moderar reseñas antes de publicar',                       0),
    ('max_reviews_per_reservation', '1',    'integer', 'reviews', 'Número máximo de reseñas permitidas por reserva completada',  0),
    ('min_review_length',           '10',   'integer', 'reviews', 'Longitud mínima del comentario de reseña',                 0),
    ('max_review_length',           '1000', 'integer', 'reviews', 'Longitud máxima del comentario de reseña',                 0),

    -- Cancelaciones y penalizaciones
    ('cancellation_fee_percentage', '0',    'integer', 'reservations', 'Porcentaje del total cobrado si el cliente cancela con antelación insuficiente. 0 = sin cargo.', 0),
    ('no_show_fee_percentage',      '100',  'integer', 'reservations', 'Porcentaje del total retenido si el cliente no se presenta sin cancelar. 100 = sin devolución.', 0);

-- ============================================
-- DOWN: Revertir migración (comentado)
-- ============================================
-- DROP TABLE IF EXISTS settings;
