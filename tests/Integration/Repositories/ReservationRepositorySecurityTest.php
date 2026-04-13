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
}
