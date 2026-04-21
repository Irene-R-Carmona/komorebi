<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AnimalCareService: createAnimal con datos inválidos (falta nombre/especie),
 * getAllAnimals y getAnimalById delegando al repositorio stubbeado.
 *
 * ¿Qué me quieres demostrar?
 * Que la validación de datos obligatorios funciona antes de llegar a la DB, y
 * que las consultas de lectura delegan correctamente al repositorio inyectado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guarda 'empty($data[name]) || empty($data[species])', si
 * getAllAnimals deja de llamar al repositorio, o si getAnimalById deja de retornar null.
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
    /** @var \PHPUnit\Framework\MockObject\Stub&AnimalRepositoryInterface */
    private AnimalRepositoryInterface $repoStub;
    private AnimalIncidentRepositoryInterface $incidentRepoStub;
    private HealthCheckRepositoryInterface $healthCheckRepoStub;

    protected function setUp(): void
    {
        $this->repoStub           = $this->createStub(AnimalRepositoryInterface::class);
        $this->incidentRepoStub   = $this->createStub(AnimalIncidentRepositoryInterface::class);
        $this->healthCheckRepoStub = $this->createStub(HealthCheckRepositoryInterface::class);
    }

    // ──────────────────────────────────────────────
    // getAllAnimals — delegación al repositorio
    // ──────────────────────────────────────────────

    public function testGetAllAnimalsDevuelveArrayDelRepositorio(): void
    {
        $animalesEsperados = [
            ['id' => 1, 'name' => 'Neko', 'species_type' => 'cat'],
            ['id' => 2, 'name' => 'Hachi', 'species_type' => 'dog'],
        ];
        $this->repoStub
            ->method('getAnimalsWithCafeInfoOptimized')
            ->willReturn($animalesEsperados);

        $service = new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub);
        $result  = $service->getAllAnimals();

        $this->assertSame($animalesEsperados, $result);
    }

    // ──────────────────────────────────────────────
    // getAnimalById — delegación al repositorio
    // ──────────────────────────────────────────────

    public function testGetAnimalByIdDevuelveNullCuandoNoExiste(): void
    {
        $this->repoStub->method('findById')->willReturn(null);

        $this->assertNull((new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub))->getAnimalById(999));
    }

    public function testGetAnimalByIdDevuelveArrayCuandoExiste(): void
    {
        $animal = ['id' => 5, 'name' => 'Mochi', 'species_type' => 'rabbit'];
        $this->repoStub->method('findById')->willReturn($animal);

        $this->assertSame($animal, (new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub))->getAnimalById(5));
    }

    // ──────────────────────────────────────────────
    // createAnimal — validaciones
    // ──────────────────────────────────────────────

    public function testCreateAnimalSinNombreRetornaFail(): void
    {
        $result = (new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub))->createAnimal(['species' => 'cat']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre', $result->error);
    }

    public function testCreateAnimalSinEspecieRetornaFail(): void
    {
        $result = (new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub))->createAnimal(['name' => 'Neko']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('especie', $result->error);
    }

    public function testCreateAnimalConDatosVaciosRetornaFail(): void
    {
        $result = (new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub))->createAnimal([]);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // updateAnimal — fallo de repositorio
    // ──────────────────────────────────────────────

    public function testUpdateAnimalCuandoNingunFilaAfectadaRetornaFail(): void
    {
        $this->repoStub->method('updateAnimal')->willReturn(false);

        $service = new AnimalCareService($this->repoStub, $this->incidentRepoStub, $this->healthCheckRepoStub);
        $result  = $service->updateAnimal(999, ['name' => 'Ghost', 'species' => 'cat']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }
}
