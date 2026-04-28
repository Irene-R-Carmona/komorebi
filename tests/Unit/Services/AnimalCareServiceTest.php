<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? AnimalCareService: validaciones al crear un animal y delegación de consultas.
 * ¿Qué me quieres demostrar? Que createAnimal valida nombre, especie, cafe_id y rango de edad.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las validaciones de datos del animal.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\AnimalIncidentRepositoryInterface;
use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\AnimalCareService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnimalCareService::class)]
final class AnimalCareServiceTest extends TestCase
{
    private AnimalRepositoryInterface $animalRepoStub;
    private AnimalIncidentRepositoryInterface $incidentRepoStub;
    private HealthCheckRepositoryInterface $healthCheckRepoStub;
    private AnimalCareService $service;

    protected function setUp(): void
    {
        $this->animalRepoStub      = $this->createStub(AnimalRepositoryInterface::class);
        $this->incidentRepoStub    = $this->createStub(AnimalIncidentRepositoryInterface::class);
        $this->healthCheckRepoStub = $this->createStub(HealthCheckRepositoryInterface::class);

        $this->service = new AnimalCareService(
            $this->animalRepoStub,
            $this->incidentRepoStub,
            $this->healthCheckRepoStub
        );
    }

    public function testCreateAnimalFailsWhenNameMissing(): void
    {
        $result = $this->service->createAnimal(['species' => 'cat', 'cafe_id' => 1]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre', $result->error);
    }

    public function testCreateAnimalFailsWhenSpeciesMissing(): void
    {
        $result = $this->service->createAnimal(['name' => 'Mochi', 'cafe_id' => 1]);

        $this->assertFalse($result->ok);
    }

    public function testCreateAnimalFailsWhenCafeIdMissingAndNotQuarantine(): void
    {
        $result = $this->service->createAnimal(['name' => 'Mochi', 'species' => 'cat', 'status' => 'active']);

        $this->assertFalse($result->ok);
        $this->assertSame('cafe_id_required', $result->code);
    }

    public function testCreateAnimalSucceedsInQuarantineWithoutCafeId(): void
    {
        $this->animalRepoStub->method('createAnimal')->willReturn(5);

        $result = $this->service->createAnimal(['name' => 'Mochi', 'species' => 'cat', 'status' => 'quarantine']);

        $this->assertTrue($result->ok);
        $this->assertSame(5, $result->data);
    }

    public function testCreateAnimalFailsWhenAgeOutOfRange(): void
    {
        $result = $this->service->createAnimal([
            'name'      => 'Mochi',
            'species'   => 'cat',
            'cafe_id'   => 1,
            'age_years' => 99,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_age', $result->code);
    }

    public function testGetAllAnimalsReturnsArray(): void
    {
        $this->animalRepoStub->method('getAnimalsWithCafeInfoOptimized')->willReturn([]);

        $result = $this->service->getAllAnimals();

        $this->assertIsArray($result);
    }

    public function testGetAnimalByIdReturnsNullWhenNotFound(): void
    {
        $this->animalRepoStub->method('findById')->willReturn(null);

        $result = $this->service->getAnimalById(999);

        $this->assertNull($result);
    }
}
