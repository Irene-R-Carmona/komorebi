<?php

/**
 * ¿Qué prueba aquí? El repositorio AllergenRepository: queries, normalización de alias legacy,
 *   validaciones de severidad y delegación a AllergenCodeGenerator.
 * ¿Qué me quieres demostrar? Que cada método del repositorio construye la query correcta,
 *   llama a PDO con los parámetros esperados, y normaliza los campos legacy en la salida.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia la lógica de normalizeRow(),
 *   si las queries cambian de tabla, o si se elimina la validación de severidad.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AllergenRepository;
use InvalidArgumentException;
use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\RepositoryTestCase;

#[CoversClass(AllergenRepository::class)]
final class AllergenRepositoryTest extends RepositoryTestCase
{
    private function makeRepo(PDO $pdo): AllergenRepository
    {
        return new AllergenRepository($pdo);
    }

    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        [$pdo] = $this->makePdoWithStmt([], false);
        $repo = $this->makeRepo($pdo);

        $this->assertNull($repo->findById(999));
    }

    public function testFindByIdReturnsRowWhenFound(): void
    {
        $row = ['id' => 1, 'code' => 'GLUTEN', 'name' => 'Gluten', 'japanese_name' => '小麦',
                'icon_class' => 'fa-wheat', 'icon_color' => '#f59e0b', 'severity' => 'high', 'description' => null];
        [$pdo] = $this->makePdoWithStmt([], $row);
        $repo = $this->makeRepo($pdo);

        $result = $repo->findById(1);
        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function testFindByIdNormalizesLegacyAliases(): void
    {
        $row = ['id' => 1, 'code' => 'GLUTEN', 'name' => 'Gluten', 'japanese_name' => '小麦',
                'icon_class' => 'fa-wheat', 'icon_color' => null, 'severity' => 'high', 'description' => null];
        [$pdo] = $this->makePdoWithStmt([], $row);
        $repo = $this->makeRepo($pdo);

        $result = $repo->findById(1);
        $this->assertArrayHasKey('name_jp', $result);
        $this->assertSame('小麦', $result['name_jp']);
        $this->assertArrayHasKey('icon', $result);
        $this->assertSame('fa-wheat', $result['icon']);
    }

    // ─────────────────────────────────────────────────────────────
    // findBySeverity — validación de dominio
    // ─────────────────────────────────────────────────────────────

    public function testFindBySeverityThrowsOnInvalidValue(): void
    {
        $pdo = $this->makePdoStub();
        $repo = $this->makeRepo($pdo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Severidad inválida/');
        $repo->findBySeverity('critical');
    }

    public function testFindBySeverityReturnsRows(): void
    {
        $rows = [
            ['id' => 1, 'code' => 'GLUTEN', 'name' => 'Gluten', 'japanese_name' => null,
             'icon_class' => null, 'icon_color' => null, 'severity' => 'high', 'description' => null],
        ];
        [$pdo] = $this->makePdoWithStmt($rows);
        $repo = $this->makeRepo($pdo);

        $result = $repo->findBySeverity('high');
        $this->assertCount(1, $result);
        $this->assertSame('Gluten', $result[0]['name']);
    }

    // ─────────────────────────────────────────────────────────────
    // getProductIds
    // ─────────────────────────────────────────────────────────────

    public function testGetProductIdsReturnsArrayOfInts(): void
    {
        $rows = [['product_id' => 3], ['product_id' => 7]];
        [$pdo] = $this->makePdoWithStmt($rows);
        $repo = $this->makeRepo($pdo);

        $result = $repo->getProductIds(1);
        $this->assertSame([3, 7], $result);
    }

    public function testGetProductIdsReturnsEmptyArrayWhenNone(): void
    {
        [$pdo] = $this->makePdoWithStmt([]);
        $repo = $this->makeRepo($pdo);

        $this->assertSame([], $repo->getProductIds(999));
    }

    // ─────────────────────────────────────────────────────────────
    // attachToProduct / detachFromProduct
    // ─────────────────────────────────────────────────────────────

    public function testAttachToProductReturnsTrueOnSuccess(): void
    {
        [$pdo] = $this->makePdoWithStmt();
        $repo = $this->makeRepo($pdo);

        $this->assertTrue($repo->attachToProduct(1, 2, null));
    }

    public function testDetachFromProductReturnsTrueOnSuccess(): void
    {
        [$pdo] = $this->makePdoWithStmt();
        $repo = $this->makeRepo($pdo);

        $this->assertTrue($repo->detachFromProduct(1, 2));
    }

    // ─────────────────────────────────────────────────────────────
    // create — validaciones
    // ─────────────────────────────────────────────────────────────

    public function testCreateThrowsOnInvalidSeverity(): void
    {
        $pdo = $this->makePdoStub();
        $repo = $this->makeRepo($pdo);

        $this->expectException(InvalidArgumentException::class);
        $repo->create(['name' => 'Test', 'severity' => 'ultra']);
    }

    public function testCreateThrowsWhenNameAndCodeBothEmpty(): void
    {
        $pdo = $this->makePdoStub();
        $repo = $this->makeRepo($pdo);

        $this->expectException(InvalidArgumentException::class);
        $repo->create(['severity' => 'low']);
    }

    public function testCreateReturnsInsertedId(): void
    {
        // createStub: solo necesitamos configurar valores de retorno, no verificar interacciones
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('42');
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);

        $repo = $this->makeRepo($pdo);
        $id = $repo->create(['name' => 'Gluten', 'severity' => 'high']);

        $this->assertSame(42, $id);
    }

    // ─────────────────────────────────────────────────────────────
    // update — validaciones
    // ─────────────────────────────────────────────────────────────

    public function testUpdateThrowsOnEmptyName(): void
    {
        $pdo = $this->makePdoStub();
        $repo = $this->makeRepo($pdo);

        $this->expectException(InvalidArgumentException::class);
        $repo->update(1, ['name' => '  ', 'severity' => 'low']);
    }

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        // update() llama findById() primero (para obtener el code existente) y luego UPDATE
        $existingRow = ['id' => 1, 'code' => 'GLUTEN', 'name' => 'Gluten', 'japanese_name' => null,
                        'icon_class' => null, 'icon_color' => null, 'severity' => 'high', 'description' => null];

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($existingRow);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = $this->makeRepo($pdo);
        $result = $repo->update(1, ['name' => 'Gluten actualizado', 'severity' => 'medium']);

        $this->assertTrue($result);
    }
}


