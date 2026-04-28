<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Favorite;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Métodos del modelo Favorite con stubs de PDO.
 * ¿Qué me quieres demostrar? Que add, remove, toggle, exists y las queries de listado delegan en PDO correctamente.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en lógica de toggle, en fetch o en fetchAll.
 */
#[CoversClass(Favorite::class)]
final class FavoriteTest extends TestCase
{
    private PDO $pdo;
    private PDOStatement $stmt;
    private Favorite $model;

    protected function setUp(): void
    {
        $this->pdo   = $this->createStub(PDO::class);
        $this->stmt  = $this->createStub(PDOStatement::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->model = new Favorite($this->pdo);
    }

    // ── add ──────────────────────────────────────────────────────

    public function testAddReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->add(1, 10);

        $this->assertTrue($result);
    }

    // ── remove ───────────────────────────────────────────────────

    public function testRemoveReturnsTrue(): void
    {
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->remove(1, 10);

        $this->assertTrue($result);
    }

    // ── exists ───────────────────────────────────────────────────

    public function testExistsReturnsTrueWhenFound(): void
    {
        $this->stmt->method('fetch')->willReturn(['user_id' => 1, 'cafe_id' => 10]);

        $result = $this->model->exists(1, 10);

        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $this->stmt->method('fetch')->willReturn(false);

        $result = $this->model->exists(1, 10);

        $this->assertFalse($result);
    }

    // ── toggle ───────────────────────────────────────────────────

    public function testToggleAddsWhenNotExistsAndReturnsTrue(): void
    {
        // exists → false (not favorited), then add → true
        $this->stmt->method('fetch')->willReturn(false);
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->toggle(1, 10);

        $this->assertTrue($result);
    }

    public function testToggleRemovesWhenExistsAndReturnsFalse(): void
    {
        // exists → row found (is favorited), then remove → true
        $this->stmt->method('fetch')->willReturn(['user_id' => 1, 'cafe_id' => 10]);
        $this->stmt->method('execute')->willReturn(true);

        $result = $this->model->toggle(1, 10);

        $this->assertFalse($result);
    }

    // ── getCafeIds ───────────────────────────────────────────────

    public function testGetCafeIdsReturnsArrayOfIds(): void
    {
        $rows = [['cafe_id' => 5], ['cafe_id' => 8]];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->getCafeIds(1);

        $this->assertIsArray($result);
        $this->assertContains(5, $result);
        $this->assertContains(8, $result);
    }

    public function testGetCafeIdsReturnsEmptyArrayWhenNoFavorites(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->model->getCafeIds(1);

        $this->assertSame([], $result);
    }

    // ── getByUser ────────────────────────────────────────────────

    public function testGetByUserReturnsArrayWithCafeDetails(): void
    {
        $rows = [
            ['id' => 5, 'name' => 'Neko Café', 'slug' => 'neko-cafe', 'category' => 'lounge', 'favorited_at' => '2024-01-01'],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->getByUser(1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('neko-cafe', $result[0]['slug']);
    }

    // ── countByUser ──────────────────────────────────────────────

    public function testCountByUserReturnsInt(): void
    {
        $this->stmt->method('fetchColumn')->willReturn('3');

        $result = $this->model->countByUser(1);

        $this->assertSame(3, $result);
    }

    public function testCountByUserReturnsZeroWhenNoFavorites(): void
    {
        $this->stmt->method('fetchColumn')->willReturn('0');

        $result = $this->model->countByUser(99);

        $this->assertSame(0, $result);
    }

    // ── getUsersByCafe ───────────────────────────────────────────

    public function testGetUsersByCafeReturnsArray(): void
    {
        $rows = [['id' => 1, 'name' => 'Ana', 'email' => 'ana@example.com', 'favorited_at' => '2024-01-01']];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->getUsersByCafe(5);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ── getMostPopular ───────────────────────────────────────────

    public function testGetMostPopularReturnsArray(): void
    {
        $rows = [
            ['id' => 5, 'name' => 'Neko Café', 'slug' => 'neko-cafe', 'favorites_count' => 42],
        ];
        $this->stmt->method('fetchAll')->willReturn($rows);

        $result = $this->model->getMostPopular(5);

        $this->assertIsArray($result);
        $this->assertSame(42, $result[0]['favorites_count']);
    }
}
