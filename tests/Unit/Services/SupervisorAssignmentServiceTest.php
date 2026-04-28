<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? SupervisorAssignmentService: validaciones de createFromArray.
 * ¿Qué me quieres demostrar? Que createFromArray retorna fail si faltan campos requeridos.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las validaciones de reservation_id o table_code.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\SupervisorAssignmentRepositoryInterface;
use App\Services\SupervisorAssignmentService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupervisorAssignmentService::class)]
final class SupervisorAssignmentServiceTest extends TestCase
{
    private SupervisorAssignmentRepositoryInterface $repoStub;
    private SupervisorAssignmentService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(SupervisorAssignmentRepositoryInterface::class);
        $this->service  = new SupervisorAssignmentService($this->repoStub);
    }

    public function testCreateFromArrayFailsWhenReservationIdMissing(): void
    {
        $result = $this->service->createFromArray([
            'table_code'    => 'A1',
            'supervisor_id' => 1,
            'cafe_id'       => 1,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateFromArrayFailsWhenTableCodeEmpty(): void
    {
        $result = $this->service->createFromArray([
            'reservation_id' => 5,
            'table_code'     => '',
            'supervisor_id'  => 1,
            'cafe_id'        => 1,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateFromArrayFailsWhenSupervisorIdMissing(): void
    {
        $result = $this->service->createFromArray([
            'reservation_id' => 5,
            'table_code'     => 'B2',
            'supervisor_id'  => 0,
            'cafe_id'        => 1,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('auth_error', $result->code);
    }

    public function testCreateFromArrayFailsWhenCafeIdMissing(): void
    {
        $result = $this->service->createFromArray([
            'reservation_id' => 5,
            'table_code'     => 'B2',
            'supervisor_id'  => 1,
            'cafe_id'        => 0,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('auth_error', $result->code);
    }
}
