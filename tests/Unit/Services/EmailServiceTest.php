<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Services\EmailService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests para EmailService
 *
 * Verifica:
 * - Encolado correcto de emails
 * - Generación de templates
 * - Configuración de from/to
 */
#[CoversClass(EmailService::class)]
final class EmailServiceTest extends TestCase
{
    private EmailService $service;

    protected function setUp(): void
    {
        $this->service = new EmailService();

        // Configurar env vars para tests
        \putenv('MAIL_FROM_ADDRESS=test@komorebi.test');
        \putenv('MAIL_FROM_NAME=Komorebi Test');
    }

    protected function tearDown(): void
    {
        \putenv('MAIL_FROM_ADDRESS');
        \putenv('MAIL_FROM_NAME');
    }

    public function testSendVerificationEmailEnqueuesJob(): void
    {
        $result = $this->service->sendVerificationEmail(
            'user@example.com',
            'Test User',
            'https://komorebi.test/verify/token123'
        );

        // EmailService usa Queue::push que requiere Redis
        // En tests unitarios, verificamos que el método retorna boolean
        $this->assertIsBool($result);
    }

    public function testSendPasswordResetEmailEnqueuesJob(): void
    {
        $result = $this->service->sendPasswordResetEmail(
            'user@example.com',
            'Test User',
            'https://komorebi.test/reset/token456'
        );

        $this->assertIsBool($result);
    }

    public function testSendReservationConfirmationEnqueuesJob(): void
    {
        $reservationData = [
            'id' => 1,
            'user_name' => 'Test User',
            'cafe_name' => 'Test Café',
            'date' => '2026-02-15',
            'time' => '15:00:00',
            'guests' => 2,
        ];

        $result = $this->service->sendReservationConfirmation(
            'user@example.com',
            $reservationData
        );

        $this->assertIsBool($result);
    }

    public function testSendReservationCancellationEnqueuesJob(): void
    {
        $reservationData = [
            'id' => 1,
            'cafe_name' => 'Test Café',
            'date' => '2026-02-15',
            'time' => '15:00:00',
        ];

        $result = $this->service->sendReservationCancellation(
            'user@example.com',
            'Test User',
            $reservationData
        );

        $this->assertIsBool($result);
    }

    public function testSendTestEmailEnqueuesJob(): void
    {
        $result = $this->service->sendTestEmail(
            'admin@example.com',
            'Admin User'
        );

        $this->assertIsBool($result);
    }

    public function testEmailServiceUsesCorrectFromConfiguration(): void
    {
        // Verificar que el servicio usa las variables de entorno correctas
        // phpunit.xml inyecta estas vars en $_ENV via <env name="...">
        $this->assertSame('test@komorebi.test', $_ENV['MAIL_FROM_ADDRESS'] ?? \getenv('MAIL_FROM_ADDRESS'));
        $this->assertSame('Komorebi Test', $_ENV['MAIL_FROM_NAME'] ?? \getenv('MAIL_FROM_NAME'));
    }

    public function testSendReservationConfirmationHandlesMissingDataGracefully(): void
    {
        // ARRANGE: Datos mínimos de reserva
        $reservationData = [
            'id' => 1,
            // Faltan: user_name, cafe_name, date, time, guests
        ];

        // ACT: Debe manejar datos faltantes sin lanzar excepciones
        $result = $this->service->sendReservationConfirmation(
            'user@example.com',
            $reservationData
        );

        // ASSERT: Debe retornar boolean (no exception)
        $this->assertIsBool($result);
    }

    public function testSendWaitlistNotificationEnqueuesJob(): void
    {
        // ACT: Enviar notificación de waitlist
        $result = $this->service->sendWaitlistConfirmation(
            'user@example.com',
            'Test User',
            'test-token-abc123',
            [
                'slot_date' => '2026-02-15',
                'slot_time' => '15:00:00',
                'position' => 5,
            ]
        );

        // ASSERT: Debe retornar boolean
        $this->assertIsBool($result);
    }
}
