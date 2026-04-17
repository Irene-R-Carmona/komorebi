<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios de los métodos de control de stock en ProductRepository:
 * hasStock(), decrementStock() e incrementStock().
 *
 * ¿Qué me quieres demostrar?
 * Que hasStock() respeta la semántica de NULL=ilimitado, que decrementStock()
 * usa SELECT FOR UPDATE y sólo reduce si hay stock suficiente, y que
 * incrementStock() aumenta correctamente o no-op si es ilimitado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si hasStock() deja de consultar stock_quantity directamente o de aceptar NULL como ilimitado.
 * - Si decrementStock() no comprueba stock antes de actualizar o ignora el resultado de rowCount().
 * - Si incrementStock() modifica filas cuando stock_quantity es NULL.
 */

namespace Repositories;

use App\Repositories\ProductRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para los métodos de stock de ProductRepository.
 */
#[AllowMockObjectsWithoutExpectations]
final class ProductRepositoryStockTest extends TestCase
{
    private ProductRepository $repository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\PDO */
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = $this->createMock(PDO::class);
        $this->repository = new ProductRepository($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->db);
    }

    // -------------------------------------------------------------------------
    // hasStock()
    // -------------------------------------------------------------------------

    public function testHasStockReturnsTrueForUnlimitedProduct(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => null,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->repository->hasStock(1, 99));
    }

    public function testHasStockReturnsTrueWhenEnoughStock(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => 10,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->repository->hasStock(1, 5));
    }

    public function testHasStockReturnsFalseWhenInsufficientStock(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => 2,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->hasStock(1, 5));
    }

    public function testHasStockReturnsFalseForInactiveProduct(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => 100,
            'is_active' => 0,
            'deleted_at' => null,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->hasStock(1));
    }

    public function testHasStockReturnsFalseForDeletedProduct(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => 100,
            'is_active' => 1,
            'deleted_at' => '2024-01-01 00:00:00',
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->hasStock(1));
    }

    public function testHasStockReturnsFalseWhenProductNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->hasStock(999));
    }

    public function testHasStockReturnsTrueWhenStockExactlyEqualsQuantity(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'stock_quantity' => 3,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->repository->hasStock(1, 3));
    }

    // -------------------------------------------------------------------------
    // decrementStock()
    // -------------------------------------------------------------------------

    public function testDecrementStockReturnsTrueForUnlimitedProduct(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['stock_quantity' => null]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->repository->decrementStock(1, 3));
    }

    public function testDecrementStockReturnsFalseWhenProductNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->decrementStock(999, 1));
    }

    public function testDecrementStockReturnsFalseWhenInsufficientStock(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['stock_quantity' => 2]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->decrementStock(1, 5));
    }

    public function testDecrementStockReturnsTrueOnSuccessfulUpdate(): void
    {
        // Primer prepare → SELECT FOR UPDATE
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetch')->willReturn(['stock_quantity' => 10]);

        // Segundo prepare → UPDATE
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);

        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $updateStmt);

        $this->assertTrue($this->repository->decrementStock(1, 3));
    }

    public function testDecrementStockReturnsFalseWhenUpdateAffectsNoRows(): void
    {
        // Primer prepare → SELECT FOR UPDATE
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute')->willReturn(true);
        $selectStmt->method('fetch')->willReturn(['stock_quantity' => 5]);

        // Segundo prepare → UPDATE (rowCount=0 por race condition)
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(0);

        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($selectStmt, $updateStmt);

        $this->assertFalse($this->repository->decrementStock(1, 3));
    }

    // -------------------------------------------------------------------------
    // incrementStock()
    // -------------------------------------------------------------------------

    public function testIncrementStockReturnsTrueForUnlimitedProduct(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['stock_quantity' => null]);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertTrue($this->repository->incrementStock(1, 5));
    }

    public function testIncrementStockReturnsFalseWhenProductNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        $this->assertFalse($this->repository->incrementStock(999, 1));
    }

    public function testIncrementStockReturnsTrueOnSuccessfulUpdate(): void
    {
        // Primer prepare → SELECT de verificación
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('execute')->willReturn(true);
        $checkStmt->method('fetch')->willReturn(['stock_quantity' => 5]);

        // Segundo prepare → UPDATE
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);

        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $updateStmt);

        $this->assertTrue($this->repository->incrementStock(1, 10));
    }

    public function testIncrementStockReturnsFalseWhenUpdateAffectsNoRows(): void
    {
        // Primer prepare → SELECT de verificación
        $checkStmt = $this->createMock(PDOStatement::class);
        $checkStmt->method('execute')->willReturn(true);
        $checkStmt->method('fetch')->willReturn(['stock_quantity' => 5]);

        // Segundo prepare → UPDATE falla (e.g. producto eliminado entre las dos queries)
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(0);

        $this->db->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($checkStmt, $updateStmt);

        $this->assertFalse($this->repository->incrementStock(1, 10));
    }
}
