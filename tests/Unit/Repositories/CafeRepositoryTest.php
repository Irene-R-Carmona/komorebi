<?php

/**
 * ¿Qué prueba aquí? CafeRepository — acceso a datos de cafeterías.
 * ¿Qué me quieres demostrar? El repositorio delega en PDO/query() y devuelve los tipos
 *   correctos: CafeDTO, array, bool, int, con fallbacks y secuencias multi-consulta.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en la firma pública, en la
 *   lógica de fallback de getAdminStats, hasAvailableCapacity o findById.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Core\Pagination;
use App\Domain\DTO\CafeDTO;
use App\Domain\Mappers\CafeMapper;
use App\Repositories\CafeRepository;

final class CafeRepositoryTest extends RepositoryTestCase
{
    private function mapper(): CafeMapper
    {
        return new CafeMapper();
    }

    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsDtoWhenFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: RowFactory::cafeRow());
        $repo = new CafeRepository($this->mapper(), $pdo);

        $dto = $repo->findById(1);

        $this->assertInstanceOf(CafeDTO::class, $dto);
        $this->assertSame(1, $dto->id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: false);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertNull($repo->findById(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findBySlug
    // ─────────────────────────────────────────────────────────────

    public function testFindBySlugReturnsArrayWhenFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: RowFactory::cafeRow());
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->findBySlug('komorebi-madrid');

        $this->assertIsArray($result);
        $this->assertSame('komorebi-madrid', $result['slug']);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: false);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertNull($repo->findBySlug('no-cafe'));
    }

    // ─────────────────────────────────────────────────────────────
    // findActive (usa query())
    // ─────────────────────────────────────────────────────────────

    public function testFindActiveReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->findActive();

        $this->assertCount(1, $result);
    }

    public function testFindActiveReturnsEmptyArray(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame([], $repo->findActive());
    }

    // ─────────────────────────────────────────────────────────────
    // findAvailableForReservation (usa query())
    // ─────────────────────────────────────────────────────────────

    public function testFindAvailableForReservationReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findAvailableForReservation());
    }

    // ─────────────────────────────────────────────────────────────
    // findAvailableForReservationById
    // ─────────────────────────────────────────────────────────────

    public function testFindAvailableForReservationByIdIndexesByIdKey(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow(['id' => 5])]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->findAvailableForReservationById();

        $this->assertArrayHasKey(5, $result);
    }

    public function testFindAvailableForReservationByIdReturnsEmptyWhenNone(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame([], $repo->findAvailableForReservationById());
    }

    // ─────────────────────────────────────────────────────────────
    // existsAndActive
    // ─────────────────────────────────────────────────────────────

    public function testExistsAndActiveReturnsTrueWhenFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: ['id' => 1]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertTrue($repo->existsAndActive(1));
    }

    public function testExistsAndActiveReturnsFalseWhenNotFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: false);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertFalse($repo->existsAndActive(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findByCategory
    // ─────────────────────────────────────────────────────────────

    public function testFindByCategoryReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findByCategory('neko'));
    }

    public function testFindByCategoryReturnsEmptyArray(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame([], $repo->findByCategory('shiba'));
    }

    // ─────────────────────────────────────────────────────────────
    // findByAnimalType
    // ─────────────────────────────────────────────────────────────

    public function testFindByAnimalTypeReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findByAnimalType('cat'));
    }

    // ─────────────────────────────────────────────────────────────
    // updateRating
    // ─────────────────────────────────────────────────────────────

    public function testUpdateRatingReturnsTrue(): void
    {
        $pdo  = $this->makePdo(rowCount: 1);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertTrue($repo->updateRating(1));
    }

    // ─────────────────────────────────────────────────────────────
    // findFiltered
    // ─────────────────────────────────────────────────────────────

    public function testFindFilteredNoFiltersReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findFiltered());
    }

    public function testFindFilteredWithFiltersReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findFiltered(['category' => 'neko', 'is_active' => true]));
    }

    // ─────────────────────────────────────────────────────────────
    // hasAvailableCapacity (2 prepares: capacity + bookings)
    // ─────────────────────────────────────────────────────────────

    public function testHasAvailableCapacityReturnsTrueWhenUnderCapacity(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => ['capacity_max' => 20]],   // café capacity
            ['fetch' => ['booked' => '10']],        // current bookings
        ]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertTrue($repo->hasAvailableCapacity(1, '2025-06-20', '11:00:00'));
    }

    public function testHasAvailableCapacityReturnsFalseWhenFull(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => ['capacity_max' => 10]],
            ['fetch' => ['booked' => '10']],
        ]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertFalse($repo->hasAvailableCapacity(1, '2025-06-20', '11:00:00'));
    }

    public function testHasAvailableCapacityReturnsFalseWhenCafeNotFound(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => false],  // cafe not found
        ]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertFalse($repo->hasAvailableCapacity(99, '2025-06-20', '11:00:00'));
    }

    // ─────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────

    public function testUpdateReturnsTrueWithValidFields(): void
    {
        $pdo  = $this->makePdo(rowCount: 1);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertTrue($repo->update(1, ['name' => 'Nuevo Nombre', 'is_active' => 1]));
    }

    public function testUpdateReturnsTrueWithEmptyData(): void
    {
        $pdo  = $this->makePdo(rowCount: 0);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertTrue($repo->update(1, []));
    }

    public function testUpdateReturnsTrueWhenNoValidFields(): void
    {
        $pdo  = $this->makePdo(rowCount: 0);
        $repo = new CafeRepository($this->mapper(), $pdo);

        // Ningún campo del array es válido → early return true
        $this->assertTrue($repo->update(1, ['nonexistent_field' => 'value']));
    }

    // ─────────────────────────────────────────────────────────────
    // findAllFiltered
    // ─────────────────────────────────────────────────────────────

    public function testFindAllFilteredNoFiltersReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findAllFiltered());
    }

    public function testFindAllFilteredWithFiltersReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findAllFiltered('neko', 'cat', 'rating_avg', 'DESC'));
    }

    public function testFindAllFilteredSanitizesInvalidOrderBy(): void
    {
        // orderBy inválido → silenciosamente cae a 'name'; no lanza excepción
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->findAllFiltered(null, null, 'INJECTION; DROP TABLE cafes;--', 'ASC');

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // findWithAnimals (2 prepares: findBySlug + animales)
    // ─────────────────────────────────────────────────────────────

    public function testFindWithAnimalsReturnsCafeWithAnimalsKey(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch'    => RowFactory::cafeRow()],      // findBySlug
            ['fetchAll' => [['id' => 1, 'name' => 'Mochi']]],  // animales
        ]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->findWithAnimals('komorebi-madrid');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('animals', $result);
        $this->assertCount(1, $result['animals']);
    }

    public function testFindWithAnimalsReturnsNullWhenSlugNotFound(): void
    {
        $pdo  = $this->makePdo(fetchReturn: false);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertNull($repo->findWithAnimals('no-such-cafe'));
    }

    // ─────────────────────────────────────────────────────────────
    // getZones
    // ─────────────────────────────────────────────────────────────

    public function testGetZonesReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [['id' => 1, 'name' => 'Sala principal']]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->getZones(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getFavoritesCount
    // ─────────────────────────────────────────────────────────────

    public function testGetFavoritesCountReturnsInt(): void
    {
        $pdo  = $this->makePdo(fetchColumnReturn: '12');
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame(12, $repo->getFavoritesCount(1));
    }

    public function testGetFavoritesCountReturnsZero(): void
    {
        $pdo  = $this->makePdo(fetchColumnReturn: '0');
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame(0, $repo->getFavoritesCount(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findByIds
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdsReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->findByIds([1]));
    }

    public function testFindByIdsReturnsEmptyArrayWhenIdsEmpty(): void
    {
        $pdo  = $this->makePdo();
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame([], $repo->findByIds([]));
    }

    // ─────────────────────────────────────────────────────────────
    // search
    // ─────────────────────────────────────────────────────────────

    public function testSearchReturnsMatchingRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertCount(1, $repo->search('komorebi'));
    }

    public function testSearchReturnsEmptyWhenNoMatch(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $this->assertSame([], $repo->search('xyz123'));
    }

    // ─────────────────────────────────────────────────────────────
    // findPaginatedAdmin
    // ─────────────────────────────────────────────────────────────

    public function testFindPaginatedAdminReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);
        $pagination = Pagination::fromRequest(1, 20);

        $this->assertCount(1, $repo->findPaginatedAdmin($pagination));
    }

    public function testFindPaginatedAdminWithFiltersReturnsRows(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: [RowFactory::cafeRow()]);
        $repo = new CafeRepository($this->mapper(), $pdo);
        $pagination = Pagination::fromRequest(1, 20);

        $result = $repo->findPaginatedAdmin($pagination, 'madrid', 'neko', 'active');

        $this->assertCount(1, $result);
    }

    public function testFindPaginatedAdminSanitizesInvalidSort(): void
    {
        $pdo  = $this->makePdo(fetchAllReturn: []);
        $repo = new CafeRepository($this->mapper(), $pdo);
        $pagination = Pagination::fromRequest(1, 20);

        // sort inválido → silenciosamente cae a 'name'
        $result = $repo->findPaginatedAdmin($pagination, '', '', '', 'INJECTION; DROP TABLE --');

        $this->assertSame([], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getAdminStats (usa query() + fetch)
    // ─────────────────────────────────────────────────────────────

    public function testGetAdminStatsReturnsStatsRow(): void
    {
        $statsRow = [
            'total_cafes'              => '10',
            'active_cafes'             => '8',
            'cafes_with_reservations'  => '5',
            'avg_rating'               => '4.3',
        ];
        $pdo  = $this->makePdo(fetchReturn: $statsRow);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->getAdminStats();

        $this->assertSame('10', $result['total_cafes']);
        $this->assertSame('8', $result['active_cafes']);
    }

    public function testGetAdminStatsReturnsDefaultWhenQueryFails(): void
    {
        $pdo  = $this->makePdo(fetchReturn: false);
        $repo = new CafeRepository($this->mapper(), $pdo);

        $result = $repo->getAdminStats();

        $this->assertSame(0, $result['total_cafes']);
        $this->assertSame(0.0, $result['avg_rating']);
    }
}
