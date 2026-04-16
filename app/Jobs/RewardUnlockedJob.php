<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Env;
use PDO;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Job: Notificar cuando se desbloquea una recompensa
 *
 * Se encola cuando el usuario alcanza un milestone (cada 5 sellos)
 */
final class RewardUnlockedJob implements JobInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    #[\Override]
    public function handle(array $payload): void
    {
        $userId = $payload['user_id'] ?? null;
        $stamps = $payload['stamps'] ?? 0;
        $tier = $payload['tier'] ?? 'bronze';
        $milestone = $payload['milestone'] ?? 0;

        if (!$userId) {
            throw new \Exception('user_id requerido en payload');
        }

        // Obtener info del usuario
        $db = $this->db;
        $stmt = $db->prepare('SELECT name, email FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            throw new \Exception("Usuario no encontrado: $userId");
        }

        // Determinar recompensa desbloqueada según milestone
        $rewardInfo = $this->getRewardInfo($milestone);

        // Enviar email
        $this->sendEmail($user, $stamps, $tier, $rewardInfo);
    }

    /**
     * Obtener información de la recompensa según milestone
     */
    private function getRewardInfo(int $milestone): array
    {
        $rewards = [
            5 => ['name' => 'Bebida Gratis', 'icon' => '☕', 'type' => 'drink_free'],
            10 => ['name' => 'Entrada Gratis', 'icon' => '🎟️', 'type' => 'entry_free'],
            15 => ['name' => 'Recompensas Especiales', 'icon' => '🎁', 'type' => 'multiple'],
            20 => ['name' => 'Recompensas Premium', 'icon' => '💎', 'type' => 'premium'],
        ];

        return $rewards[$milestone] ?? [
            'name' => 'Nueva Recompensa',
            'icon' => '⭐',
            'type' => 'generic',
        ];
    }

    /**
     * Enviar email de notificación
     */
    private function sendEmail(array $user, int $stamps, string $tier, array $rewardInfo): void
    {
        $mail = new PHPMailer(true);

        try {
            // Configuración SMTP
            $this->configureMailer($mail);

            // Destinatario
            $mail->addAddress($user['email'], $user['name']);

            // Asunto
            $mail->Subject = \sprintf('🎉 ¡Nueva recompensa desbloqueada! - Komorebi Café');

            // Contenido HTML
            $mail->isHTML(true);
            $mail->Body = $this->getEmailBody($user, $stamps, $tier, $rewardInfo);

            // Alternativa texto plano
            $mail->AltBody = $this->getEmailPlainText($user, $stamps, $tier, $rewardInfo);

            $mail->send();
        } catch (MailerException $e) {
            throw new \Exception('Error al enviar email: ' . $mail->ErrorInfo);
        }
    }

    /**
     * Configurar PHPMailer
     */
    private function configureMailer(PHPMailer $mail): void
    {
        $appEnv = Env::get('APP_ENV', 'production');

        if ($appEnv === 'development') {
            // Mailpit en desarrollo (sin TLS)
            $mail->isSMTP();
            $mail->Host = Env::get('MAIL_HOST', 'mailpit');
            $mail->Port = (int) Env::get('MAIL_PORT', '1025');
            $mail->SMTPAuth = false;
        } else {
            // Configuración producción
            $mail->isSMTP();
            $mail->Host = Env::get('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = Env::get('MAIL_USERNAME');
            $mail->Password = Env::get('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) Env::get('MAIL_PORT', '587');
        }

        $mail->setFrom(
            Env::get('MAIL_FROM_ADDRESS', 'noreply@komorebi.local'),
            Env::get('MAIL_FROM_NAME', 'Komorebi Café')
        );

        $mail->CharSet = 'UTF-8';
    }

    /**
     * Generar cuerpo HTML del email
     */
    private function getEmailBody(array $user, int $stamps, string $tier, array $rewardInfo): string
    {
        $tierLabels = [
            'bronze' => '🥉 Bronce',
            'silver' => '🥈 Plata',
            'gold' => '🥇 Oro',
            'platinum' => '💎 Platino',
        ];

        $tierLabel = $tierLabels[$tier] ?? \ucfirst($tier);
        $cardUrl = Env::get('APP_URL', 'http://localhost:8080');
        $cardUrl .= '/loyalty/card';

        return <<<HTML
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; }
                    .content { padding: 30px 20px; }
                    .reward-badge { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
                    .reward-badge .icon { font-size: 60px; margin-bottom: 10px; }
                    .reward-badge .name { font-size: 24px; font-weight: bold; }
                    .stats { display: flex; justify-content: space-around; margin: 30px 0; }
                    .stat { text-align: center; }
                    .stat .value { font-size: 32px; font-weight: bold; color: #667eea; }
                    .stat .label { font-size: 14px; color: #666; margin-top: 5px; }
                    .cta { text-align: center; margin: 30px 0; }
                    .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; }
                    .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎉 ¡Felicidades, {$user['name']}!</h1>
                        <p>Has desbloqueado una nueva recompensa</p>
                    </div>

                    <div class="content">
                        <div class="reward-badge">
                            <div class="icon">{$rewardInfo['icon']}</div>
                            <div class="name">{$rewardInfo['name']}</div>
                            <p style="margin: 10px 0 0 0;">¡Ya puedes canjear esta recompensa!</p>
                        </div>

                        <div class="stats">
                            <div class="stat">
                                <div class="value">$stamps</div>
                                <div class="label">Sellos acumulados</div>
                            </div>
                            <div class="stat">
                                <div class="value">$tierLabel</div>
                                <div class="label">Tu nivel actual</div>
                            </div>
                        </div>

                        <p style="text-align: center; font-size: 16px; color: #555;">
                            Visita tu tarjeta de fidelización para canjear tus sellos por recompensas exclusivas.
                        </p>

                        <div class="cta">
                            <a href="$cardUrl" class="button">Ver Mi Tarjeta</a>
                        </div>

                        <p style="font-size: 14px; color: #666; text-align: center; margin-top: 30px;">
                            <strong>💡 Tip:</strong> Sigue completando visitas para desbloquear más recompensas y subir de nivel.
                        </p>
                    </div>

                    <div class="footer">
                        <p>Este email fue enviado automáticamente por el sistema de fidelización de Komorebi Café.</p>
                        <p>© 2026 Komorebi Café - Todos los derechos reservados</p>
                    </div>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * Generar texto plano del email
     */
    private function getEmailPlainText(array $user, int $stamps, string $tier, array $rewardInfo): string
    {
        $cardUrl = Env::get('APP_URL', 'http://localhost:8080') . '/loyalty/card';

        return <<<TEXT
            ¡Felicidades, {$user['name']}!

            Has desbloqueado una nueva recompensa: {$rewardInfo['name']}

            Sellos acumulados: $stamps
            Nivel actual: {$tier}

            Visita tu tarjeta de fidelización para canjear tus sellos:
            $cardUrl

            Sigue completando visitas para desbloquear más recompensas.

            ---
            Komorebi Café
            © 2026 Todos los derechos reservados
            TEXT;
    }
}
