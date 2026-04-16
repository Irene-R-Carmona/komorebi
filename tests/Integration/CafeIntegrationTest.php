<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests de Integración de CafeService
 *
 * Valida operaciones con MySQL 8.4 real usando transacciones para aislamiento.
 * Estos tests NO usan mocks - ejecutan queries reales contra la BD.
 */

namespace Tests\Integration;

use App\Repositories\CafeRepository;
use App\Services\CafeService;
use Tests\Support\BaseIntegrationTest;

final class CafeIntegrationTest extends BaseIntegrationTest
{
    private CafeService $service;

    // IDs únicos para tests
    private const TEST_CAFE_ID_BASE = 77700;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->service = new CafeService(new CafeRepository(self::$db));
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Limpiar datos previos
        self::$db->exec('DELETE FROM cafes WHERE id >= ' . self::TEST_CAFE_ID_BASE);

        // Café activo de tipo lounge
        self::$db->exec('
            INSERT INTO cafes (
                id, name, japanese_name, slug, location, category,
                animal_type, description, price_per_hour,
                opening_time, closing_time, capacity_max, is_active
            )
            VALUES (
                ' . self::TEST_CAFE_ID_BASE . ",
                'Test Cat Lounge',
                'テストキャットラウンジ',
                'test-cat-lounge',
                'Tokyo Test District',
                'lounge',
                'gato',
                'Café de prueba para tests de integración',
                1500,
                '10:00:00',
                '20:00:00',
                30,
                1
            )
        ");

        // Café inactivo de tipo zen
        self::$db->exec('
            INSERT INTO cafes (
                id, name, slug, location, category,
                animal_type, description, price_per_hour,
                opening_time, closing_time, capacity_max, is_active
            )
            VALUES (
                ' . (self::TEST_CAFE_ID_BASE + 1) . ",
                'Test Zen Garden',
                'test-zen-garden',
                'Kyoto Test',
                'zen',
                'tortuga',
                'Café zen inactivo para tests',
                2000,
                '09:00:00',
                '18:00:00',
                20,
                0
            )
        ");

        // Café activo de tipo playroom
        self::$db->exec('
            INSERT INTO cafes (
                id, name, slug, location, category,
                animal_type, description, price_per_hour,
                opening_time, closing_time, capacity_max, is_active
            )
            VALUES (
                ' . (self::TEST_CAFE_ID_BASE + 2) . ",
                'Test Dog Playroom',
                'test-dog-playroom',
                'Osaka Test',
                'playroom',
                'perro',
                'Playroom para perros pequeños',
                1800,
                '11:00:00',
                '21:00:00',
                25,
                1
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Integration Tests
    // ─────────────────────────────────────────────────────────────

    public function testGetAllReturnsActiveCafesByDefault(): void
    {
        // ACT: Obtener solo activos
        $result = $this->service->getAll(['is_active' => 1]);

        // ASSERT: Debe incluir nuestros 2 cafés activos (más los de seed)
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, \count($result));

        // Verificar que todos son activos
        foreach ($result as $cafe) {
            $this->assertEquals(1, $cafe['is_active']);
        }
    }

    public function testSearchFindsCafesByName(): void
    {
        // ACT: Buscar por "Test Cat"
        $result = $this->service->search('Test Cat');

        // ASSERT: Debe encontrar el café "Test Cat Lounge"
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, \count($result));

        $found = false;
        foreach ($result as $cafe) {
            if (\stripos($cafe['name'], 'Test Cat') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Debe encontrar "Test Cat Lounge" en la búsqueda');
    }

    public function testGetByIdReturnsCorrectCafe(): void
    {
        // ACT: Obtener café por ID
        $result = $this->service->getById(self::TEST_CAFE_ID_BASE);

        // ASSERT: Debe retornar el café correcto
        $this->assertIsArray($result);
        $this->assertSame(self::TEST_CAFE_ID_BASE, $result['id']);
        $this->assertSame('Test Cat Lounge', $result['name']);
        $this->assertSame('lounge', $result['category']);
        $this->assertSame('gato', $result['animal_type']);
    }

    public function testGetAllWithFiltersReturnsFilteredResults(): void
    {
        // ACT: Filtrar por categoría 'playroom'
        $result = $this->service->getAll(['category' => 'playroom', 'is_active' => 1]);

        // ASSERT: Debe incluir solo cafés activos de tipo playroom
        $this->assertIsArray($result);

        foreach ($result as $cafe) {
            $this->assertSame('playroom', $cafe['category']);
            $this->assertEquals(1, $cafe['is_active']);
        }
    }
}
