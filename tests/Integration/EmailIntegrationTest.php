<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de EmailService (NotificationService)
 *
 * Valida operaciones de envío de emails con sistema de colas.
 * EmailService actúa como servicio de notificaciones del proyecto.
 *
 * Tests validan que los métodos retornen true (enqueue exitoso).
 */

namespace Tests\Integration;

use App\Core\Queue;
use App\Services\EmailService;
use Override;
use Tests\Support\BaseIntegrationTest;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class EmailIntegrationTest extends BaseIntegrationTest
{
    private EmailService $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        Queue::clear();
        $this->service = new EmailService();
        \putenv('MAIL_FROM_ADDRESS=test@komorebi.test');
        \putenv('MAIL_FROM_NAME=Komorebi Integration Test');
    }

    #[Override]
    protected function tearDown(): void
    {
        \putenv('MAIL_FROM_ADDRESS');
        \putenv('MAIL_FROM_NAME');
        Queue::clear();
        parent::tearDown();
    }

    public function testSendVerificationEmailEnqueuesJobSuccessfully(): void
    {
        $result = $this->service->sendVerificationEmail(
            'user@example.com',
            'Integration Test User',
            'https://komorebi.test/verify/token123'
        );

        $this->assertTrue($result);
    }

    public function testSendReservationConfirmationEnqueuesJob(): void
    {
        $reservationData = [
            'user_name' => 'John Doe',
            'reservation_id' => 'RES-2026-001',
            'cafe_name' => 'Neko Cat Café',
        ];

        $result = $this->service->sendReservationConfirmation(
            'customer@example.com',
            $reservationData
        );

        $this->assertTrue($result);
    }

    public function testSendGenericMethodEnqueuesEmail(): void
    {
        $result = $this->service->send(
            'admin@example.com',
            'Test Email',
            '<p>Test body</p>'
        );

        $this->assertTrue($result);
    }

    public function testSendWaitlistConfirmationEnqueuesNotification(): void
    {
        $waitlistData = [
            'cafe_name' => 'Inu Dog Café',
            'date' => '2026-04-20',
            'time' => '16:00',
            'position' => 3,
        ];

        $result = $this->service->sendWaitlistConfirmation(
            'waitlist@example.com',
            'Jane Smith',
            'wl_token_abc123xyz',
            $waitlistData
        );

        $this->assertTrue($result);
    }

    public function testSendPasswordResetEmailEnqueuesJob(): void
    {
        $result = $this->service->sendPasswordResetEmail(
            'forgot@example.com',
            'Bob Johnson',
            'https://komorebi.test/auth/reset?token=xyz'
        );

        $this->assertTrue($result);
    }

    public function testSendReservationCancellationEnqueuesJob(): void
    {
        $reservationData = [
            'reservation_id' => 'RES-2026-999',
            'cafe_name' => 'Test Café',
        ];

        $result = $this->service->sendReservationCancellation(
            'cancel@example.com',
            'Test User',
            $reservationData,
            'Usuario canceló'
        );

        $this->assertTrue($result);
    }
}
