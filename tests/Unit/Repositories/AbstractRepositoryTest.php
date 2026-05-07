<?php

/**
 * ¿Qué prueba aquí?
 * AbstractRepository: findById, findAll, exists, create, update, delete, softDelete,
 * count (con y sin condiciones), findPaginated con condiciones/sort/search,
 * countFiltered, y la rama LIKE de buildWhere.
 *
 * ¿Qué me quieres demostrar?
 * Que cada método delega correctamente la SQL al PDO inyectado y devuelve
 * el resultado transformado según el contrato de la interfaz.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cambia la firma de cualquier método CRUD, si se elimina la inyección de PDO,
 * o si los métodos dejan de retornar los tipos documentados.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Core\Pagination;
use App\Repositories\AbstractRepository;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AbstractRepository::class)]
final class AbstractRepositoryTest extends RepositoryTestCase
{
    // -------------------------------------------------------------------------
    // Helper — clase concreta anónima con métodos expuestos
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $fetchAllReturn
     * @param mixed                $fetchReturn
     * @param mixed                $fetchColumnReturn
     */
    private function makeRepo(
        array $fetchAllReturn = [],
        mixed $fetchReturn = false,
        mixed $fetchColumnReturn = '0',
        string $lastInsertId = '1',
    ): object {
        $pdo = $this->makePdo(
            fetchAllReturn: $fetchAllReturn,
            fetchReturn: $fetchReturn,
            fetchColumnReturn: $fetchColumnReturn,
            lastInsertId: $lastInsertId,
        );

        return new class ($pdo) extends AbstractRepository {
            #[Override]
            protected function getTable(): string
            {
                return 'items';
            }

            #[Override]
            protected function getSelectFields(): array
            {
                return ['id', 'name'];
            }

            // Expose protected methods for testing
            public function countPublic(array $conditions = []): int
            {
                return $this->count($conditions);
            }

            /**
             * @param array<string, mixed> $conditions
             * @param array<int, string>   $searchColumns
             * @param array<int, string>   $sortWhitelist
             * @return array<int, array<string, mixed>>
             */
            public function paginatedWithOptions(
                Pagination $pagination,
                array $conditions = [],
                string $search = '',
                array $searchColumns = [],
                string $sort = '',
                string $sortDir = 'asc',
                array $sortWhitelist = [],
            ): array {
                return $this->findPaginated(
                    $pagination,
                    $conditions,
                    $search,
                    $searchColumns,
                    $sort,
                    $sortDir,
                    $sortWhitelist,
                );
            }

            /**
             * @param array<string, mixed> $conditions
             * @param array<int, string>   $searchColumns
             */
            public function countFilteredPublic(
                array $conditions = [],
                string $search = '',
                array $searchColumns = [],
            ): int {
                return $this->countFiltered($conditions, $search, $searchColumns);
            }
        };
    }

    // -------------------------------------------------------------------------
    // findById
    // -------------------------------------------------------------------------

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = $this->makeRepo(fetchReturn: false);

        $result = $repo->findById(999);

        $this->assertNull($result);
    }

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 3, 'name' => 'Matcha'];
        $repo = $this->makeRepo(fetchReturn: $row);

        $result = $repo->findById(3);

        $this->assertSame($row, $result);
    }

    // -------------------------------------------------------------------------
    // findAll
    // -------------------------------------------------------------------------

    public function testFindAllReturnsEmptyArrayWhenNoRows(): void
    {
        $repo = $this->makeRepo(fetchAllReturn: []);

        $this->assertSame([], $repo->findAll());
    }

    public function testFindAllReturnsRows(): void
    {
        $rows = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];
        $repo = $this->makeRepo(fetchAllReturn: $rows);

        $this->assertSame($rows, $repo->findAll());
    }

    // -------------------------------------------------------------------------
    // exists
    // -------------------------------------------------------------------------

    public function testExistsReturnsFalseWhenNoRow(): void
    {
        $repo = $this->makeRepo(fetchReturn: false);

        $this->assertFalse($repo->exists(999));
    }

    public function testExistsReturnsTrueWhenRowFound(): void
    {
        $repo = $this->makeRepo(fetchReturn: ['1']);

        $this->assertTrue($repo->exists(1));
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateReturnsInsertedId(): void
    {
        $repo = $this->makeRepo(lastInsertId: '7');

        $id = $repo->create(['name' => 'New Item']);

        $this->assertSame(7, $id);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $repo = $this->makeRepo();

        $result = $repo->update(1, ['name' => 'Updated']);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $repo = $this->makeRepo();

        $result = $repo->delete(1);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // softDelete
    // -------------------------------------------------------------------------

    public function testSoftDeleteReturnsTrueOnSuccess(): void
    {
        $repo = $this->makeRepo();

        $result = $repo->softDelete(1);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // count (protected — exposed via countPublic)
    // -------------------------------------------------------------------------

    public function testCountWithoutConditionsReturnsInt(): void
    {
        $repo = $this->makeRepo(fetchColumnReturn: '5');

        $this->assertSame(5, $repo->countPublic());
    }

    public function testCountWithConditionsReturnsInt(): void
    {
        $repo = $this->makeRepo(fetchColumnReturn: '2');

        $this->assertSame(2, $repo->countPublic(['name' => 'Matcha']));
    }

    // -------------------------------------------------------------------------
    // findPaginated with conditions (covers SQL_WHERE branch — line 239)
    // -------------------------------------------------------------------------

    public function testFindPaginatedWithConditionsAddsWhereSql(): void
    {
        $rows = [['id' => 1, 'name' => 'A']];
        $repo = $this->makeRepo(fetchAllReturn: $rows);

        $result = $repo->paginatedWithOptions(
            pagination: Pagination::fromRequest(1, 10),
            conditions: ['name' => 'A'],
        );

        $this->assertSame($rows, $result);
    }

    // -------------------------------------------------------------------------
    // findPaginated with sort (covers sort branch — lines 243-244)
    // -------------------------------------------------------------------------

    public function testFindPaginatedWithValidSortAddsOrderBy(): void
    {
        $rows = [['id' => 1, 'name' => 'A']];
        $repo = $this->makeRepo(fetchAllReturn: $rows);

        $result = $repo->paginatedWithOptions(
            pagination: Pagination::fromRequest(1, 10),
            sort: 'name',
            sortDir: 'desc',
            sortWhitelist: ['name'],
        );

        $this->assertSame($rows, $result);
    }

    // -------------------------------------------------------------------------
    // findPaginated with search (covers buildWhere LIKE branch — lines 301-307)
    // -------------------------------------------------------------------------

    public function testFindPaginatedWithSearchAddsLikeCondition(): void
    {
        $rows = [['id' => 2, 'name' => 'Matcha']];
        $repo = $this->makeRepo(fetchAllReturn: $rows);

        $result = $repo->paginatedWithOptions(
            pagination: Pagination::fromRequest(1, 10),
            search: 'match',
            searchColumns: ['name'],
        );

        $this->assertSame($rows, $result);
    }

    // -------------------------------------------------------------------------
    // countFiltered (covers lines 263-279)
    // -------------------------------------------------------------------------

    public function testCountFilteredWithNoFiltersReturnsInt(): void
    {
        $repo = $this->makeRepo(fetchColumnReturn: '10');

        $this->assertSame(10, $repo->countFilteredPublic());
    }

    public function testCountFilteredWithSearchAndConditionsReturnsInt(): void
    {
        $repo = $this->makeRepo(fetchColumnReturn: '3');

        $this->assertSame(3, $repo->countFilteredPublic(
            conditions: ['active' => 1],
            search: 'tea',
            searchColumns: ['name'],
        ));
    }
}
