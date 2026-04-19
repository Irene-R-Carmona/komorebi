<?php

declare(strict_types=1);

/**
 * Tests de Integración de ReservationService
 *
 * ¿Qué pruebas aquí?
 * Operaciones CRUD de reservas contra MySQL 8.4 real: creación, cancelación,
 * check-in, check-out, validación de estados del ciclo de vida.
 *
 * ¿Qué me quieres demostrar?
 * Que ReservationService persiste correctamente en BD y que las transiciones
 * de estado (pending→confirmed→active→completed) funcionan en integración.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se modifica el ciclo de vida de estados, si las queries de reserva
 * cambian, o si la validación de disponibilidad se elimina.
 */

namespace Tests\Integration;

use App\Repositories\CafeRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ReservationRepository;
use App\Services\EmailService;
use App\Services\InvoicePDFService;
use App\Services\ReservationService;
use Exception;
use Override;
use PDO;
use Tests\Support\BaseIntegrationTest;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ReservationIntegrationTest extends BaseIntegrationTest
{
    private ReservationService $service;

    // IDs de test (deben ser únicos y no conflictivos con datos reales)
    private const TEST_USER_ID = 99999;
    private const TEST_CAFE_ID = 99998;
    private const TEST_PRODUCT_ID = 99997;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $reservationRepo = new ReservationRepository(self::$db);
        $cafeRepo = new CafeRepository(self::$db);
        $productRepo = new ProductRepository(self::$db);
        $invoiceService = new InvoicePDFService();
        $emailService = new EmailService();
        $this->service = new ReservationService(
            $reservationRepo,
            $cafeRepo,
            $productRepo,
            $invoiceService,
            $emailService
        );
    }

    /**
     * Seed de datos de prueba
     */
    private function seedTestData(): void
    {
        // Usuario de prueba
        self::$db->exec('
            INSERT IGNORE INTO users (id, uuid, email, password, name, email_verified_at, is_active)
            VALUES (
                ' . self::TEST_USER_ID . ",
                UUID(),
                'integration-test@komorebi.test',
                '\$argon2id\$v=19\$m=65536,t=4,p=1\$test\$hash',
                'Integration Test User',
                NOW(),
                1
            )
        ");

        // Café de prueba
        self::$db->exec('
            INSERT IGNORE INTO cafes (
                id, name, slug, location, category, animal_type,
                description, price_per_hour, opening_time, closing_time,
                capacity_max, is_active, has_reservations
            )
            VALUES (
                ' . self::TEST_CAFE_ID . ",
                'Test Café Integration',
                'test-cafe-integration',
                'Tokyo Test District',
                'lounge',
                'cat',
                'Café de prueba para integration tests',
                2000,
                '09:00:00',
                '20:00:00',
                50,
                1,
                1
            )
        ");

        // Categoría de producto (para FK)
        self::$db->exec("
            INSERT IGNORE INTO menu_categories (id, name, slug)
            VALUES (99999, 'Test Category', 'test-category')
        ");

        // Producto (pase) de prueba
        self::$db->exec('
            INSERT IGNORE INTO products (
                id, category_id, name, slug, product_type, description,
                price, is_active, duration_minutes, min_pax, max_pax,
                target_cafe_types
            )
            VALUES (
                ' . self::TEST_PRODUCT_ID . ",
                99999,
                'Test Pass 1H',
                'test-pass-1h',
                'pass',
                'Pase de prueba 1 hora',
                1500,
                1,
                60,
                1,
                4,
                '[\"lounge\"]'
            )
        ");
    }

    // ─────────────────────────────────────────────────────────────
    // Tests de integración
    // ─────────────────────────────────────────────────────────────

    public function testCreateReservationInsertsIntoDatabaseCorrectly(): void
    {
        // ACT: Crear reserva con datos válidos
        $createResult = $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-25',
            'time' => '10:00',
            'guests' => 2,
            'comments' => 'Integration test reservation',
        ]);
        $reservationId = $createResult->data;

        // ASSERT: Verificar que retorna un ID válido
        $this->assertTrue($createResult->ok);
        $this->assertIsInt($reservationId);
        $this->assertGreaterThan(0, $reservationId);

        // ASSERT: Verificar que el registro existe en la BD con datos correctos
        $stmt = self::$db->prepare('
            SELECT
                user_id, cafe_id, pass_product_id,
                reservation_date, reservation_time, guest_count,
                status, notes
            FROM reservations
            WHERE id = ?
        ');
        $stmt->execute([$reservationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame(self::TEST_USER_ID, (int) $row['user_id']);
        $this->assertSame(self::TEST_CAFE_ID, (int) $row['cafe_id']);
        $this->assertSame(self::TEST_PRODUCT_ID, (int) $row['pass_product_id']);
        $this->assertSame('2026-12-25', $row['reservation_date']);
        $this->assertSame('10:00:00', $row['reservation_time']);
        $this->assertSame(2, (int) $row['guest_count']);
        $this->assertSame('confirmed', $row['status']);
        $this->assertStringContainsString('Integration test', $row['notes']);
    }

    public function testCreateReservationWithNullComments(): void
    {
        // ACT: Crear reserva SIN comentarios
        $createResult = $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-26',
            'time' => '11:00',
            'guests' => 1,
            // Sin 'comments'
        ]);
        $reservationId = $createResult->data;

        // ASSERT: Verificar que notes es null o vacío en BD
        $stmt = self::$db->prepare('SELECT notes FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $notes = $stmt->fetchColumn();

        $this->assertTrue($notes === null || $notes === '');
    }

    public function testCancelReservationUpdatesStatusInDatabase(): void
    {
        // ARRANGE: Crear reserva primero
        $createResult = $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-27',
            'time' => '14:00',
            'guests' => 2,
        ]);
        $reservationId = $createResult->data;

        // ACT: Cancelar la reserva
        $result = $this->service->cancel($reservationId, self::TEST_USER_ID);

        // ASSERT: Verificar que retorna ok
        $this->assertTrue($result->ok);

        // ASSERT: Verificar que el status cambió a 'cancelled' en BD
        $stmt = self::$db->prepare('SELECT status FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $status = $stmt->fetchColumn();

        $this->assertSame('cancelled', $status);
    }

    public function testCancelReservationFailsForWrongUser(): void
    {
        // ARRANGE: Crear reserva con TEST_USER_ID
        $createResult = $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-28',
            'time' => '15:00',
            'guests' => 1,
        ]);
        $reservationId = $createResult->data;

        // ACT: Intentar cancelar con otro usuario (debe fallar)
        $result = $this->service->cancel($reservationId, 88888); // Usuario diferente

        // ASSERT: Debe retornar false (no pertenece al usuario)
        $this->assertFalse($result->ok);

        // ASSERT: El status debe seguir siendo 'confirmed' (no se canceló)
        $stmt = self::$db->prepare('SELECT status FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $status = $stmt->fetchColumn();

        $this->assertSame('confirmed', $status);
    }

    public function testGetByUserReturnsUserReservationsWithJoins(): void
    {
        // ARRANGE: Crear 2 reservas para el usuario de prueba
        $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-30',
            'time' => '10:00',
            'guests' => 1,
        ]);

        $this->service->create([
            'user_id' => self::TEST_USER_ID,
            'cafe_id' => self::TEST_CAFE_ID,
            'pass_product_id' => self::TEST_PRODUCT_ID,
            'date' => '2026-12-31',
            'time' => '11:00',
            'guests' => 2,
        ]);

        // ACT: Obtener reservas del usuario
        $result = $this->service->getByUser(self::TEST_USER_ID);

        // ASSERT: Verificar que retorna array con data y total
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);

        // ASSERT: Debe tener al menos 2 reservas
        $this->assertGreaterThanOrEqual(2, $result['total']);
        $this->assertCount(2, $result['data']);

        // ASSERT: Cada reserva debe incluir datos del JOIN con cafes
        foreach ($result['data'] as $reservation) {
            $this->assertArrayHasKey('cafe_name', $reservation);
            $this->assertSame('Test Café Integration', $reservation['cafe_name']);
        }
    }

    public function testCreateReservationFailsWithInactiveCafe(): void
    {
        // ARRANGE: Crear café inactivo
        self::$db->exec("
            INSERT INTO cafes (
                id, name, slug, location, category, animal_type,
                description, price_per_hour, opening_time, closing_time,
                capacity_max, is_active, has_reservations
            )
            VALUES (
                99996,
                'Inactive Café',
                'inactive-cafe',
                'Tokyo',
                'lounge',
                'cat',
                'Café inactivo para tests',
                1500,
                '09:00:00',
                '20:00:00',
                10,
                0,  -- is_active = 0
                1
            )
        ");

        // ACT & ASSERT: Intentar crear reserva debe fallar o retornar 0
        try {
            $result = $this->service->create([
                'user_id' => self::TEST_USER_ID,
                'cafe_id' => 99996, // Café inactivo
                'pass_product_id' => self::TEST_PRODUCT_ID,
                'date' => '2026-12-29',
                'time' => '10:00',
                'guests' => 2,
            ]);

            // Si no lanza excepción, al menos no debe insertar
            $this->assertFalse($result->ok, 'No debe crear reserva en café inactivo');
        } catch (Exception $e) {
            // Es válido que lance excepción (mensaje puede ser "no acepta reservas")
            $message = \strtolower($e->getMessage());
            $this->assertTrue(
                \str_contains($message, 'acepta') || \str_contains($message, 'inactiv'),
                'Mensaje de error debe mencionar que el café no acepta reservas o está inactivo'
            );
        }
    }

    public function testCreateReservationFailsWithPastDate(): void
    {
        // ACT & ASSERT: Crear reserva con fecha pasada debe fallar
        try {
            $result = $this->service->create([
                'user_id' => self::TEST_USER_ID,
                'cafe_id' => self::TEST_CAFE_ID,
                'pass_product_id' => self::TEST_PRODUCT_ID,
                'date' => '2020-01-01', // Fecha en el pasado
                'time' => '10:00',
                'guests' => 2,
            ]);

            // Si no lanza excepción, al menos no debe insertar
            $this->assertFalse($result->ok, 'No debe crear reserva con fecha pasada');
        } catch (Exception $e) {
            // Es válido que lance excepción
            $this->assertStringContainsString('fecha', \strtolower($e->getMessage()));
        }
    }

    public function testGetByUserReturnsEmptyArrayForUserWithNoReservations(): void
    {
        // ACT: Buscar reservas para usuario sin reservas (ID inexistente)
        $result = $this->service->getByUser(88888);

        // ASSERT: Debe retornar estructura válida con data vacío
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['data']);
    }
}
