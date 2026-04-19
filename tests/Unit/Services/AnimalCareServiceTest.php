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

use App\Repositories\Contracts\AnimalRepositoryInterface;
use App\Services\AnimalCareService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AnimalCareService::class)]
final class AnimalCareServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&AnimalRepositoryInterface */
    private AnimalRepositoryInterface $repoStub;
    /** @var \PHPUnit\Framework\MockObject\Stub&PDO */
    private PDO $pdoStub;

    protected function setUp(): void
    {
        $this->repoStub = $this->createMock(AnimalRepositoryInterface::class);
        $this->pdoStub = $this->createMock(PDO::class);
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

        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->getAllAnimals();

        $this->assertSame($animalesEsperados, $result);
    }

    // ──────────────────────────────────────────────
    // getAnimalById — delegación al repositorio
    // ──────────────────────────────────────────────

    public function testGetAnimalByIdDevuelveNullCuandoNoExiste(): void
    {
        $this->repoStub->method('findById')->willReturn(null);

        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->getAnimalById(999);

        $this->assertNull($result);
    }

    public function testGetAnimalByIdDevuelveArrayCuandoExiste(): void
    {
        $animal = ['id' => 5, 'name' => 'Mochi', 'species_type' => 'rabbit'];
        $this->repoStub->method('findById')->willReturn($animal);

        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->getAnimalById(5);

        $this->assertSame($animal, $result);
    }

    // ──────────────────────────────────────────────
    // createAnimal — validaciones
    // ──────────────────────────────────────────────

    public function testCreateAnimalSinNombreRetornaFail(): void
    {
        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->createAnimal(['species' => 'cat']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Nombre', $result->error);
    }

    public function testCreateAnimalSinEspecieRetornaFail(): void
    {
        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->createAnimal(['name' => 'Neko']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('especie', $result->error);
    }

    public function testCreateAnimalConDatosVaciosRetornaFail(): void
    {
        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->createAnimal([]);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // updateAnimal — fallo PDO
    // ──────────────────────────────────────────────

    public function testUpdateAnimalCuandoNingunFilaAfectadaRetornaFail(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0); // ninguna fila afectada

        $this->pdoStub->method('prepare')->willReturn($stmt);

        $service = new AnimalCareService($this->pdoStub, $this->repoStub);
        $result = $service->updateAnimal(999, ['name' => 'Ghost', 'species' => 'cat']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }
}
