<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * SystemSettingsSeeder
 *
 * Seeder de configuración del sistema (tabla settings).
 * Incluye configuración RGPD, SMTP, seguridad, reservas.
 */
final class SystemSettingsSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[SystemSettingsSeeder] starting');

        $settings = [
            // ════════════════════════════════════════
            // GENERAL
            // ════════════════════════════════════════
            ['site_name', 'Komorebi Café', 'string', 'general', 'Nombre del sitio', true],
            ['site_description', 'Red de cafeterías temáticas de animales con estética japonesa en España', 'string', 'general', 'Descripción del sitio', true],
            ['maintenance_mode', 'false', 'boolean', 'general', 'Modo mantenimiento', false],
            ['timezone', 'Europe/Madrid', 'string', 'general', 'Zona horaria por defecto', true],
            ['default_language', 'es', 'string', 'general', 'Idioma por defecto', true],
            ['items_per_page', '25', 'integer', 'general', 'Elementos por página (admin)', false],

            // ════════════════════════════════════════
            // EMAIL / SMTP
            // ════════════════════════════════════════
            ['smtp_host', 'mailpit', 'string', 'email', 'Host SMTP', false],
            ['smtp_port', '1025', 'integer', 'email', 'Puerto SMTP', false],
            ['smtp_username', '', 'string', 'email', 'Usuario SMTP', false],
            ['smtp_password', '', 'string', 'email', 'Contraseña SMTP (encriptada)', false],
            ['smtp_encryption', 'none', 'string', 'email', 'Encriptación: none, tls, ssl', false],
            ['mail_from_address', 'noreply@komorebi.test', 'string', 'email', 'Email remitente', false],
            ['mail_from_name', 'Komorebi Café', 'string', 'email', 'Nombre remitente', false],
            ['support_email', 'soporte@komorebi.test', 'string', 'email', 'Email de soporte', true],

            // ════════════════════════════════════════
            // RESERVAS
            // ════════════════════════════════════════
            ['reservations_enabled', 'true', 'boolean', 'reservations', 'Sistema de reservas activo', true],
            ['max_advance_days', '30', 'integer', 'reservations', 'Días máximos antelación', false],
            ['min_advance_hours', '2', 'integer', 'reservations', 'Horas mínimas antelación', false],
            ['cancellation_hours', '24', 'integer', 'reservations', 'Horas de antelación necesarias para cancelar sin cargo. Por debajo de este umbral se aplica cancellation_fee_percentage.', false],
            ['cancellation_fee_percentage', '0', 'integer', 'reservations', 'Porcentaje del total cobrado si cancela con antelación insuficiente. 0 = sin cargo.', false],
            ['no_show_fee_percentage', '100', 'integer', 'reservations', 'Porcentaje retenido si no se presenta sin cancelar. 100 = sin devolución.', false],
            ['max_guests_per_reservation', '10', 'integer', 'reservations', 'Máximo personas por reserva', false],
            ['default_duration_minutes', '60', 'integer', 'reservations', 'Duración por defecto (min)', false],
            ['send_confirmation_email', 'true', 'boolean', 'reservations', 'Enviar email confirmación', false],
            ['send_reminder_email', 'true', 'boolean', 'reservations', 'Enviar email recordatorio 24h', false],

            // ════════════════════════════════════════
            // SEGURIDAD
            // ════════════════════════════════════════
            ['session_lifetime', '120', 'integer', 'security', 'Duración sesión (minutos)', false],
            ['max_login_attempts', '5', 'integer', 'security', 'Máximo intentos login', false],
            ['lockout_duration', '15', 'integer', 'security', 'Duración bloqueo (minutos)', false],
            ['require_email_verification', 'true', 'boolean', 'security', 'Requerir verificación email', false],
            ['min_password_length', '8', 'integer', 'security', 'Longitud mínima contraseña', false],
            ['password_requires_uppercase', 'true', 'boolean', 'security', 'Contraseña: mayúscula obligatoria', false],
            ['password_requires_number', 'true', 'boolean', 'security', 'Contraseña: número obligatorio', false],
            ['password_requires_special', 'true', 'boolean', 'security', 'Contraseña: carácter especial obligatorio', false],
            ['enable_2fa', 'false', 'boolean', 'security', '2FA habilitado (roadmap)', false],

            // ════════════════════════════════════════
            // RESEÑAS
            // ════════════════════════════════════════
            ['reviews_enabled', 'true', 'boolean', 'reviews', 'Sistema de reseñas activo', true],
            ['review_requires_reservation', 'true', 'boolean', 'reviews', 'Requiere reserva completada', false],
            ['review_moderation_enabled', 'true', 'boolean', 'reviews', 'Moderar antes de publicar', false],
            ['max_reviews_per_reservation', '1', 'integer', 'reviews', 'Número máximo de reseñas permitidas por reserva completada', false],
            ['min_review_length', '10', 'integer', 'reviews', 'Longitud mínima comentario', false],
            ['max_review_length', '1000', 'integer', 'reviews', 'Longitud máxima comentario', false],

            // ════════════════════════════════════════
            // RGPD / PRIVACIDAD
            // ════════════════════════════════════════
            ['gdpr_enabled', 'true', 'boolean', 'privacy', 'RGPD compliance activo', true],
            ['data_retention_days', '365', 'integer', 'privacy', 'Retención logs auditoría (días)', false],
            ['user_deletion_grace_period', '30', 'integer', 'privacy', 'Período gracia eliminación usuario (días)', false],
            ['require_cookie_consent', 'true', 'boolean', 'privacy', 'Requerir consentimiento cookies', true],
            ['allow_data_export', 'true', 'boolean', 'privacy', 'Permitir exportación datos usuario', true],
            ['anonymize_deleted_users', 'true', 'boolean', 'privacy', 'Anonimizar usuarios eliminados', false],

            // ════════════════════════════════════════
            // TELEGRAM BOT
            // ════════════════════════════════════════
            ['telegram_bot_enabled', 'false', 'boolean', 'telegram', 'Bot de Telegram activo', false],
            ['telegram_bot_token', '', 'string', 'telegram', 'Token del bot (BotFather)', false],
            ['telegram_webhook_url', '', 'string', 'telegram', 'URL webhook', false],

            // ════════════════════════════════════════
            // APIS EXTERNAS
            // ════════════════════════════════════════
            ['weather_api_enabled', 'true', 'boolean', 'apis', 'Weather API activa', false],
            ['weather_api_key', '', 'string', 'apis', 'API key OpenWeatherMap', false],
            ['weather_cache_hours', '6', 'integer', 'apis', 'Horas cache clima', false],
            ['holidays_api_enabled', 'true', 'boolean', 'apis', 'Holidays API activa', false],
            ['holidays_cache_days', '30', 'integer', 'apis', 'Días cache festivos', false],
        ];

        $stmt = $this->db->prepare('
            INSERT INTO settings (`key`, `value`, `type`, `group_name`, `description`, `is_public`)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `type` = VALUES(`type`),
                `group_name` = VALUES(`group_name`),
                `description` = VALUES(`description`),
                `is_public` = VALUES(`is_public`)
        ');

        $count = 0;
        foreach ($settings as $setting) {
            // Convertir booleanos a int para MySQL
            $setting[5] = $setting[5] ? 1 : 0;
            $stmt->execute($setting);
            $count++;
        }

        Logger::info('[SystemSettingsSeeder] completed', ['count' => $count]);
    }
}
