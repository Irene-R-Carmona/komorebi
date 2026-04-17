<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests del servicio StaffShiftService, incluyendo obtención de turnos semanales,
 * historial de staff, asignación de turnos con y sin solapamiento, y métricas.
 *
 * ¿Qué me quieres demostrar?
 * Que StaffShiftService delega correctamente al repositorio y envuelve cada
 * resultado en Result::ok / Result::fail según la lógica de negocio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si assignShift deja de verificar solapamientos → testAssignShiftWithOverlapReturnsFailResult falla.
 * - Si se elimina la propagación del shift_id en el dato de respuesta → testAssignShiftWithValidDataReturnsOk falla.
 * - Si getWeekShifts deja de retornar Result::ok → los tests de getWeekShifts fallan.
 */

use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use App\Services\StaffShiftService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para StaffShiftService.
 *
 * El repositorio es stubbed; no se toca base de datos.
 * Todos los métodos del servicio retornan Result{ok, data, error, code}.
 */
final class StaffShiftServiceTest extends TestCase
{
    private StaffShiftService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&StaffShiftRepositoryInterface */
    private StaffShiftRepositoryInterface $repoStub;

    protected function setUp(): void
    {
        $this->repoStub = $this->createMock(StaffShiftRepositoryInterface::class);
        $this->service = new StaffShiftService($this->repoStub);
    }

    // ─────────────────────────────────────────────────────────────
    // getWeekShifts
    // ─────────────────────────────────────────────────────────────

    public function testGetWeekShiftsReturnsOkWithShiftsArray(): void
    {
        $shifts = [
            ['id' => 1, 'user_id' => 10, 'cafe_id' => 5, 'shift_date' => '2026-03-27', 'staff_name' => 'Ana'],
            ['id' => 2, 'user_id' => 11, 'cafe_id' => 5, 'shift_date' => '2026-03-28', 'staff_name' => 'Luis'],
        ];

        $this->repoStub
            ->method('findByCafeAndDateRange')
            ->willReturn($shifts);

        $result = $this->service->getWeekShifts(5);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertSame($shifts, $result->data);
    }

    public function testGetWeekShiftsWithCafeIdReturnsOnlyThatCafesShifts(): void
    {
        $cafeId = 3;
        $shifts = [['id' => 99, 'cafe_id' => $cafeId, 'shift_date' => '2026-03-30']];

        $this->repoStub
            ->method('findByCafeAndDateRange')
            ->willReturn($shifts);

        $result = $this->service->getWeekShifts($cafeId);

        $this->assertTrue($result->ok);
        $this->assertSame($shifts, $result->data);
    }

    public function testGetWeekShiftsReturnsOkWithEmptyArrayWhenNoShiftsExist(): void
    {
        $this->repoStub
            ->method('findByCafeAndDateRange')
            ->willReturn([]);

        $result = $this->service->getWeekShifts(7);

        $this->assertTrue($result->ok);
        $this->assertSame([], $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // getStaffHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetStaffHistoryReturnsOkWithHistoryData(): void
    {
        $history = [
            ['id' => 5, 'shift_date' => '2026-03-01', 'shift_start' => '09:00:00', 'shift_end' => '17:00:00'],
            ['id' => 6, 'shift_date' => '2026-03-08', 'shift_start' => '10:00:00', 'shift_end' => '18:00:00'],
        ];

        $this->repoStub
            ->method('findRecentByUserAndCafe')
            ->willReturn($history);

        $result = $this->service->getStaffHistory(10, 5);

        $this->assertTrue($result->ok);
        $this->assertSame($history, $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // assignShift
    // ─────────────────────────────────────────────────────────────

    public function testAssignShiftWithValidDataReturnsOkWithShiftId(): void
    {
        $this->repoStub
            ->method('hasOverlap')
            ->willReturn(false);

        $this->repoStub
            ->method('create')
            ->willReturn(42);

        $result = $this->service->assignShift(
            userId: 10,
            cafeId: 5,
            date: '2026-03-30',
            start: '09:00:00',
            end: '17:00:00',
            notes: null,
            createdBy: 1,
        );

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertSame(42, $result->data['shift_id']);
    }

    public function testAssignShiftWithOverlappingShiftReturnsFailResult(): void
    {
        $this->repoStub
            ->method('hasOverlap')
            ->willReturn(true);

        $result = $this->service->assignShift(
            userId: 10,
            cafeId: 5,
            date: '2026-03-30',
            start: '09:00:00',
            end: '17:00:00',
            notes: null,
            createdBy: 1,
        );

        $this->assertFalse($result->ok);
        $this->assertSame('shift_overlap', $result->code);
        $this->assertStringContainsString('turno', strtolower($result->getMessage()));
    }

    public function testAssignShiftWithNotesPropagatesNotesCorrectly(): void
    {
        $this->repoStub
            ->method('hasOverlap')
            ->willReturn(false);

        $this->repoStub
            ->method('create')
            ->willReturn(55);

        $result = $this->service->assignShift(
            userId: 10,
            cafeId: 5,
            date: '2026-03-31',
            start: '14:00:00',
            end: '22:00:00',
            notes: 'Turno de tarde especial',
            createdBy: 2,
        );

        $this->assertTrue($result->ok);
        $this->assertSame(55, $result->data['shift_id']);
    }

    // ─────────────────────────────────────────────────────────────
    // getPerformanceMetrics
    // ─────────────────────────────────────────────────────────────

    public function testGetPerformanceMetricsReturnsOkWithMetrics(): void
    {
        $metrics = ['total_shifts' => 12, 'total_hours' => 96];

        $this->repoStub
            ->method('getPerformanceMetrics')
            ->willReturn($metrics);

        $result = $this->service->getPerformanceMetrics(10, 5);

        $this->assertTrue($result->ok);
        $this->assertSame($metrics, $result->data);
    }
}
