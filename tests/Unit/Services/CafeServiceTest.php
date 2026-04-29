<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? CafeService: validaciones de creación, búsqueda y delegación al repositorio.
 * ¿Qué me quieres demostrar? Que los campos requeridos son validados y que las operaciones CRUD delegan correctamente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las validaciones de campos requeridos o cambia la lógica de not_found.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\CafeDTO;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\CafeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CafeService::class)]
final class CafeServiceTest extends TestCase
{
    private CafeRepositoryInterface $cafeRepoStub;
    private CafeService $service;

    protected function setUp(): void
    {
        $this->cafeRepoStub = $this->createStub(CafeRepositoryInterface::class);
        $statsRepoStub      = $this->createStub(StatisticsRepositoryInterface::class);
        $this->service      = new CafeService($this->cafeRepoStub, $statsRepoStub);
    }

    public function testGetAllWithActiveFilterCallsFindActive(): void
    {
        $this->cafeRepoStub->method('findActive')->willReturn([['id' => 1, 'name' => 'Café Tokio']]);

        $result = $this->service->getAll(['is_active' => 1]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetAllWithCategoryFilterCallsFindByCategory(): void
    {
        $this->cafeRepoStub->method('findByCategory')->willReturn([['id' => 2, 'name' => 'Café Neko']]);

        $result = $this->service->getAll(['category' => 'neko']);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetByIdDelegatesToRepository(): void
    {
        $dto = new CafeDTO(1, 'cafe-shiba', 'Café Shiba', null, null, 'Madrid', 'neko', 'cat', 5.0, 20, 4.5, '09:00', '21:00', 'Europe/Madrid', true, true, null);
        $this->cafeRepoStub->method('findById')->willReturn($dto);

        $result = $this->service->getById(1);

        $this->assertSame('Café Shiba', $result['name']);
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $this->assertNull($this->service->getById(99));
    }

    public function testCreateFailsWhenNameIsMissing(): void
    {
        $result = $this->service->create(['slug' => 'test', 'location' => 'Madrid']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('name', $result->error);
    }

    public function testCreateFailsWhenSlugIsMissing(): void
    {
        $result = $this->service->create(['name' => 'Test Café', 'location' => 'Madrid']);

        $this->assertFalse($result->ok);
    }

    public function testCreateFailsWhenLocationIsMissing(): void
    {
        $result = $this->service->create(['name' => 'Test Café', 'slug' => 'test-cafe']);

        $this->assertFalse($result->ok);
    }

    public function testUpdateReturnsFailWhenCafeNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->update(99, ['name' => 'Nuevo nombre']);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testToggleActiveReturnsFailWhenCafeNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->toggleActive(99);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testDeleteReturnsFailWhenCafeNotFound(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->delete(99);

        $this->assertFalse($result->ok);
        $this->assertSame('not_found', $result->code);
    }

    public function testSearchDelegatesToRepository(): void
    {
        $this->cafeRepoStub->method('search')->willReturn([['id' => 1, 'name' => 'Café Sakura']]);

        $results = $this->service->search('Sakura');

        $this->assertCount(1, $results);
    }

    public function testGetAllWithNoFiltersCallsFindFiltered(): void
    {
        $this->cafeRepoStub->method('findFiltered')->willReturn([['id' => 3, 'name' => 'Café Inu']]);

        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetAllWithBothCategoryAndActiveFilterCallsFindFiltered(): void
    {
        $this->cafeRepoStub->method('findFiltered')->willReturn([['id' => 4, 'name' => 'Café Mix']]);

        $result = $this->service->getAll(['category' => 'neko', 'is_active' => 1]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testUpdateReturnsOkTrueWhenNoFieldsToUpdate(): void
    {
        $dto = new CafeDTO(1, 'cafe-shiba', 'Café Shiba', null, null, 'Madrid', 'neko', 'cat', 5.0, 20, 4.5, '09:00', '21:00', 'Europe/Madrid', true, true, null);
        $this->cafeRepoStub->method('findById')->willReturn($dto);

        $result = $this->service->update(1, ['unknown_field_that_does_not_exist' => 'val']);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data);
    }

    public function testGetStatsDelegatesToStatsRepository(): void
    {
        $statsRepoStub = $this->createStub(\App\Repositories\Contracts\StatisticsRepositoryInterface::class);
        $statsRepoStub->method('getCafeStats')->willReturn(['total' => 5, 'active' => 3]);
        $service = new CafeService($this->cafeRepoStub, $statsRepoStub);

        $stats = $service->getStats();

        $this->assertSame(5, $stats['total']);
    }

    public function testCreateReturnsFailOnPDOException(): void
    {
        $this->cafeRepoStub->method('create')->willThrowException(new \PDOException('DB error'));

        $result = $this->service->create([
            'name' => 'Café Neko',
            'slug' => 'cafe-neko',
            'location' => 'Madrid',
        ]);

        $this->assertFalse($result->ok);
    }

    public function testUpdateCoversFieldProcessingAndPDOException(): void
    {
        $dto = new CafeDTO(1, 'cafe-shiba', 'Café Shiba', null, null, 'Madrid', 'neko', 'cat', 5.0, 20, 4.5, '09:00', '21:00', 'Europe/Madrid', true, true, null);
        $this->cafeRepoStub->method('findById')->willReturn($dto);
        $this->cafeRepoStub->method('update')->willThrowException(new \PDOException('DB error'));

        $result = $this->service->update(1, ['price_per_hour' => 100, 'name' => 'New Name']);

        $this->assertFalse($result->ok);
    }

    public function testToggleActiveCoversNotFoundPath(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->toggleActive(99);

        $this->assertFalse($result->ok);
    }

    public function testDeleteCoversNotFoundPath(): void
    {
        $this->cafeRepoStub->method('findById')->willReturn(null);

        $result = $this->service->delete(99);

        $this->assertFalse($result->ok);
    }
}
