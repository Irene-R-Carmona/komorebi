<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Services\NewsletterService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests para NewsletterService
 *
 * Verifica:
 * - Suscripción a newsletter
 * - Validación de emails
 * - Prevención de duplicados
 */
final class NewsletterServiceTest extends TestCase
{
    private NewsletterService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(PDO::class);
        $this->service = new NewsletterService($this->dbMock);
    }

    public function testSubscribeWithValidEmailReturnsSuccess(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('rowCount')->willReturn(1);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->subscribe('test@example.com');

        $this->assertTrue($result['success']);
    }

    public function testSubscribeWithInvalidEmailReturnsError(): void
    {
        $result = $this->service->subscribe('invalid-email');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('válido', strtolower($result['message'] ?? ''));
    }

    public function testSubscribeWithEmptyEmailReturnsError(): void
    {
        $result = $this->service->subscribe('');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testConfirmWithValidTokenReturnsSuccess(): void
    {
        // Mock para SELECT
        $stmtSelect = $this->createStub(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn([
            'id' => 1,
            'email' => 'test@example.com',
            'confirmed_at' => null
        ]);

        // Mock para UPDATE
        $stmtUpdate = $this->createStub(PDOStatement::class);
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
        $stmtSelect = $this->createStub(PDOStatement::class);
        $stmtSelect->method('execute')->willReturn(true);
        $stmtSelect->method('fetch')->willReturn([
            'id' => 1,
            'email' => 'test@example.com'
        ]);

        // Mock para UPDATE
        $stmtUpdate = $this->createStub(PDOStatement::class);
        $stmtUpdate->method('execute')->willReturn(true);

        $this->dbMock->method('prepare')
            ->willReturnOnConsecutiveCalls($stmtSelect, $stmtUpdate);

        $result = $this->service->unsubscribe('valid-token-123');

        $this->assertTrue($result['success']);
    }

    public function testUnsubscribeWithInvalidTokenReturnsError(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false); // No encontrado

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $result = $this->service->unsubscribe('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetConfirmedEmailsReturnsArray(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([
            'user1@example.com',
            'user2@example.com'
        ]);

        $this->dbMock->method('query')->willReturn($stmtMock);

        $emails = $this->service->getConfirmedEmails();

        $this->assertIsArray($emails);
        $this->assertCount(2, $emails);
    }
}
