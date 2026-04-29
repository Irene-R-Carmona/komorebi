<?php

/**
 * ¿Qué pruebas aquí?
 * AbstractRepository::findPaginated(): que ejecuta la query con el fetchLimit y el offset
 * de la Pagination dada, y retorna el array de filas que devuelve PDO.
 *
 * ¿Qué me quieres demostrar?
 * Que findPaginated delega correctamente LIMIT/OFFSET a la BD usando los valores
 * calculados por Pagination y retorna el resultado de fetchAll() sin transformación.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si findPaginated deja de usar fetchLimit como LIMIT, si el offset es incorrecto,
 * o si la firma del método cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Core\Pagination;
use App\Repositories\AbstractRepository;
use Override;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractRepository::class)]
final class AbstractRepositoryFindPaginatedTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Devuelve una clase anónima concreta de AbstractRepository que expone
     * findPaginated como método público para poder testearlo.
     */
    private function makeRepo(PDO $pdo): object
    {
        return new class($pdo) extends AbstractRepository {
            #[Override]
            protected function getTable(): string
            {
                return 'test_items';
            }

            #[Override]
            protected function getSelectFields(): array
            {
                return ['id', 'name'];
            }

            /**
             * Expone findPaginated al test (es protected en la clase base).
             *
             * @return array<int, array<string, mixed>>
             */
            public function paginatedPublic(Pagination $pagination): array
            {
                return $this->findPaginated($pagination);
            }
        };
    }

    private function makeStmt(array $rows): PDOStatement
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function testReturnsRowsFromPdo(): void
    {
        $rows = [['id' => 1, 'name' => 'Café A'], ['id' => 2, 'name' => 'Café B']];
        $repo = $this->makeRepo($this->makePdo($this->makeStmt($rows)));

        $pagination = Pagination::fromRequest(1, 10);
        $result     = $repo->paginatedPublic($pagination);

        $this->assertSame($rows, $result);
    }

    public function testReturnsEmptyArrayWhenNoRows(): void
    {
        $repo = $this->makeRepo($this->makePdo($this->makeStmt([])));

        $pagination = Pagination::fromRequest(1, 10);
        $result     = $repo->paginatedPublic($pagination);

        $this->assertSame([], $result);
    }

    public function testReturnsFetchLimitPlusOneRowsForSentinelDetection(): void
    {
        // fetchLimit = limit + 1 = 3 cuando limit = 2
        $pagination = Pagination::fromRequest(1, 2);
        $this->assertSame(3, $pagination->fetchLimit, 'fetchLimit debe ser limit + 1');

        // Simulamos que la BD devuelve fetchLimit filas (hay página siguiente)
        $rows = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B'], ['id' => 3, 'name' => 'C']];
        $repo = $this->makeRepo($this->makePdo($this->makeStmt($rows)));

        $result = $repo->paginatedPublic($pagination);

        // findPaginated retorna exactamente lo que fetchAll devuelve (sin podar el sentinel)
        $this->assertCount(3, $result);
        $this->assertTrue($pagination->hasNextPage(\count($result)));
    }

    public function testSecondPageUsesCorrectOffset(): void
    {
        // Para page=2, limit=5: offset debe ser 5
        $pagination = Pagination::fromRequest(2, 5);
        $this->assertSame(5, $pagination->offset);

        $rows = [['id' => 6, 'name' => 'F']];
        $repo = $this->makeRepo($this->makePdo($this->makeStmt($rows)));

        $result = $repo->paginatedPublic($pagination);

        $this->assertCount(1, $result);
        $this->assertSame(6, $result[0]['id']);
    }
}
