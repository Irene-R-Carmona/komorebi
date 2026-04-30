<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MenuCategory;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Métodos de consulta del modelo MenuCategory.
 * ¿Qué me quieres demostrar? Que findAll, findBySlug y findAllWithProductCount delegan en PDO.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en las queries o en el retorno.
 */
#[CoversClass(MenuCategory::class)]
final class MenuCategoryTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;
    private MenuCategory $model;

    protected function setUp(): void
    {
        $this->pdo = $this->createStub(PDO::class);
        $this->stmt = $this->createStub(PDOStatement::class);
        $this->model = new MenuCategory($this->pdo);
    }

    // ── findAll ──────────────────────────────────────────────────

    public function testFindAllReturnsArray(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1],
            ['id' => 2, 'name' => 'Comida', 'slug' => 'comida', 'display_order' => 2],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->findAll();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('bebidas', $result[0]['slug']);
    }

    public function testFindAllReturnsEmptyArrayWhenNoCategories(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->findAll();

        $this->assertSame([], $result);
    }

    // ── findBySlug ───────────────────────────────────────────────

    public function testFindBySlugReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1];
        $this->stmt->method('fetch')->willReturn($row);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->model->findBySlug('bebidas');

        $this->assertIsArray($result);
        $this->assertSame('bebidas', $result['slug']);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $this->stmt->method('fetch')->willReturn(false);
        $this->pdo->method('prepare')->willReturn($this->stmt);

        $result = $this->model->findBySlug('nonexistent');

        $this->assertNull($result);
    }

    // ── findAllWithProductCount ──────────────────────────────────

    public function testFindAllWithProductCountReturnsArray(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Bebidas', 'slug' => 'bebidas', 'display_order' => 1, 'product_count' => 5, 'available_count' => 4],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);
        $this->pdo->method('query')->willReturn($this->stmt);

        $result = $this->model->findAllWithProductCount();

        $this->assertIsArray($result);
        $this->assertSame(5, $result[0]['product_count']);
    }
}
