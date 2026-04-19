<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use Override;
use Throwable;

/**
 * Servicio de gestión de turnos de staff.
 *
 * Encapsula la lógica de negocio relacionada con staff_shifts.
 * Todos los métodos retornan Result.
 */
final class StaffShiftService implements StaffShiftServiceInterface
{
    public function __construct(private readonly StaffShiftRepositoryInterface $repo)
    {
    }

    /**
     * Obtiene los turnos de un café para la semana actual (hoy + 7 días).
     */
    #[Override]
    public function getWeekShifts(int $cafeId): Result
    {
        $from = \date('Y-m-d');
        $to = \date('Y-m-d', \strtotime('+7 days'));

        $shifts = $this->repo->findByCafeAndDateRange($cafeId, $from, $to);

        return Result::ok($shifts);
    }

    /**
     * Obtiene el historial de turnos recientes de un staff en un café.
     */
    #[Override]
    public function getStaffHistory(int $userId, int $cafeId): Result
    {
        $history = $this->repo->findRecentByUserAndCafe($userId, $cafeId);

        return Result::ok($history);
    }

    /**
     * Asigna un turno verificando que no haya solapamiento.
     *
     * @param string $start Hora inicio normalizada (HH:MM:SS)
     * @param string $end   Hora fin normalizada (HH:MM:SS)
     */
    #[Override]
    public function assignShift(
        int $userId,
        int $cafeId,
        string $date,
        string $start,
        string $end,
        ?string $notes,
        int $createdBy,
    ): Result {
        try {
            // S3-08: Turno no puede cruzar medianoche (start < end obligatorio)
            if ($start >= $end) {
                return Result::fail(
                    'La hora de inicio debe ser anterior a la hora de fin (no se permiten turnos que crucen medianoche)',
                    'invalid_shift_hours'
                );
            }

            if ($this->repo->hasOverlap($userId, $date, $start, $end)) {
                return Result::fail(
                    'El staff member ya tiene un turno asignado en ese horario',
                    'shift_overlap'
                );
            }

            $shiftId = $this->repo->create([
                'user_id' => $userId,
                'cafe_id' => $cafeId,
                'shift_date' => $date,
                'shift_start' => $start,
                'shift_end' => $end,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            Logger::info('[StaffShiftService] Turno asignado', [
                'shift_id' => $shiftId,
                'user_id' => $userId,
                'cafe_id' => $cafeId,
                'date' => $date,
            ]);

            return Result::ok(['shift_id' => $shiftId]);
        } catch (Throwable $e) {
            Logger::error('[StaffShiftService] Error al asignar turno', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Result::fail('Error al asignar turno', 'shift_create_error');
        }
    }

    /**
     * Obtiene métricas de performance de un staff en un café (últimos 30 días).
     */
    #[Override]
    public function getPerformanceMetrics(int $userId, int $cafeId): Result
    {
        try {
            $metrics = $this->repo->getPerformanceMetrics($userId, $cafeId);

            return Result::ok($metrics);
        } catch (Throwable $e) {
            Logger::error('[StaffShiftService] Error al obtener métricas', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Result::fail('Error al obtener métricas de performance', 'metrics_error');
        }
    }
}
