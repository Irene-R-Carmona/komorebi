<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Exceptions\BusinessRuleException;
use Override;
use PDO;
use Throwable;

/**
 * Job para notificar promoción desde waitlist
 *
 * Cuando se libera una plaza, este job envía un email al siguiente
 * usuario en la lista de espera con un enlace para confirmar su reserva
 * (válido por 10 minutos).
 *
 * Payload esperado:
 * - waitlist_id: int (ID del registro en waitlist)
 * - user_email: string
 * - user_name: string
 * - cafe_name: string
 * - date: string (formato: Y-m-d)
 * - time: string (formato: H:i)
 * - token: string (UUID para confirmación)
 * - expires_at: int (timestamp de expiración)
 *
 * @package App\Jobs
 */
final class WaitlistPromotionJob implements JobInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Ejecuta la notificación de promoción
     *
     * @param array<string, mixed> $payload Datos de la promoción
     * @return void
     * @throws BusinessRuleException Si el token ya expiró
     */
    #[Override]
    public function handle(array $payload): void
    {
        // Support lightweight push: just the entry ID — hydrate from DB
        if (isset($payload['waitlist_entry_id']) && !isset($payload['waitlist_id'])) {
            $entryId = (int) $payload['waitlist_entry_id'];
            $hydratedPayload = $this->hydratePayload($entryId);
            if ($hydratedPayload === null) {
                Logger::warning('[WaitlistPromotionJob] Entrada waitlist no encontrada', [
                    'waitlist_entry_id' => $entryId,
                ]);

                return;
            }
            $payload = $hydratedPayload;
        }

        $this->validatePayload($payload);

        // Normalizar campos críticos del payload para evitar mixed
        $expiresAt = isset($payload['expires_at']) ? (int) $payload['expires_at'] : 0;
        $waitlistId = isset($payload['waitlist_id']) ? (int) $payload['waitlist_id'] : 0;
        $userEmail = isset($payload['user_email']) ? (string) $payload['user_email'] : '';
        $token = isset($payload['token']) ? (string) $payload['token'] : '';

        try {
            // Verificar que el token no haya expirado antes de enviar email
            if ($expiresAt < \time()) {
                Logger::warning('[WaitlistPromotionJob] Token expirado, cancelando envío', [
                    'waitlist_id' => $waitlistId,
                    'expires_at' => \date('Y-m-d H:i:s', $expiresAt),
                ]);

                // Marcar como expirado en BD
                $this->markAsExpired($waitlistId);

                return;
            }

            // Generar enlace de confirmación
            $confirmUrl = $this->generateConfirmUrl($token);
            $expiresIn = (int) \ceil(($expiresAt - \time()) / 60); // minutos

            // Preparar contenido del email
            $emailBody = $this->buildEmailBody($payload, $confirmUrl, $expiresIn);

            // Encolar email (evitar bloquear este job si falla el email)
            Queue::push(SendEmailJob::class, [
                'to' => $userEmail,
                'subject' => '¡Tenemos una plaza disponible! - Komorebi Café',
                'body' => $emailBody,
                '_correlation_id' => WideEvent::get('request_id') ?? '',
            ]);

            // Enviar notificación por Telegram si el usuario tiene telegram_id
            $this->sendTelegramNotification($payload, $confirmUrl, $expiresIn);

            Logger::info('[WaitlistPromotionJob] Notificación de promoción enviada', [
                'waitlist_id' => $waitlistId,
                'user_email' => $userEmail,
                'cafe_name' => isset($payload['cafe_name']) ? (string) $payload['cafe_name'] : '',
                'date' => isset($payload['date']) ? (string) $payload['date'] : '',
                'time' => isset($payload['time']) ? (string) $payload['time'] : '',
            ]);
        } catch (Throwable $e) {
            Logger::error('[WaitlistPromotionJob] Error al procesar promoción', [
                'waitlist_id' => $payload['waitlist_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Genera el cuerpo HTML del email de promoción
     *
     * @param array<string, mixed> $payload
     * @param string               $confirmUrl
     * @param integer              $expiresIn  Minutos hasta expiración
     * @return string HTML del email
     */
    private function buildEmailBody(array $payload, string $confirmUrl, int $expiresIn): string
    {
        $userName = \htmlspecialchars($payload['user_name'], ENT_QUOTES, 'UTF-8');
        $cafeName = \htmlspecialchars($payload['cafe_name'], ENT_QUOTES, 'UTF-8');
        $date = \htmlspecialchars($payload['date'], ENT_QUOTES, 'UTF-8');
        $time = \htmlspecialchars($payload['time'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Plaza Disponible - Komorebi Café</title>
            </head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                    <h1 style="margin: 0; font-size: 28px;">🎉 ¡Buenas noticias, {$userName}!</h1>
                </div>

                <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                    <p style="font-size: 16px; margin-bottom: 20px;">
                        Se ha liberado una plaza para tu fecha y hora deseada en <strong>{$cafeName}</strong>.
                    </p>

                    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                        <p style="margin: 5px 0;"><strong>📍 Café:</strong> {$cafeName}</p>
                        <p style="margin: 5px 0;"><strong>📅 Fecha:</strong> {$date}</p>
                        <p style="margin: 5px 0;"><strong>🕐 Hora:</strong> {$time}</p>
                    </div>

                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                        <p style="margin: 0; font-size: 14px;">
                            ⏰ <strong>Tienes {$expiresIn} minutos para confirmar tu reserva.</strong><br>
                            Si no confirmas en ese tiempo, la plaza se ofrecerá al siguiente en la lista de espera.
                        </p>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{$confirmUrl}" style="display: inline-block; background: #667eea; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                            ✅ Confirmar Reserva Ahora
                        </a>
                    </div>

                    <p style="font-size: 12px; color: #666; margin-top: 30px; text-align: center;">
                        Si no solicitaste estar en lista de espera, ignora este email.<br>
                        Este enlace expirará automáticamente en {$expiresIn} minutos.
                    </p>
                </div>

                <div style="text-align: center; margin-top: 20px; padding: 20px; color: #999; font-size: 12px;">
                    <p>Komorebi Café - Donde los gatos y el café se encuentran 🐾☕</p>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * Genera la URL de confirmación con el token
     *
     * @param string $token UUID del token
     * @return string URL completa
     */
    private function generateConfirmUrl(string $token): string
    {
        $baseUrl = Env::get('APP_URL', 'http://localhost:8080');

        return $baseUrl . '/reservas/waitlist/confirm?token=' . \urlencode($token);
    }

    /**
     * Marca un registro de waitlist como expirado
     *
     * @param integer $waitlistId
     * @return void
     */
    private function markAsExpired(int $waitlistId): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE waitlist SET status = 'expired' WHERE id = ? AND status = 'notified'"
            );
            $stmt->execute([$waitlistId]);
        } catch (Throwable $e) {
            Logger::error('[WaitlistPromotionJob] Error al marcar como expirado', [
                'waitlist_id' => $waitlistId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hydrates the full payload from a waitlist entry ID.
     * Fetches user, time slot, and café data from the database.
     *
     * @return array<string, mixed>|null  null if the entry doesn't exist
     */
    private function hydratePayload(int $entryId): ?array
    {
        try {
            $stmt = $this->db->prepare(<<<'SQL'
                    SELECT
                        w.id                          AS waitlist_id,
                        w.token,
                        UNIX_TIMESTAMP(w.expires_at)  AS expires_at,
                        u.name                        AS user_name,
                        u.email                       AS user_email,
                        c.name                        AS cafe_name,
                        ts.slot_date                  AS date,
                        ts.slot_time                  AS time
                    FROM waitlist w
                    INNER JOIN users u      ON w.user_id      = u.id
                    INNER JOIN time_slots ts ON w.time_slot_id = ts.id
                    INNER JOIN cafes c       ON ts.cafe_id     = c.id
                    WHERE w.id = :id
                SQL);
            $stmt->execute([':id' => $entryId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row !== false ? $row : null;
        } catch (Throwable $e) {
            Logger::error('[WaitlistPromotionJob] Error al hidratar payload', [
                'waitlist_entry_id' => $entryId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Valida que el payload tenga los campos requeridos
     *
     * @param array<string, mixed> $payload
     * @return void
     * @throws BusinessRuleException Si falta algún campo requerido
     */
    private function validatePayload(array $payload): void
    {
        $required = [
            'waitlist_id',
            'user_email',
            'user_name',
            'cafe_name',
            'date',
            'time',
            'token',
            'expires_at',
        ];

        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                throw BusinessRuleException::withMessage(
                    "Campo requerido ausente en WaitlistPromotionJob: {$field}"
                );
            }
        }
    }

    /**
     * Envía notificación por Telegram si el usuario tiene telegram_id
     *
     * @param array<string, mixed> $payload
     * @param string               $confirmUrl
     * @param integer              $expiresIn
     * @return void
     */
    private function sendTelegramNotification(array $payload, string $confirmUrl, int $expiresIn): void
    {
        try {
            // Buscar telegram_id del usuario
            $stmt = $this->db->prepare('
                SELECT telegram_id FROM users WHERE email = ? AND telegram_id IS NOT NULL
            ');
            $stmt->execute([$payload['user_email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['telegram_id'])) {
                // Usuario no tiene Telegram configurado, skip
                return;
            }

            $message = $this->buildTelegramMessage($payload, $confirmUrl, $expiresIn);

            // Enviar mensaje (requiere bot token configurado)
            $botToken = Env::get('TELEGRAM_BOT_TOKEN');

            if (!$botToken) {
                Logger::warning('[WaitlistPromotionJob] TELEGRAM_BOT_TOKEN no configurado');

                return;
            }

            $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $postData = [
                'chat_id' => $user['telegram_id'],
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ];

            $ch = \curl_init($apiUrl);
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, \http_build_query($postData));
            \curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            \curl_close($ch);

            if ($httpCode === 200) {
                Logger::info('[WaitlistPromotionJob] Notificación Telegram enviada', [
                    'telegram_id' => $user['telegram_id'],
                    'waitlist_id' => $payload['waitlist_id'],
                ]);
            } else {
                Logger::warning('[WaitlistPromotionJob] Error enviando Telegram', [
                    'http_code' => $httpCode,
                    'response' => $response,
                ]);
            }
        } catch (Throwable $e) {
            Logger::error('[WaitlistPromotionJob] Excepción en Telegram', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Construye el mensaje de Telegram
     *
     * @param array<string, mixed> $payload
     * @param string               $confirmUrl
     * @param integer              $expiresIn
     * @return string
     */
    private function buildTelegramMessage(array $payload, string $confirmUrl, int $expiresIn): string
    {
        return "🎉 <b>¡Tenemos una plaza disponible!</b>\n\n"
            . "📍 Café: <b>{$payload['cafe_name']}</b>\n"
            . "📅 Fecha: <b>{$payload['date']}</b>\n"
            . "🕐 Hora: <b>{$payload['time']}</b>\n\n"
            . "⏰ Tienes <b>{$expiresIn} minutos</b> para confirmar.\n\n"
            . "👉 <a href=\"{$confirmUrl}\">Confirmar Reserva</a>";
    }
}
