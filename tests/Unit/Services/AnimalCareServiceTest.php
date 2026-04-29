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

    public function testUpdateAnimalFailsWhenAgeOutOfRange(): void
    {
        $result = $this->service->updateAnimal(1, ['age_years' => 99]);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_age', $result->code);
    }

    public function testUpdateAnimalFailsWhenNotFound(): void
    {
        $this->animalRepoStub->method('updateAnimal')->willReturn(false);

        $result = $this->service->updateAnimal(999, ['name' => 'Ghost']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testUpdateAnimalSucceeds(): void
    {
        $this->animalRepoStub->method('updateAnimal')->willReturn(true);

        $result = $this->service->updateAnimal(1, ['name' => 'Mochi', 'age_years' => 3]);

        $this->assertTrue($result->ok);
    }

    public function testDeleteAnimalFailsWhenNotFound(): void
    {
        $this->animalRepoStub->method('softDeleteAnimal')->willReturn(false);

        $result = $this->service->deleteAnimal(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testDeleteAnimalSucceeds(): void
    {
        $this->animalRepoStub->method('softDeleteAnimal')->willReturn(true);

        $result = $this->service->deleteAnimal(1);

        $this->assertTrue($result->ok);
    }

    public function testGetDashboardDataReturnsExpectedKeys(): void
    {
        $this->animalRepoStub->method('getAnimalsWithCafeInfoOptimized')->willReturn([]);
        $this->animalRepoStub->method('getHealthStatistics')->willReturn([]);
        $this->healthCheckRepoStub->method('getRecentLogs')->willReturn([]);
        $this->incidentRepoStub->method('getActiveIncidents')->willReturn([]);

        $result = $this->service->getDashboardData();

        $this->assertArrayHasKey('animals', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('recent_logs', $result);
        $this->assertArrayHasKey('active_incidents', $result);
    }

    public function testGetStatisticsDelegatesToRepo(): void
    {
        $this->animalRepoStub->method('getHealthStatistics')->willReturn(['total' => 10]);

        $result = $this->service->getStatistics();

        $this->assertSame(['total' => 10], $result);
    }

    public function testGetRecentLogsDelegatesToRepo(): void
    {
        $this->healthCheckRepoStub->method('getRecentLogs')->willReturn([['id' => 1]]);

        $result = $this->service->getRecentLogs(5);

        $this->assertCount(1, $result);
    }

    public function testGetActiveIncidentsDelegatesToRepo(): void
    {
        $this->incidentRepoStub->method('getActiveIncidents')->willReturn([['id' => 1], ['id' => 2]]);

        $result = $this->service->getActiveIncidents();

        $this->assertCount(2, $result);
    }

    public function testGetIncidentByIdReturnsNullWhenNotFound(): void
    {
        $this->incidentRepoStub->method('findById')->willReturn(null);

        $result = $this->service->getIncidentById(999);

        $this->assertNull($result);
    }

    public function testToggleActiveFailsWhenNotFound(): void
    {
        $this->animalRepoStub->method('toggleStatus')->willReturn(['found' => false]);

        $result = $this->service->toggleActive(999);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testToggleActiveSucceedsWithActivatedStatus(): void
    {
        $this->animalRepoStub->method('toggleStatus')->willReturn([
            'found' => true,
            'current_status' => 'active',
        ]);

        $result = $this->service->toggleActive(1);

        $this->assertTrue($result->ok);
        $this->assertSame('active', $result->data['current_status']);
        $this->assertStringContainsString('activado', $result->data['message']);
    }

    public function testToggleActiveSucceedsWithRestingStatus(): void
    {
        $this->animalRepoStub->method('toggleStatus')->willReturn([
            'found' => true,
            'current_status' => 'resting',
        ]);

        $result = $this->service->toggleActive(1);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('descanso', $result->data['message']);
    }

    public function testCreateCareLogFailsWhenAnimalIdInvalid(): void
    {
        $result = $this->service->createCareLog(['animal_id' => 0, 'activity_type' => 'feeding']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('animal', $result->error);
    }

    public function testCreateCareLogFailsWhenActivityTypeInvalid(): void
    {
        $result = $this->service->createCareLog(['animal_id' => 1, 'activity_type' => 'invalid_activity']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('actividad', $result->error);
    }

    public function testCreateCareLogSucceeds(): void
    {
        $this->healthCheckRepoStub->method('createCareLog')->willReturn(7);

        $result = $this->service->createCareLog([
            'animal_id' => 1,
            'activity_type' => 'feeding',
            'logged_by_user_id' => 1,
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame(7, $result->data);
    }

    public function testUpdateHealthFailsWhenStatusInvalid(): void
    {
        $result = $this->service->updateHealth(1, 'unknown_status');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválido', $result->error);
    }

    public function testResolveIncidentSucceeds(): void
    {
        $this->incidentRepoStub->method('resolve')->willReturn(true);

        $result = $this->service->resolveIncident(1, 'Resolved', 1);

        $this->assertTrue($result->ok);
    }

    public function testUpdateIncidentFailsWhenNotFound(): void
    {
        $this->incidentRepoStub->method('findById')->willReturn(null);

        $result = $this->service->updateIncident(999, ['severity' => 'low']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }
}
