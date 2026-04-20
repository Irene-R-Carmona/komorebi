<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Jobs\SendEmailJob;
use App\Services\Contracts\NewsletterServiceInterface;
use PDO;

/**
 * Newsletter Service - Gestión de suscripciones con double opt-in
 * GDPR compliant
 */
final class NewsletterService extends BaseService implements NewsletterServiceInterface
{
    private PDO $db;

    private string $baseUrl;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->baseUrl = Env::require('APP_URL');
    }

    /**
     * Suscribir email (paso 1: enviar confirmación)
     * @throws \Random\RandomException
     */
    #[\Override]
    public function subscribe(string $email): array
    {
        $email = \strtolower(\trim($email));

        if (!\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email inválido'];
        }

        $stmt = $this->db->prepare('SELECT id, confirmed_at, unsubscribed_at FROM newsletter_subscriptions WHERE email = ?');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['confirmed_at']) {
                return ['success' => false, 'message' => 'Este email ya está suscrito'];
            }

            if ($existing['unsubscribed_at']) {
                // Reactivar suscripción
                $token = \bin2hex(\random_bytes(32));
                $stmt = $this->db->prepare('UPDATE newsletter_subscriptions SET token = ?, unsubscribed_at = NULL, updated_at = NOW() WHERE email = ?');
                $stmt->execute([$token, $email]);
            } else {
                // Reenviar email de confirmación
                $stmt = $this->db->prepare('SELECT token FROM newsletter_subscriptions WHERE email = ?');
                $stmt->execute([$email]);
                $token = $stmt->fetchColumn();
            }
        } else {
            // Nueva suscripción
            $token = \bin2hex(\random_bytes(32));
            $stmt = $this->db->prepare('INSERT INTO newsletter_subscriptions (email, token) VALUES (?, ?)');
            $stmt->execute([$email, $token]);
        }

        // Enviar email de confirmación
        $confirmUrl = $this->baseUrl . '/newsletter/verify?token=' . $token;

        $emailPayload = [
            'to' => $email,
            'subject' => 'Confirma tu suscripción a Komorebi Café',
            'body' => $this->getConfirmationTemplate($confirmUrl),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ];

        try {
            // En desarrollo, enviar de forma síncrona (sin cola)
            // En producción, usar cola asíncrona
            if ($this->shouldUseSyncEmail()) {
                $this->sendEmailSync($emailPayload);
                Logger::info('Email de confirmación newsletter enviado (sync)', ['email' => $email]);
            } else {
                $enqueued = Queue::push(SendEmailJob::class, $emailPayload, 'default');

                if (!$enqueued) {
                    Logger::error('Error encolando email de confirmación newsletter', ['email' => $email]);

                    return ['success' => false, 'message' => 'Error al procesar la solicitud'];
                }

                Logger::info('Email de confirmación newsletter encolado', ['email' => $email]);
            }

            return [
                'success' => true,
                'message' => 'Revisa tu correo para confirmar la suscripción',
            ];
        } catch (\Throwable $e) {
            Logger::error('Error enviando email de confirmación newsletter', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Error al enviar el email de confirmación'];
        }
    }

    /**
     * Confirmar suscripción (paso 2: click en email)
     */
    #[\Override]
    public function confirm(string $token): array
    {
        $stmt = $this->db->prepare('SELECT id, email, confirmed_at FROM newsletter_subscriptions WHERE token = ?');
        $stmt->execute([$token]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            return ['success' => false, 'message' => 'Token inválido'];
        }

        if ($subscription['confirmed_at']) {
            return ['success' => false, 'message' => 'Esta suscripción ya fue confirmada'];
        }

        // Confirmar
        $stmt = $this->db->prepare('UPDATE newsletter_subscriptions SET confirmed_at = NOW() WHERE token = ?');
        $stmt->execute([$token]);

        // Enviar email de bienvenida
        $emailPayload = [
            'to' => $subscription['email'],
            'subject' => 'Bienvenido a la comunidad Komorebi',
            'body' => $this->getWelcomeTemplate(),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ];

        try {
            if ($this->shouldUseSyncEmail()) {
                $this->sendEmailSync($emailPayload);
                Logger::info('Email de bienvenida newsletter enviado (sync)', ['email' => $subscription['email']]);
            } else {
                Queue::push(SendEmailJob::class, $emailPayload, 'default');
                Logger::info('Email de bienvenida newsletter encolado', ['email' => $subscription['email']]);
            }
        } catch (\Throwable $e) {
            Logger::warning('Error enviando email de bienvenida (no crítico)', [
                'email' => $subscription['email'],
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Suscripción confirmada correctamente',
            'email' => $subscription['email'],
        ];
    }

    /**
     * Cancelar suscripción
     */
    #[\Override]
    public function unsubscribe(string $token): array
    {
        $stmt = $this->db->prepare('SELECT id, email FROM newsletter_subscriptions WHERE token = ?');
        $stmt->execute([$token]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            return ['success' => false, 'message' => 'Token inválido'];
        }

        $stmt = $this->db->prepare('UPDATE newsletter_subscriptions SET unsubscribed_at = NOW() WHERE token = ?');
        $stmt->execute([$token]);

        return [
            'success' => true,
            'message' => 'Te has dado de baja correctamente',
        ];
    }

    /**
     * Template HTML de confirmación
     */
    private function getConfirmationTemplate(string $confirmUrl): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Confirma tu suscripción</title>
            </head>
            <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(135deg, #8b4513 0%, #c9a959 100%); padding: 40px 30px; text-align: center;">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 300; letter-spacing: 1px;">
                                            KOMOREBI CAFÉ
                                        </h1>
                                        <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                            木漏れ日カフェ
                                        </p>
                                    </td>
                                </tr>

                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px 30px;">
                                        <h2 style="margin: 0 0 20px 0; color: #2c2c2c; font-size: 24px; font-weight: 600;">
                                            Confirma tu suscripción
                                        </h2>
                                        <p style="margin: 0 0 20px 0; color: #666; font-size: 16px; line-height: 1.6;">
                                            Estás a un paso de unirte a la comunidad Komorebi. Haz click en el botón para confirmar tu email y empezar a recibir nuestras novedades.
                                        </p>

                                        <!-- CTA Button -->
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="padding: 30px 0;">
                                                    <a href="$confirmUrl" style="display: inline-block; padding: 16px 40px; background-color: #c9a959; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: 600; letter-spacing: 0.5px;">
                                                        CONFIRMAR SUSCRIPCIÓN
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 20px 0 0 0; color: #999; font-size: 14px; line-height: 1.6;">
                                            Si no solicitaste esta suscripción, puedes ignorar este email.
                                        </p>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background-color: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                        <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                                            Komorebi Café · Madrid, España
                                        </p>
                                        <p style="margin: 0; color: #999; font-size: 12px;">
                                            © 2025-2026 Komorebi Café. Todos los derechos reservados.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }

    /**
     * Template HTML de bienvenida
     */
    private function getWelcomeTemplate(): string
    {
        $cafesUrl = $this->baseUrl . '/cafes';
        $couponCode = 'WELCOME5';

        return <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Bienvenido a Komorebi</title>
            </head>
            <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
                    <tr>
                        <td align="center">
                            <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(135deg, #8b4513 0%, #c9a959 100%); padding: 40px 30px; text-align: center;">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 300; letter-spacing: 1px;">
                                            KOMOREBI CAFÉ
                                        </h1>
                                        <p style="margin: 10px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                            木漏れ日カフェ
                                        </p>
                                    </td>
                                </tr>

                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px 30px;">
                                        <h2 style="margin: 0 0 20px 0; color: #2c2c2c; font-size: 24px; font-weight: 600;">
                                            ¡Bienvenido a la familia Komorebi!
                                        </h2>
                                        <p style="margin: 0 0 20px 0; color: #666; font-size: 16px; line-height: 1.6;">
                                            Gracias por unirte a nuestra comunidad. A partir de ahora recibirás nuestras novedades sobre cafés de especialidad, nuevos residentes peludos y eventos exclusivos.
                                        </p>

                                        <!-- Coupon Box -->
                                        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                            <tr>
                                                <td style="background: linear-gradient(135deg, rgba(201, 169, 89, 0.1) 0%, rgba(201, 169, 89, 0.05) 100%); border: 2px dashed #c9a959; border-radius: 8px; padding: 30px; text-align: center;">
                                                    <p style="margin: 0 0 10px 0; color: #8b4513; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                                        Tu regalo de bienvenida
                                                    </p>
                                                    <p style="margin: 0 0 15px 0; color: #2c2c2c; font-size: 32px; font-weight: 700; font-family: 'Courier New', monospace; letter-spacing: 3px;">
                                                        $couponCode
                                                    </p>
                                                    <p style="margin: 0; color: #666; font-size: 16px;">
                                                        <strong>5% de descuento</strong> en tu primera visita
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- CTA Button -->
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="padding: 20px 0;">
                                                    <a href="$cafesUrl" style="display: inline-block; padding: 16px 40px; background-color: #c9a959; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: 600; letter-spacing: 0.5px;">
                                                        EXPLORAR CAFÉS
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 30px 0 0 0; color: #666; font-size: 15px; line-height: 1.6;">
                                            <strong>Qué encontrarás en nuestras newsletters:</strong>
                                        </p>
                                        <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #666; font-size: 15px; line-height: 1.8;">
                                            <li>Cafés y animales destacados del mes</li>
                                            <li>Eventos y talleres exclusivos</li>
                                            <li>Ofertas especiales para suscriptores</li>
                                            <li>Tips de bienestar animal</li>
                                        </ul>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style="background-color: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                        <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
                                            Komorebi Café · Madrid, España
                                        </p>
                                        <p style="margin: 0; color: #999; font-size: 12px;">
                                            © 2025-2026 Komorebi Café. Todos los derechos reservados.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
    }

    /**
     * Obtener lista de emails confirmados (para envío masivo)
     */
    #[\Override]
    public function getConfirmedEmails(): array
    {
        $stmt = $this->db->query('
            SELECT email
            FROM newsletter_subscriptions
            WHERE confirmed_at IS NOT NULL
            AND unsubscribed_at IS NULL
            ORDER BY confirmed_at DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Determina si se debe usar envío síncrono de emails
     * En desarrollo (local/debug) enviamos directamente sin cola
     */
    private function shouldUseSyncEmail(): bool
    {
        $appEnv = Env::get('APP_ENV', 'production');
        $appDebug = \filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

        // Usar sync en development, local o cuando debug está habilitado
        return \in_array($appEnv, ['local', 'development'], true) || $appDebug === true;
    }

    /**
     * Envía un email de forma síncrona usando SendEmailJob
     *
     * @param array<string, mixed> $payload
     * @throws \Throwable
     */
    private function sendEmailSync(array $payload): void
    {
        $job = new SendEmailJob();
        $job->handle($payload);
    }
}
