<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? El método transact() de TransactionalService.
 * ¿Qué me quieres demostrar? Que transact() hace commit y retorna Result::ok en éxito, y rollback con Result::fail en excepción.
 * ¿Qué va a fallar en este test si se cambia el código? Si se pierde el rollback o se cambia el manejo de la excepción.
 */

use App\Core\Result;
use App\Core\TransactionalService;
use PHPUnit\Framework\TestCase;

final class ConcreteTransactionalService extends TransactionalService
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
    }

    public function runTransact(callable $fn): Result
    {
        return $this->transact($fn);
    }
}

final class TransactionalServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&\PDO */
    private PDO $pdoMock;
    private ConcreteTransactionalService $service;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->service = new ConcreteTransactionalService($this->pdoMock);
    }

    public function testTransactCommitsAndReturnsOkOnSuccess(): void
    {
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('commit')->willReturn(true);

        $result = $this->service->runTransact(fn () => Result::ok('data'));

        $this->assertTrue($result->ok);
        $this->assertSame('data', $result->data);
    }

    public function testTransactRollsBackAndReturnsFailOnException(): void
    {
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('rollBack')->willReturn(true);

        $result = $this->service->runTransact(function () {
            throw new \RuntimeException('DB error');
        });

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('DB error', $result->getMessage());
    }

    public function testTransactPropagatesFailResultWithRollback(): void
    {
        $this->pdoMock->method('beginTransaction')->willReturn(true);
        $this->pdoMock->method('rollBack')->willReturn(true);

        $result = $this->service->runTransact(fn () => Result::fail('negocio falló', 'business_error'));

        $this->assertFalse($result->ok);
        $this->assertSame('negocio falló', $result->getMessage());
    }
}
