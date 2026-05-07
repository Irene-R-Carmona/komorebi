<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Cache;
use App\Core\Env;
use App\Core\Logger;
use App\Exceptions\ExternalServiceException;
use Override;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Job para envío de emails asíncrono
 *
 * Encapsula el envío de correos usando PHPMailer para evitar
 * bloquear las peticiones HTTP mientras se envían emails.
 *
 * Payload esperado:
 * - to: string (email destinatario)
 * - subject: string (asunto)
 * - body: string (cuerpo HTML)
 * - from: array{email: string, name: string} (opcional)
 * - cc: array<string> (opcional)
 * - bcc: array<string> (opcional)
 * - attachments: array<string> (opcional, rutas de archivos)

 */
final class SendEmailJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload Datos del email
     * @throws ExternalServiceException Si falla el envío del email
     */
    #[Override]
    public function handle(array $payload): void
    {
        $this->validatePayload($payload);

        $idempotencyKey = 'email_sent:' . ($payload['_correlation_id'] ?? \md5(\serialize($payload)));

        if (Cache::has($idempotencyKey)) {
            Logger::info('[SendEmailJob] Skipped duplicate (idempotency key already set)', [
                'key' => $idempotencyKey,
            ]);

            return;
        }

        try {
            $mail = $this->createMailer();
            $this->configureEmailRecipients($mail, $payload);
            $this->configureEmailContent($mail, $payload);
            $this->configureEmailAttachments($mail, $payload);
            $this->sendEmail($mail, $payload);
            Cache::set($idempotencyKey, true, 86400);
        } catch (PHPMailerException $e) {
            $this->handleMailerException($e, $payload);
        } catch (Throwable $e) {
            $this->handleUnexpectedException($e, $payload);
        }
    }

    private function configureEmailRecipients(PHPMailer $mail, array $payload): void
    {
        $to = isset($payload['to']) ? (string) $payload['to'] : '';
        if ($to === '' || !\filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new ExternalServiceException('Destinatario inválido en SendEmailJob', 'SendEmailJob');
        }
        $mail->addAddress($to);

        if (isset($payload['from'])) {
            $mail->setFrom(
                $payload['from']['email'],
                $payload['from']['name'] ?? ''
            );
        }

        if (isset($payload['cc']) && \is_array($payload['cc'])) {
            foreach ($payload['cc'] as $cc) {
                $ccEmail = (string) $cc;
                if ($ccEmail !== '' && \filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                }
            }
        }

        if (isset($payload['bcc']) && \is_array($payload['bcc'])) {
            foreach ($payload['bcc'] as $bcc) {
                $bccEmail = (string) $bcc;
                if ($bccEmail !== '' && \filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bccEmail);
                }
            }
        }
    }

    private function configureEmailContent(PHPMailer $mail, array $payload): void
    {
        $mail->Subject = $payload['subject'];
        $mail->Body = $payload['body'];
        $mail->isHTML(true);
    }

    private function configureEmailAttachments(PHPMailer $mail, array $payload): void
    {
        // Soporte para adjunto único (attachment_path + attachment_name)
        if (isset($payload['attachment_path']) && \file_exists($payload['attachment_path'])) {
            $attachmentName = $payload['attachment_name'] ?? \basename($payload['attachment_path']);
            $mail->addAttachment($payload['attachment_path'], $attachmentName);
            Logger::debug('[SendEmailJob] Adjunto añadido', [
                'path' => $payload['attachment_path'],
                'name' => $attachmentName,
            ]);
        }

        // Soporte para múltiples adjuntos (attachments array)
        if (!isset($payload['attachments']) || !\is_array($payload['attachments'])) {
            return;
        }

        foreach ($payload['attachments'] as $attachment) {
            if (\file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }
    }

    private function sendEmail(PHPMailer $mail, array $payload): void
    {
        if (!$mail->send()) {
            throw new ExternalServiceException('Error al enviar email: ' . $mail->ErrorInfo);
        }

        Logger::info('[SendEmailJob] Email enviado correctamente', [
            'to' => (string) ($payload['to'] ?? ''),
            'subject' => (string) ($payload['subject'] ?? ''),
        ]);
    }

    private function handleMailerException(PHPMailerException $e, array $payload): void
    {
        Logger::error('[SendEmailJob] Error de PHPMailer', [
            'to' => $payload['to'] ?? 'unknown',
            'error' => $e->getMessage(),
        ]);

        throw new ExternalServiceException(
            'Error al enviar email: ' . $e->getMessage(),
            'PHPMailer'
        );
    }

    private function handleUnexpectedException(Throwable $e, array $payload): void
    {
        Logger::error('[SendEmailJob] Error inesperado al enviar email', [
            'to' => $payload['to'] ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw $e;
    }

    /**
     * @return PHPMailer
     * @throws PHPMailerException
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        // Configuración SMTP desde variables de entorno
        $mail->isSMTP();
        $mail->Host = Env::get('MAIL_HOST', 'localhost');
        $mail->Port = (int) Env::get('MAIL_PORT', '1025');
        $mail->SMTPAuth = false; // Mailpit no requiere autenticación en dev

        // En producción, habilitar autenticación
        if (Env::get('APP_ENV', 'production') === 'production') {
            $mail->SMTPAuth = true;
            $mail->Username = Env::get('MAIL_USERNAME');
            $mail->Password = Env::get('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Remitente por defecto
        $defaultFrom = Env::get('MAIL_FROM_ADDRESS', 'noreply@komorebi.local');
        $defaultName = Env::get('MAIL_FROM_NAME', 'Komorebi Café');
        $mail->setFrom($defaultFrom, $defaultName);

        // Charset UTF-8
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ExternalServiceException Si falta algún campo requerido
     */
    private function validatePayload(array $payload): void
    {
        $required = ['to', 'subject', 'body'];

        foreach ($required as $field) {
            if (!isset($payload[$field]) || empty($payload[$field])) {
                throw new ExternalServiceException(
                    "Campo requerido ausente en SendEmailJob: {$field}",
                    'SendEmailJob'
                );
            }
        }

        // Validar formato de email
        if (!\filter_var($payload['to'], FILTER_VALIDATE_EMAIL)) {
            throw new ExternalServiceException(
                "Email inválido en SendEmailJob: {$payload['to']}",
                'SendEmailJob'
            );
        }
    }
}
