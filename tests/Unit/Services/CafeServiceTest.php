<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Services\CafeService;
use App\Repositories\Contracts\CafeRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Tests Unitarios de CafeService
 *
 * Valida lógica de negocio sin tocar BD real (usa mocks).
 */
#[AllowMockObjectsWithoutExpectations]
final class CafeServiceTest extends TestCase
{
    private CafeService $service;
    /** @var \PHPUnit\Framework\MockObject\MockObject&CafeRepositoryInterface */
    private CafeRepositoryInterface $mockRepo;

    protected function setUp(): void
    {
        // Evitar que un user_id de otro test (mismo worker paratest) contamine AuditLog
        if (isset($_SESSION)) {
            unset($_SESSION['user_id']);
        }
        $this->mockRepo = $this->createMock(CafeRepositoryInterface::class);
        $this->service = new CafeService($this->mockRepo);
    }

    protected function tearDown(): void
    {
        if (isset($_SESSION)) {
            unset($_SESSION['user_id']);
        }
        unset($this->service, $this->mockRepo);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getAll
    // ─────────────────────────────────────────────────────────────

    public function testGetAllWithActiveFilterUsesRepository(): void
    {
        // ARRANGE: Mock repository devuelve cafés activos
        $expectedCafes = [
            ['id' => 1, 'name' => 'Café Test 1', 'is_active' => 1],
            ['id' => 2, 'name' => 'Café Test 2', 'is_active' => 1],
        ];

        $this->mockRepo->expects($this->once())
            ->method('findActive')
            ->willReturn($expectedCafes);

        // ACT: Llamar con filtro is_active=1
        $result = $this->service->getAll(['is_active' => 1]);

        // ASSERT: Debe retornar cafés activos
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Café Test 1', $result[0]['name']);
    }

    public function testGetAllWithCategoryFilterUsesRepository(): void
    {
        // ARRANGE: Mock repository devuelve cafés de categoría
        $expectedCafes = [
            ['id' => 1, 'name' => 'Cat Lounge', 'category' => 'lounge'],
        ];

        $this->mockRepo->expects($this->once())
            ->method('findByCategory')
            ->with('lounge')
            ->willReturn($expectedCafes);

        // ACT: Llamar con filtro category=lounge
        $result = $this->service->getAll(['category' => 'lounge']);

        // ASSERT: Debe retornar cafés de esa categoría
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('lounge', $result[0]['category']);
    }

    public function testGetAllWithMultipleFiltersUsesGeneralMethod(): void
    {
        // ARRANGE: Mock repository con filtros complejos
        $expectedCafes = [
            ['id' => 1, 'name' => 'Cat Zen', 'category' => 'zen', 'animal_type' => 'gato'],
        ];

        $this->mockRepo->expects($this->once())
            ->method('findFiltered')
            ->willReturn($expectedCafes);

        // ACT: Llamar con filtros múltiples
        $result = $this->service->getAll(['category' => 'zen', 'animal_type' => 'gato']);

        // ASSERT: Debe usar findFiltered
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: getById
    // ─────────────────────────────────────────────────────────────

    public function testGetByIdReturnsDataWhenCafeExists(): void
    {
        // ARRANGE: Mock repository devuelve café
        $expectedCafe = ['id' => 1, 'name' => 'Test Café', 'slug' => 'test-cafe'];

        $this->mockRepo->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($expectedCafe);

        // ACT: Llamar getById
        $result = $this->service->getById(1);

        // ASSERT: Debe retornar el café
        $this->assertIsArray($result);
        $this->assertSame('Test Café', $result['name']);
    }

    public function testGetByIdReturnsNullWhenCafeNotFound(): void
    {
        // ARRANGE: Mock repository devuelve null
        $this->mockRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        // ACT: Llamar getById con ID inexistente
        $result = $this->service->getById(999);

        // ASSERT: Debe retornar null
        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: create
    // ─────────────────────────────────────────────────────────────

    public function testCreateWithValidDataSucceeds(): void
    {
        // ARRANGE: Mock repository devuelve ID
        $this->mockRepo->expects($this->once())
            ->method('create')
            ->willReturn(1);

        $validData = [
            'name' => 'Nuevo Café',
            'slug' => 'nuevo-cafe',
            'location' => 'Tokyo',
            'category' => 'lounge',
            'animal_type' => 'gato',
            'price_per_hour' => 1500,
            'capacity_max' => 30,
        ];

        // ACT: Llamar create
        $result = $this->service->create($validData);

        // ASSERT: Debe retornar Result ok con el ID
        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->data);
    }

    public function testCreateThrowsExceptionWhenMissingRequiredFields(): void
    {
        // ARRANGE: Datos incompletos (falta slug)
        $invalidData = [
            'name' => 'Nuevo Café',
            'location' => 'Tokyo',
        ];

        // ACT & ASSERT: Debe retornar Result fallido por campo faltante
        $result = $this->service->create($invalidData);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString("'slug' es obligatorio", $result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // Tests: update
    // ─────────────────────────────────────────────────────────────

    public function testUpdateSucceedsWhenCafeExists(): void
    {
        // ARRANGE: Mock repository indica que café existe
        $this->mockRepo->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn(['id' => 1, 'name' => 'Old Name']);

        $this->mockRepo->expects($this->once())
            ->method('update')
            ->with(1, ['name' => 'Nuevo Nombre'])
            ->willReturn(true);

        // ACT: Intentar actualizar
        $updateData = ['name' => 'Nuevo Nombre'];
        $result = $this->service->update(1, $updateData);

        // ASSERT: Debe retornar Result ok
        $this->assertTrue($result->ok);
    }

    public function testUpdateThrowsExceptionWhenCafeNotFound(): void
    {
        // ARRANGE: Mock repository indica que café no existe
        $this->mockRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        // ACT & ASSERT: Debe retornar Result fallido
        $result = $this->service->update(999, ['name' => 'Test']);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', strtolower($result->error));
    }
}
