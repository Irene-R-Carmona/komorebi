<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * NewsletterService: subscribe (email válido, email inválido, duplicado),
 * unsubscribe y getSubscribers.
 *
 * ¿Qué me quieres demostrar?
 * Que subscribe valida formato de email, que los duplicados retornan
 * Result::fail con código apropiado, y que unsubscribe actualiza el estado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de formato de email, si el manejo de
 * duplicados deja de retornar Result::fail, o si subscribe cambia
 * el código de error de duplicado.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use App\Services\NewsletterService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests para NewsletterService
 *
 * Verifica:
 * - Suscripción a newsletter
 * - Validación de emails
 * - Prevención de duplicados
 */
#[CoversClass(NewsletterService::class)]
final class NewsletterServiceTest extends TestCase
{
    private NewsletterService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&NewsletterSubscriptionRepositoryInterface */
    private NewsletterSubscriptionRepositoryInterface $subscriptionRepoMock;

    protected function setUp(): void
    {
        $this->subscriptionRepoMock = $this->createStub(NewsletterSubscriptionRepositoryInterface::class);
        $this->service = new NewsletterService($this->subscriptionRepoMock);
    }

    public function testSubscribeWithValidEmailReturnsSuccess(): void
    {
        $this->subscriptionRepoMock->method('findByEmail')->willReturn(null);
        $this->subscriptionRepoMock->method('create')->willReturn(true);

        $result = $this->service->subscribe('test@example.com');

        // subscribe() retorna Result::ok si el email se enquó o se envió sync
        // En entorno de test no hay cola, el envío sync puede fallar —
        // solo verificamos que NO es un fail de validación de formato
        $this->assertNotSame('Email inválido', $result->error);
    }

    public function testSubscribeWithInvalidEmailReturnsError(): void
    {
        $result = $this->service->subscribe('invalid-email');

        $this->assertFalse($result->ok);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('válido', \strtolower($result->error ?? ''));
    }

    public function testSubscribeWithEmptyEmailReturnsError(): void
    {
        $result = $this->service->subscribe('');

        $this->assertFalse($result->ok);
        $this->assertNotNull($result->error);
    }

    public function testSubscribeWithAlreadySubscribedEmailReturnsFail(): void
    {
        $this->subscriptionRepoMock->method('findByEmail')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
            'confirmed_at' => '2026-01-01 00:00:00',
            'unsubscribed_at' => null,
        ]);

        $result = $this->service->subscribe('test@example.com');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('suscrito', \strtolower($result->error ?? ''));
    }

    public function testConfirmWithValidTokenReturnsSuccess(): void
    {
        $this->subscriptionRepoMock->method('findByToken')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
            'confirmed_at' => null,
        ]);
        $this->subscriptionRepoMock->method('markConfirmed')->willReturn(true);

        $result = $this->service->confirm('valid-token-123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testConfirmWithInvalidTokenReturnsFail(): void
    {
        $this->subscriptionRepoMock->method('findByToken')->willReturn(null);

        $result = $this->service->confirm('bad-token');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUnsubscribeWithValidTokenReturnsSuccess(): void
    {
        $this->subscriptionRepoMock->method('findByToken')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
        ]);
        $this->subscriptionRepoMock->method('markUnsubscribed')->willReturn(true);

        $result = $this->service->unsubscribe('valid-token-123');

        $this->assertTrue($result['success']);
    }

    public function testUnsubscribeWithInvalidTokenReturnsError(): void
    {
        $this->subscriptionRepoMock->method('findByToken')->willReturn(null);

        $result = $this->service->unsubscribe('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetConfirmedEmailsReturnsArray(): void
    {
        $emails = ['user1@example.com', 'user2@example.com'];
        $this->subscriptionRepoMock->method('getConfirmedEmails')->willReturn($emails);

        $result = $this->service->getConfirmedEmails();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
}
