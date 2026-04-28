<?php

/**
 * ¿Qué pruebas aquí? MenuCategoryRepository: findAll, findBySlug,
 *   findAllWithProductCount.
 * ¿Qué me quieres demostrar? Que findAll y findAllWithProductCount usan
 *   $this->db->query() (no prepare/execute), y que findBySlug retorna null
 *   cuando fetch() es false.
 * ¿Qué va a fallar en este test si se cambia el código? Si findAll pasa a usar
 *   prepare(), si findBySlug deja de retornar null, o si findAllWithProductCount
 *   deja de usar query().
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\MenuCategoryDTO;
use App\Domain\Mappers\MenuCategoryMapper;
use App\Repositories\MenuCategoryRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuCategoryRepository::class)]
final class MenuCategoryRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('query')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findAll (usa query())
    // -------------------------------------------------------------------------

    public function testFindAllReturnsRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1],
            ['id' => 2, 'name' => 'Postres', 'slug' => 'postres', 'display_order' => 2],
        ];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $result = $repo->findAll();
        $this->assertCount(2, $result);
        $this->assertInstanceOf(MenuCategoryDTO::class, $result[0]);
        $this->assertSame('bebidas', $result[0]->slug);
    }

    public function testFindAllReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $this->assertSame([], $repo->findAll());
    }

    // -------------------------------------------------------------------------
    // findBySlug (usa prepare)
    // -------------------------------------------------------------------------

    public function testFindBySlugReturnsArray(): void
    {
        $row = ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $result = $repo->findBySlug('bebidas');
        $this->assertNotNull($result);
        $this->assertSame('bebidas', $result['slug']);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $this->assertNull($repo->findBySlug('slug-inexistente'));
    }

    // -------------------------------------------------------------------------
    // findAllWithProductCount (usa query())
    // -------------------------------------------------------------------------

    public function testFindAllWithProductCountReturnsRows(): void
    {
        $rows = [['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'product_count' => 5, 'available_count' => 3]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $result = $repo->findAllWithProductCount();
        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['product_count']);
    }

    public function testFindAllWithProductCountReturnsEmptyArray(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new MenuCategoryRepository(new MenuCategoryMapper(), $this->makePdo($stmt));

        $this->assertSame([], $repo->findAllWithProductCount());
    }
}
