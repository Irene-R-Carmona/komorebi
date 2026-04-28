<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Repositories;

use App\Domain\DTO\UserDTO;
use App\Repositories\UserRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests para UserRepository
 *
 * Demuestra el patrón Repository con mocks de PDO para unit testing,
 * sin necesidad de base de datos real.
 */
#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&PDO */
    private PDO $pdoMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&PDOStatement */
    private PDOStatement $stmtMock;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new UserRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->pdoMock, $this->stmtMock);
    }

    public function testFindByIdReturnsUser(): void
    {
        $expectedData = [
            'id' => 1,
            'uuid' => 'uuid-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_active' => 1,
            'cafe_id' => null,
            'created_at' => '2026-01-01 00:00:00',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['id' => 1])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findById(1);

        $this->assertInstanceOf(UserDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    public function testFindByEmailReturnsUser(): void
    {
        $expectedData = [
            'id' => 5,
            'email' => 'user@example.com',
            'name' => 'Email User',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['email' => 'user@example.com'])
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findByEmail('user@example.com');

        $this->assertIsArray($result);
        $this->assertEquals('user@example.com', $result['email']);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->findByEmail('notfound@example.com');

        $this->assertNull($result);
    }

    public function testEmailExistsReturnsTrue(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(['1' => 1]);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->emailExists('existing@example.com');

        $this->assertTrue($result);
    }

    public function testEmailExistsReturnsFalse(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->emailExists('notfound@example.com');

        $this->assertFalse($result);
    }

    public function testCreateInsertsUser(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'hashed_password',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $this->pdoMock
            ->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('42');

        $result = $this->repository->create($userData);

        $this->assertEquals(42, $result);
    }

    public function testUpdateModifiesUser(): void
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->update(1, $updateData);

        $this->assertTrue($result);
    }

    public function testDeleteSoftDeletesUser(): void
    {
        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->stmtMock);

        $result = $this->repository->delete(1);

        $this->assertTrue($result);
    }

    public function testUpdatePasswordHashesPasswordInternally(): void
    {
        $plainPassword = 'secret_password_123';
        $userId = 42;

        $this->stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params) use ($userId, $plainPassword): bool {
                return isset($params['password'])
                    && \password_verify($plainPassword, (string) $params['password'])
                    && isset($params['id'])
                    && $params['id'] === $userId;
            }))
            ->willReturn(true);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('password'))
            ->willReturn($this->stmtMock);

        $result = $this->repository->updatePassword($userId, $plainPassword);

        $this->assertTrue($result);
    }
}
