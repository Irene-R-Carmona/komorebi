<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de seguridad del ReservationRepository: valida que getSelectFields() jamás expone
 * campos operativos internos (tracker_id, protocol_*, payment_notes) en consultas estándar.
 *
 * ¿Qué me quieres demostrar?
 * Que getSelectFields() no contiene campos internos del staff/KDS y que
 * findWithOperationalData() sí los contiene (contrato de acceso explícito).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si alguien añade campos operativos a getSelectFields(), o elimina findWithOperationalData().
 */

namespace Tests\Integration\Repositories;

use Override;
use ReflectionClass;
use function method_exists;
use App\Repositories\ReservationRepository;
use Tests\Support\BaseIntegrationTest;

final class ReservationRepositorySecurityTest extends BaseIntegrationTest
{
    private const int TEST_RESERVATION_ID = 79001;

    private ReservationRepository $repo;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ReservationRepository(self::$db);
    }

    public function testGetSelectFieldsExcludesInternalOperationalFields(): void
    {
        $reflection = new ReflectionClass($this->repo);
        $method = $reflection->getMethod('getSelectFields');
        $fields = $method->invoke($this->repo);

        $this->assertIsArray($fields);
        $this->assertNotContains('tracker_id', $fields);
        $this->assertNotContains('current_zone_id', $fields);
        $this->assertNotContains('protocol_hygiene', $fields);
        $this->assertNotContains('protocol_briefing', $fields);
        $this->assertNotContains('protocol_shoes', $fields);
        $this->assertNotContains('payment_notes', $fields);
    }

    public function testFindWithOperationalDataMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->repo, 'findWithOperationalData'),
            'ReservationRepository debe exponer findWithOperationalData() para contextos de staff'
        );
    }

    public function testFindWithOperationalDataReturnsOperationalFields(): void
    {
        $this->seedTestReservation();

        $result = $this->repo->findWithOperationalData(self::TEST_RESERVATION_ID);

        $this->assertIsArray($result, 'findWithOperationalData() debe retornar un array para un ID existente');

        // Campos operativos que DEBEN estar presentes
        $this->assertArrayHasKey('tracker_id', $result);
        $this->assertArrayHasKey('current_zone_id', $result);
        $this->assertArrayHasKey('protocol_hygiene', $result);
        $this->assertArrayHasKey('protocol_briefing', $result);
        $this->assertArrayHasKey('protocol_shoes', $result);
        $this->assertArrayHasKey('payment_notes', $result);

        // Verificar valores sembrados
        $this->assertNull($result['tracker_id']);         // BIGINT, sembrado como NULL
        $this->assertNull($result['current_zone_id']);    // BIGINT, sembrado como NULL
        $this->assertSame('Nota de pago de prueba de seguridad', $result['payment_notes']);
        $this->assertSame(1, (int) $result['protocol_hygiene']);
        $this->assertSame(1, (int) $result['protocol_shoes']);
    }

    private function seedTestReservation(): void
    {
        self::$db->exec('DELETE FROM reservations WHERE id = ' . self::TEST_RESERVATION_ID);
        self::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
        self::$db->exec('
            INSERT INTO reservations (
                id, user_id, cafe_id, pass_product_id, pass_name, pass_unit_price,
                pass_duration_minutes, tracker_id, current_zone_id,
                reservation_date, reservation_time, guest_count, status,
                protocol_hygiene, protocol_briefing, protocol_shoes,
                payment_notes, final_amount, payment_status, payment_method,
                notes, created_at, updated_at
            ) VALUES (
                ' . self::TEST_RESERVATION_ID . ',
                1, 1, 1, "Pase test seguridad", 3000,
                90, NULL, NULL,
                CURDATE(), "10:00:00", 2, "confirmed",
                1, 0, 1,
                "Nota de pago de prueba de seguridad", 3000, "pending", "cash",
                NULL, NOW(), NOW()
            )
        ');
        self::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
