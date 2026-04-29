<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? NewsletterService: validación de email y detección de suscripción duplicada.
 * ¿Qué me quieres demostrar? Que subscribe retorna fail con email inválido o ya confirmado.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la validación de email o de duplicados.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\NewsletterSubscriptionDTO;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use App\Services\NewsletterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterService::class)]
final class NewsletterServiceTest extends TestCase
{
    private NewsletterSubscriptionRepositoryInterface $repoStub;
    private NewsletterService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(NewsletterSubscriptionRepositoryInterface::class);
        $this->service  = new NewsletterService($this->repoStub);
    }

    public function testSubscribeFailsWithInvalidEmail(): void
    {
        $result = $this->service->subscribe('not-an-email');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválido', $result->error);
    }

    public function testSubscribeFailsWhenAlreadyConfirmed(): void
    {
        $this->repoStub->method('findByEmail')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'              => 1,
                'email'           => 'user@example.com',
                'confirmed_at'    => '2025-01-01 00:00:00',
                'unsubscribed_at' => null,
            ])
        );

        $result = $this->service->subscribe('user@example.com');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('ya está suscrito', $result->error);
    }

    public function testGetConfirmedEmailsDelegatesToRepository(): void
    {
        $this->repoStub->method('getConfirmedEmails')->willReturn(['a@a.com', 'b@b.com']);

        $emails = $this->service->getConfirmedEmails();

        $this->assertCount(2, $emails);
    }

    public function testConfirmReturnsFalseForInvalidToken(): void
    {
        $this->repoStub->method('findByToken')->willReturn(null);

        $result = $this->service->confirm('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['message']);
    }

    public function testConfirmSucceedsForValidToken(): void
    {
        $this->repoStub->method('findByToken')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'           => 1,
                'email'        => 'user@example.com',
                'confirmed_at' => null,
            ])
        );

        $result = $this->service->confirm('valid-token');

        $this->assertTrue($result['success']);
        $this->assertSame('user@example.com', $result['email']);
    }

    public function testUnsubscribeReturnsFalseForInvalidToken(): void
    {
        $this->repoStub->method('findByToken')->willReturn(null);

        $result = $this->service->unsubscribe('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['message']);
    }

    public function testUnsubscribeSucceedsForValidToken(): void
    {
        $this->repoStub->method('findByToken')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'           => 1,
                'email'        => 'user@example.com',
                'confirmed_at' => '2025-01-01 00:00:00',
            ])
        );

        $result = $this->service->unsubscribe('valid-token');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('baja', $result['message']);
    }

    public function testConfirmFailsWhenAlreadyConfirmed(): void
    {
        $this->repoStub->method('findByToken')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'           => 1,
                'email'        => 'user@example.com',
                'confirmed_at' => '2025-01-01 00:00:00',
            ])
        );

        $result = $this->service->confirm('already-confirmed-token');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('confirmada', $result['message']);
    }

    public function testSubscribeNewEmailSendsConfirmationAndReturnsOk(): void
    {
        $this->repoStub->method('findByEmail')->willReturn(null);

        $result = $this->service->subscribe('nuevo@example.com');

        $this->assertTrue($result->ok);
    }

    public function testSubscribeReactivatesUnsubscribedEmailAndReturnsOk(): void
    {
        $this->repoStub->method('findByEmail')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'              => 1,
                'email'           => 'baja@example.com',
                'confirmed_at'    => null,
                'unsubscribed_at' => '2025-01-01 00:00:00',
            ])
        );

        $result = $this->service->subscribe('baja@example.com');

        $this->assertTrue($result->ok);
    }

    public function testSubscribeResendsConfirmationForUnconfirmedEmailAndReturnsOk(): void
    {
        $this->repoStub->method('findByEmail')->willReturn(
            NewsletterSubscriptionDTO::fromArray([
                'id'              => 2,
                'email'           => 'pendiente@example.com',
                'confirmed_at'    => null,
                'unsubscribed_at' => null,
                'token'           => 'existingtoken123',
            ])
        );
        $this->repoStub->method('getTokenByEmail')->willReturn('existingtoken123');

        $result = $this->service->subscribe('pendiente@example.com');

        $this->assertTrue($result->ok);
    }
}
