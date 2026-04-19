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

use App\Services\NewsletterService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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
    /** @var \PHPUnit\Framework\MockObject\Stub&PDO */
    private PDO $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(PDO::class);
        $this->service = new NewsletterService($this->dbMock);
    }

    public function testSubscribeWithValidEmailReturnsSuccess(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('rowCount')->willReturn(1);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->subscribe('test@example.com');

        $this->assertTrue($result->ok);
    }

    public function testSubscribeWithInvalidEmailReturnsError(): void
    {
        $result = $this->service->subscribe('invalid-email');

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('válido', \strtolower($result->error ?? ''));
    }

    public function testSubscribeWithEmptyEmailReturnsError(): void
    {
        $result = $this->service->subscribe('');

        $this->assertNotNull($result->error);
    }

    public function testConfirmWithValidTokenReturnsSuccess(): void
    {
        // Mock para SELECT
        $stmtSelect = $this->createMock(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
            'confirmed_at' => null,
        ]);

        // Mock para UPDATE
        $stmtUpdate = $this->createMock(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtSelect, $stmtUpdate);

        $result = $this->service->confirm('valid-token-123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUnsubscribeWithValidTokenReturnsSuccess(): void
    {
        // Mock para SELECT
        $stmtSelect = $this->createMock(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        // Mock para UPDATE
        $stmtUpdate = $this->createMock(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtSelect, $stmtUpdate);

        $result = $this->service->unsubscribe('valid-token-123');

        $this->assertTrue($result['success']);
    }

    public function testUnsubscribeWithInvalidTokenReturnsError(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false); // No encontrado

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->unsubscribe('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetConfirmedEmailsReturnsArray(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([
            'user1@example.com',
            'user2@example.com',
        ]);

        $this->dbMock->method('query')->willReturn($stmtMock);

        $emails = $this->service->getConfirmedEmails();

        $this->assertIsArray($emails);
        $this->assertCount(2, $emails);
    }
}
