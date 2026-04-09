-- ============================================================================
-- MIGRACIÓN 006: TELEGRAM BOT (BOTFATHER)
-- ============================================================================
-- Módulo: Bot de Telegram simplificado vía BotFather (gratuito)
-- Dependencias: 002_users_rbac.sql
-- MySQL 8.4+: Compatible ✓
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- Usuarios de Telegram vinculados a cuentas del sistema
CREATE TABLE IF NOT EXISTS telegram_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Usuario del sistema (1:1)',
    telegram_id BIGINT NOT NULL UNIQUE COMMENT 'ID de usuario Telegram',
    chat_id BIGINT NOT NULL COMMENT 'ID del chat para enviar mensajes',
    username VARCHAR(100) DEFAULT NULL COMMENT '@username en Telegram',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Vinculación activa',
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha vinculación',
    unlinked_at TIMESTAMP NULL COMMENT 'Fecha desvinculación',
    last_message_at TIMESTAMP NULL COMMENT 'Último mensaje recibido/enviado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telegram_user_id (user_id),
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_telegram_active (is_active, last_message_at DESC),
    CONSTRAINT fk_tu_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Cuentas Telegram vinculadas (BotFather webhook)';
-- Log de mensajes del bot (auditoría independiente de API logs)
CREATE TABLE IF NOT EXISTS telegram_message_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL COMMENT 'incoming=usuario, outgoing=bot',
    command VARCHAR(100) DEFAULT NULL COMMENT 'Comando Telegram: /start, /reservas, etc.',
    message_text TEXT DEFAULT NULL COMMENT 'Texto del mensaje',
    response_text TEXT DEFAULT NULL COMMENT 'Respuesta del bot',
    success BOOLEAN DEFAULT TRUE COMMENT 'Procesamiento exitoso',
    error_message TEXT DEFAULT NULL COMMENT 'Error si success=false',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retention_until TIMESTAMP AS (DATE_ADD(created_at, INTERVAL 1 YEAR)) STORED COMMENT 'RGPD: purga 1 año',
    INDEX idx_tg_user (telegram_user_id),
    INDEX idx_tg_command (command),
    INDEX idx_tg_direction (direction, created_at DESC),
    INDEX idx_tg_retention (retention_until) COMMENT 'Job purga RGPD',
    CONSTRAINT fk_tml_user FOREIGN KEY (telegram_user_id) REFERENCES telegram_users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Auditoría de mensajes Telegram (independiente de api_audit_log)';
-- Evento MySQL 8.4: Purga automática logs Telegram (RGPD 1 año)
DROP EVENT IF EXISTS evt_purge_telegram_logs;
CREATE EVENT evt_purge_telegram_logs ON SCHEDULE EVERY 1 MONTH DO
DELETE FROM telegram_message_log
WHERE retention_until < NOW();
SET FOREIGN_KEY_CHECKS = 1;
