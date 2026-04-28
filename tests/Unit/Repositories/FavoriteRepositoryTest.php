<?php

/**
 * ¿Qué pruebas aquí? FavoriteRepository: add, remove, toggle, exists,
 *   getCafeIds, getByUser, countByUser, getUsersByCafe, getMostPopular.
 * ¿Qué me quieres demostrar? Que toggle retorna false si el favorito existe
 *   (llama a remove) y true si no existe (llama a add), que countByUser usa
 *   fetchColumn(), y que getMostPopular usa bindValue antes de execute().
 * ¿Qué va a fallar en este test si se cambia el código? Si toggle invierte su
 *   lógica de retorno, si countByUser deja de usar fetchColumn(), o si
 *   getMostPopular deja de llamar bindValue con el parámetro limit.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\FavoriteRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FavoriteRepository::class)]
final class FavoriteRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        mixed $fetchColumnReturn = false,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('bindValue')->willReturn(true);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // add
    // -------------------------------------------------------------------------

    public function testAddReturnsTrue(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertTrue($repo->add(1, 2));
    }

    // -------------------------------------------------------------------------
    // remove
    // -------------------------------------------------------------------------

    public function testRemoveReturnsTrue(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertTrue($repo->remove(1, 2));
    }

    // -------------------------------------------------------------------------
    // exists
    // -------------------------------------------------------------------------

    public function testExistsReturnsTrueWhenRowFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: ['user_id' => 1, 'cafe_id' => 2]);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertTrue($repo->existsForUser(1, 2));
    }

    public function testExistsReturnsFalseWhenNoRow(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertFalse($repo->existsForUser(1, 99));
    }

    // -------------------------------------------------------------------------
    // toggle
    // -------------------------------------------------------------------------

    public function testToggleReturnsFalseWhenFavoriteAlreadyExists(): void
    {
        // fetch() retorna array → exists = true → llama remove → devuelve false
        $stmt = $this->makeStmt(fetchReturn: ['user_id' => 1, 'cafe_id' => 2]);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertFalse($repo->toggle(1, 2));
    }

    public function testToggleReturnsTrueWhenFavoriteDoesNotExist(): void
    {
        // fetch() retorna false → exists = false → llama add → devuelve true
        $stmt = $this->makeStmt(fetchReturn: false, executeReturn: true);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertTrue($repo->toggle(1, 2));
    }

    // -------------------------------------------------------------------------
    // getCafeIds
    // -------------------------------------------------------------------------

    public function testGetCafeIdsReturnsArray(): void
    {
        $rows = [['cafe_id' => 1], ['cafe_id' => 3]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $ids = $repo->getCafeIds(1);
        $this->assertSame([1, 3], $ids);
    }

    public function testGetCafeIdsReturnsEmptyWhenNone(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->getCafeIds(1));
    }

    // -------------------------------------------------------------------------
    // getByUser
    // -------------------------------------------------------------------------

    public function testGetByUserReturnsRows(): void
    {
        $rows = [['cafe_id' => 1, 'name' => 'Komorebi Centro']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $result = $repo->getByUser(1);
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // countByUser (usa fetchColumn)
    // -------------------------------------------------------------------------

    public function testCountByUserReturnsInt(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: '5');
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertSame(5, $repo->countByUser(1));
    }

    public function testCountByUserReturnsZeroWhenNoFavorites(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: '0');
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $this->assertSame(0, $repo->countByUser(1));
    }

    // -------------------------------------------------------------------------
    // getUsersByCafe
    // -------------------------------------------------------------------------

    public function testGetUsersByCafeReturnsRows(): void
    {
        $rows = [['user_id' => 10, 'name' => 'Ana']];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $result = $repo->getUsersByCafe(1);
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // getMostPopular (usa bindValue + execute())
    // -------------------------------------------------------------------------

    public function testGetMostPopularReturnsRows(): void
    {
        $rows = [['cafe_id' => 1, 'name' => 'Komorebi Sur', 'fav_count' => 30]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $result = $repo->getMostPopular(5);
        $this->assertCount(1, $result);
        $this->assertSame(30, $result[0]['fav_count']);
    }

    public function testGetMostPopularDefaultLimitReturnsRows(): void
    {
        $rows = [['cafe_id' => 2, 'name' => 'Komorebi Norte', 'fav_count' => 15]];
        $stmt = $this->makeStmt(fetchAllReturn: $rows);
        $repo = new FavoriteRepository($this->makePdo($stmt));

        $result = $repo->getMostPopular();
        $this->assertCount(1, $result);
    }
}
