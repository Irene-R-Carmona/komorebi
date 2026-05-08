<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Jobs\SendEmailJob;
use App\Services\Contracts\EmailServiceInterface;

/**
 * Servicio de Email ASÍNCRONO
 *
 * Gestiona envío de emails usando sistema de colas.
 * Todos los emails se procesan de forma asíncrona para no bloquear requests HTTP.
 */
final class EmailService extends BaseService implements EmailServiceInterface
{
    /**
     * Enviar email de verificación
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $verificationUrl
     *
     * @return boolean
     */
    public function sendVerificationEmail(
        string $userEmail,
        string $userName,
        string $verificationUrl
    ): bool {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $userEmail,
            'subject' => 'Verifica tu email en Komorebi',
            'body' => $this->getVerificationEmailTemplate($userName, $verificationUrl),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email de verificación encolado', ['email' => $userEmail]);
        } else {
            Logger::error('Error encolando email de verificación', ['email' => $userEmail]);
        }

        return $enqueued;
    }

    /**
     * Enviar email de reset de contraseña
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $resetUrl
     *
     * @return boolean
     */
    public function sendPasswordResetEmail(string $userEmail, string $userName, string $resetUrl): bool
    {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $userEmail,
            'subject' => 'Recupera tu contraseña en Komorebi',
            'body' => $this->getPasswordResetTemplate($userName, $resetUrl),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email de password reset encolado', ['email' => $userEmail]);
        } else {
            Logger::error('Error encolando email de password reset', ['email' => $userEmail]);
        }

        return $enqueued;
    }

    /**
     * Enviar confirmación de reserva
     *
     * @param string $userEmail
     * @param string|array $userNameOrReservationData
     * @param array|null  $reservationData
     * @param string|null $pdfPath Ruta al PDF adjunto (opcional)
     *
     * @return boolean
     */
    public function sendReservationConfirmation(
        string $userEmail,
        mixed $userNameOrReservationData,
        ?array $reservationData = null,
        ?string $pdfPath = null
    ): bool {
        // Backwards-compatible handling: callers may pass (email, reservationData)
        if (\is_array($userNameOrReservationData)) {
            $reservationData = $userNameOrReservationData;
            $userName = $reservationData['user_name'] ?? 'Usuario';
        } else {
            $userName = (string) $userNameOrReservationData;
            $reservationData ??= [];
        }
        $pdfPath ??= ($reservationData['pdf_path'] ?? null);

        $jobData = [
            'to' => $userEmail,
            'subject' => '✅ Reserva confirmada en Komorebi',
            'body' => $this->getReservationConfirmationTemplate($userName, $reservationData),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ];

        // Añadir PDF si existe
        if ($pdfPath && \file_exists($pdfPath)) {
            $jobData['attachment_path'] = $pdfPath;
            $jobData['attachment_name'] = 'factura_reserva.pdf';
        }

        $enqueued = Queue::push(SendEmailJob::class, $jobData, 'emails');

        if ($enqueued) {
            Logger::info('Email de confirmación de reserva encolado', [
                'email' => $userEmail,
                'reservation_id' => $reservationData['id'] ?? 'unknown',
                'has_pdf' => $pdfPath !== null,
            ]);
        } else {
            Logger::error('Error encolando confirmación de reserva', [
                'email' => $userEmail,
                'reservation_id' => $reservationData['id'] ?? 'unknown',
            ]);
        }

        return $enqueued;
    }

    /**
     * Enviar cancelación de reserva
     *
     * @param string $userEmail
     * @param string $userName
     * @param array  $reservationData
     * @param string $reason
     *
     * @return boolean
     */
    public function sendReservationCancellation(
        string $userEmail,
        string $userName,
        array $reservationData,
        string $reason = ''
    ): bool {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $userEmail,
            'subject' => 'Reserva cancelada en Komorebi',
            'body' => $this->getReservationCancellationTemplate($userName, $reservationData, $reason),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email de cancelación de reserva encolado', [
                'email' => $userEmail,
                'reservation_id' => $reservationData['id'] ?? 'unknown',
            ]);
        } else {
            Logger::error('Error encolando cancelación de reserva', [
                'email' => $userEmail,
                'reservation_id' => $reservationData['id'] ?? 'unknown',
            ]);
        }

        return $enqueued;
    }

    /**
     * Enviar email de prueba
     *
     * @param string $recipientEmail Email del destinatario
     * @param string $recipientName  Nombre del destinatario
     *
     * @return boolean
     */
    public function sendTestEmail(string $recipientEmail, string $recipientName = 'Administrador'): bool
    {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $recipientEmail,
            'subject' => 'Email de prueba - Komorebi Café',
            'body' => $this->getTestEmailTemplate($recipientName),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email de prueba encolado', ['email' => $recipientEmail]);
        } else {
            Logger::error('Error encolando email de prueba', ['email' => $recipientEmail]);
        }

        return $enqueued;
    }

    // ─────────────────────────────────────────────────────────────
    // PLANTILLAS
    // ─────────────────────────────────────────────────────────────

    private function getVerificationEmailTemplate(string $userName, string $verificationUrl): string
    {
        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>Bienvenido a Komorebi, $userName</h2>
                <p>Para completar tu registro, verifica tu email haciendo clic en el siguiente enlace:</p>
                <p style="text-align: center; margin: 20px 0;">
                    <a href="$verificationUrl" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        Verificar Email
                    </a>
                </p>
                <p style="color: #666; font-size: 12px;">
                    Este enlace expira en 1 hora. Si no hiciste esta solicitud, ignora este email.
                </p>
            </div>
            HTML;
    }

    private function getPasswordResetTemplate(string $userName, string $resetUrl): string
    {
        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>Recupera tu contraseña</h2>
                <p>Hola $userName,</p>
                <p>Recibimos una solicitud para recuperar tu contraseña. Haz clic en el siguiente enlace:</p>
                <p style="text-align: center; margin: 20px 0;">
                    <a href="$resetUrl" style="background-color: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        Recuperar Contraseña
                    </a>
                </p>
                <p style="color: #666; font-size: 12px;">
                    Este enlace expira en 1 hora. Si no solicitaste esto, ignora este email.
                </p>
            </div>
            HTML;
    }

    private function getReservationConfirmationTemplate(string $userName, array $data): string
    {
        $cafeName = \e($data['cafe_name'] ?? 'Komorebi');
        $date = \e($data['reservation_date'] ?? '');
        $time = \e($data['reservation_time'] ?? '');
        $guests = (int) ($data['guest_count'] ?? 1);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>Reserva Confirmada</h2>
                <p>Hola $userName,</p>
                <p>Tu reserva en <strong>$cafeName</strong> ha sido confirmada.</p>
                <div style="background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <p><strong>Fecha:</strong> $date</p>
                    <p><strong>Hora:</strong> $time</p>
                    <p><strong>Personas:</strong> $guests</p>
                </div>
                <p>Te esperamos. ¡Que disfrutes tu visita!</p>
            </div>
            HTML;
    }

    private function getReservationCancellationTemplate(string $userName, array $data, string $reason): string
    {
        $cafeName = \e($data['cafe_name'] ?? 'Komorebi');
        $date = \e($data['reservation_date'] ?? '');
        $reasonText = $reason ? '<p><strong>Motivo:</strong> ' . \e($reason) . '</p>' : '';

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>Reserva Cancelada</h2>
                <p>Hola $userName,</p>
                <p>Tu reserva en <strong>$cafeName</strong> para el $date ha sido cancelada.</p>
                $reasonText
                <p style="color: #666; font-size: 12px;">
                    Si tienes dudas, contacta con nuestro equipo de atención al cliente.
                </p>
            </div>
            HTML;
    }

    private function getTestEmailTemplate(string $userName): string
    {
        $currentDate = \date('d/m/Y H:i:s');

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;">
                    <h1 style="color: white; margin: 0;">✓ Email de Prueba</h1>
                    <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Komorebi Café</p>
                </div>

                <div style="background-color: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #333; margin-top: 0;">¡Hola $userName!</h2>

                    <p style="color: #666; line-height: 1.6;">
                        Este es un email de prueba para verificar que la configuración de correo electrónico
                        de tu sistema está funcionando correctamente.
                    </p>

                    <div style="background-color: white; padding: 20px; margin: 20px 0; border-left: 4px solid #667eea; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #667eea;">✓ Configuración SMTP correcta</h3>
                        <ul style="color: #666; line-height: 1.8;">
                            <li>El servidor SMTP está respondiendo</li>
                            <li>Las credenciales son válidas</li>
                            <li>El email se envió exitosamente</li>
                        </ul>
                    </div>

                    <div style="background-color: #e8f4fd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <p style="margin: 0; color: #0066cc; font-size: 14px;">
                            <strong>📅 Fecha de envío:</strong> $currentDate
                        </p>
                    </div>

                    <p style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                        Este es un email automático generado desde el panel de administración de Komorebi Café.
                        Si no solicitaste esta prueba, puedes ignorar este mensaje.
                    </p>
                </div>
            </div>
            HTML;
    }

    /**
     * Enviar confirmación de waitlist
     *
     * @param string $userEmail
     * @param string $userName
     * @param string $token
     * @param array  $waitlistData
     *
     * @return boolean
     */
    public function sendWaitlistConfirmation(
        string $userEmail,
        string $userName,
        string $token,
        array $waitlistData
    ): bool {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $userEmail,
            'subject' => '🐾 Confirmación de Lista de Espera - Komorebi Café',
            'body' => $this->getWaitlistConfirmationTemplate($userName, $token, $waitlistData),
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email de waitlist encolado', ['email' => $userEmail]);
        } else {
            Logger::error('Error encolando email de waitlist', ['email' => $userEmail]);
        }

        return $enqueued;
    }

    private function getWaitlistConfirmationTemplate(string $userName, string $token, array $data): string
    {
        $appUrl = Env::get('APP_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $statusUrl = \rtrim($appUrl, '/') . '/waitlist/status/' . $token;
        $slotDate = \date('d/m/Y', \strtotime($data['slot_date'] ?? 'now'));
        $slotTime = \date('H:i', \strtotime($data['slot_time'] ?? 'now'));
        $position = $data['position'] ?? 0;
        $estimatedWait = ($position - 1) * 15;

        return <<<HTML
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;">
                    <h1 style="color: white; margin: 0;">🐾 Komorebi Café</h1>
                    <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0;">Confirmación de Lista de Espera</p>
                </div>

                <div style="background-color: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                    <h2 style="color: #333; margin-top: 0;">¡Hola $userName!</h2>

                    <p style="color: #666; line-height: 1.6;">
                        Te has unido exitosamente a la lista de espera. Estos son los detalles:
                    </p>

                    <div style="text-align: center; margin: 30px 0;">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;
                                    font-size: 48px; font-weight: bold; width: 100px; height: 100px;
                                    border-radius: 50%; display: inline-flex; align-items: center;
                                    justify-content: center;">
                            #$position
                        </div>
                        <p style="color: #6b7280; margin: 10px 0;">Tu posición en la cola</p>
                    </div>

                    <div style="background-color: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
                        <p style="margin: 10px 0; color: #374151;"><strong>📅 Fecha:</strong> $slotDate</p>
                        <p style="margin: 10px 0; color: #374151;"><strong>🕐 Hora:</strong> $slotTime</p>
                        <p style="margin: 10px 0; color: #374151;"><strong>⏳ Tiempo estimado:</strong> ~$estimatedWait minutos</p>
                    </div>

                    <p style="color: #666; line-height: 1.6;">
                        Te notificaremos por email cuando tengamos una plaza disponible para ti.
                    </p>

                    <div style="text-align: center; margin: 30px 0;">
                        <a href="$statusUrl" style="background: #667eea; color: white; padding: 12px 24px;
                                                      text-decoration: none; border-radius: 6px; font-weight: bold;
                                                      display: inline-block;">
                            Ver Estado de mi Lista
                        </a>
                    </div>

                    <div style="background-color: #e8f4fd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <p style="margin: 0; color: #0066cc; font-size: 14px;">
                            <strong>Importante:</strong> Guarda este enlace para consultar tu posición:<br>
                            <a href="$statusUrl" style="color: #0066cc; word-break: break-all;">$statusUrl</a>
                        </p>
                    </div>

                    <p style="color: #999; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;">
                        Gracias por elegir Komorebi Café<br>
                        木漏れ日カフェ
                    </p>
                </div>
            </div>
            HTML;
    }

    /**
     * Send generic email
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $enqueued = Queue::push(SendEmailJob::class, [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            '_correlation_id' => WideEvent::get('request_id') ?? '',
        ], 'emails');

        if ($enqueued) {
            Logger::info('Email genérico encolado', [
                'to' => $to,
                'subject' => $subject,
            ]);
        }

        return $enqueued;
    }
}
