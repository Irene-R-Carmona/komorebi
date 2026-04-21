<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Comportamiento del SupervisorAssignmentService con repositorio en base de datos.
 *
 * ¿Qué me quieres demostrar?
 * Que el servicio valida los campos requeridos y delega correctamente en el repositorio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cambian las reglas de validación de createFromArray() o las firmas de los
 * métodos del repositorio que el servicio invoca.
 */

use App\Repositories\Contracts\SupervisorAssignmentRepositoryInterface;
use App\Services\SupervisorAssignmentService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SupervisorAssignmentService::class)]
final class SupervisorAssignmentServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\Stub&SupervisorAssignmentRepositoryInterface */
    private SupervisorAssignmentRepositoryInterface $repo;
    private SupervisorAssignmentService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createStub(SupervisorAssignmentRepositoryInterface::class);
        $this->service = new SupervisorAssignmentService($this->repo);
    }

    public function testCreateFromArrayHappyPath(): void
    {
        $createdRecord = [
            'id' => 1,
            'supervisor_id' => 5,
            'reservation_id' => 123,
            'table_code' => 'A1',
            'cafe_id' => 2,
            'is_active' => 1,
            'assigned_at' => '2026-03-27 10:00:00',
            'created_at' => '2026-03-27 10:00:00',
        ];

        $this->repo->method('createAssignment')->willReturn(1);
        $this->repo->method('findById')->willReturn($createdRecord);

        $result = $this->service->createFromArray([
            'reservation_id' => 123,
            'table_code' => 'A1',
            'supervisor_id' => 5,
            'cafe_id' => 2,
        ]);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertSame(123, $result->data['reservation_id']);
        $this->assertSame('A1', $result->data['table_code']);
    }

    public function testCreateFromArrayMissingReservationId(): void
    {
        $result = $this->service->createFromArray([
            'table_code' => 'A1',
            'supervisor_id' => 5,
            'cafe_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateFromArrayMissingTableCode(): void
    {
        $result = $this->service->createFromArray([
            'reservation_id' => 123,
            'supervisor_id' => 5,
            'cafe_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('validation_error', $result->code);
    }

    public function testCreateFromArrayMissingSupervisorId(): void
    {
        $result = $this->service->createFromArray([
            'reservation_id' => 123,
            'table_code' => 'A1',
            'cafe_id' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('auth_error', $result->code);
    }

    public function testListAssignmentsReturnsRows(): void
    {
        $rows = [
            ['id' => 1, 'reservation_id' => 10, 'table_code' => 'B2'],
            ['id' => 2, 'reservation_id' => 11, 'table_code' => 'C3'],
        ];

        $this->repo->method('findAll')->willReturn($rows);

        $result = $this->service->listAssignments();

        $this->assertTrue($result->ok);
        $this->assertCount(2, (array) $result->data);
    }

    public function testListAssignmentsOnDbErrorReturnsFailResult(): void
    {
        $this->repo->method('findAll')->willThrowException(new \RuntimeException('DB down'));

        $result = $this->service->listAssignments();

        $this->assertFalse($result->ok);
        $this->assertSame('db_error', $result->code);
    }
}
