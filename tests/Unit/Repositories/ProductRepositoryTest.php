<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios para los nuevos métodos de ProductRepository:
 * findAvailablePasses, existsAndActivePass y findItemsByIds.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos aplican los filtros correctos (product_type, is_active)
 * y que findItemsByIds usa parámetros posicionales seguros.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambian los filtros SQL, el tipo de producto esperado,
 * o si findItemsByIds devuelve resultados para IDs vacíos.
 */

namespace Tests\Unit\Repositories;

use App\Repositories\ProductRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductRepository::class)]
final class ProductRepositoryTest extends TestCase
{
    /** @var MockObject&PDO */
    private PDO $pdoMock;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->repository = new ProductRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->pdoMock);
    }

    public function testFindAvailablePassesQueriesCorrectProductType(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $expectedData = [
            ['id' => 1, 'name' => 'Pase 1h', 'price' => 1500, 'duration_minutes' => 60],
            ['id' => 2, 'name' => 'Pase 2h', 'price' => 2500, 'duration_minutes' => 120],
        ];

        $stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('query')
            ->with($this->logicalAnd(
                $this->stringContains("product_type = 'pass'"),
                $this->stringContains('is_active = 1')
            ))
            ->willReturn($stmtMock);

        $result = $this->repository->findAvailablePasses();

        $this->assertCount(2, $result);
        $this->assertSame('Pase 1h', $result[0]['name']);
    }

    public function testExistsAndActivePassReturnsTrueWhenFound(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with(['id' => 5])
            ->willReturn(true);

        $stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(['id' => 5]);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains("product_type = 'pass'"),
                $this->stringContains('is_active = 1')
            ))
            ->willReturn($stmtMock);

        $this->assertTrue($this->repository->existsAndActivePass(5));
    }

    public function testExistsAndActivePassReturnsFalseWhenNotFound(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmtMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($stmtMock);

        $this->assertFalse($this->repository->existsAndActivePass(999));
    }

    public function testFindItemsByIdsReturnsEmptyArrayForEmptyInput(): void
    {
        $this->pdoMock
            ->expects($this->never())
            ->method('prepare');

        $result = $this->repository->findItemsByIds([]);

        $this->assertSame([], $result);
    }

    public function testFindItemsByIdsExecutesParameterizedQuery(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $expectedData = [
            ['id' => 10, 'name' => 'Matcha Latte', 'price' => 650],
            ['id' => 20, 'name' => 'Croissant', 'price' => 350],
        ];

        $stmtMock
            ->expects($this->once())
            ->method('execute')
            ->with([10, 20])
            ->willReturn(true);

        $stmtMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expectedData);

        $this->pdoMock
            ->expects($this->once())
            ->method('prepare')
            ->with($this->logicalAnd(
                $this->stringContains('IN (?,?)'),
                $this->stringContains("product_type = 'item'")
            ))
            ->willReturn($stmtMock);

        $result = $this->repository->findItemsByIds([10, 20]);

        $this->assertCount(2, $result);
        $this->assertSame('Matcha Latte', $result[0]['name']);
    }
}
