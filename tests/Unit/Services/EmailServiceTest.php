<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? EmailService: encolado de emails cuando Redis no está disponible.
 * ¿Qué me quieres demostrar? Que los métodos de envío retornan bool sin lanzar excepciones.
 * ¿Qué va a fallar en este test si se cambia el código? Si send() lanza excepciones en lugar de retornar bool.
 */

namespace Tests\Unit\Services;

use App\Services\EmailService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailService::class)]
final class EmailServiceTest extends TestCase
{
    public function testSendReturnsBoolWithoutThrowing(): void
    {
        $service = new EmailService();
        $result  = $service->send('test@example.com', 'Asunto', '<p>Cuerpo</p>');

        $this->assertIsBool($result);
    }

    public function testSendVerificationEmailReturnsBoolWithoutThrowing(): void
    {
        $service = new EmailService();
        $result  = $service->sendVerificationEmail('test@example.com', 'Usuario', 'https://example.com/verify');

        $this->assertIsBool($result);
    }

    public function testSendPasswordResetEmailReturnsBoolWithoutThrowing(): void
    {
        $service = new EmailService();
        $result  = $service->sendPasswordResetEmail(
            'reset@example.com',
            'Usuario Test',
            'https://example.com/reset?token=abc123'
        );

        $this->assertIsBool($result);
    }

    public function testSendReservationConfirmationWithStringUserNameReturnsBool(): void
    {
        $service = new EmailService();
        $result  = $service->sendReservationConfirmation(
            'user@example.com',
            'Juan García',
            [
                'date'   => '2025-01-15',
                'time'   => '14:00',
                'cafe'   => 'Komorebi Central',
                'guests' => 2,
            ]
        );

        $this->assertIsBool($result);
    }

    public function testSendReservationConfirmationWithArrayAsSecondParamReturnsBool(): void
    {
        // Backwards-compatible call: sendReservationConfirmation(email, reservationData)
        $service = new EmailService();
        $result  = $service->sendReservationConfirmation(
            'user@example.com',
            ['date' => '2025-01-15', 'time' => '14:00', 'cafe' => 'Komorebi', 'guests' => 2]
        );

        $this->assertIsBool($result);
    }
}
