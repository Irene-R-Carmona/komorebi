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
    public function __construct(private readonly StaffShiftRepositoryInterface $repo) {}

    /**
     * Obtiene los turnos de un café para la semana indicada por `$weekOffset` (0 = semana actual).
     * Retorna data: ['shifts' => array, 'from' => string (YYYY-MM-DD lunes), 'to' => string (YYYY-MM-DD domingo)]
     */
    #[Override]
    public function getWeekShifts(int $cafeId, int $weekOffset = 0): Result
    {
        $offset = \max(-52, \min(52, $weekOffset));
        $sign = $offset >= 0 ? '+' : '-';
        $weeks = \abs($offset);
        $mondayTs = \strtotime("monday this week {$sign}{$weeks} week");
        $from = \date('Y-m-d', $mondayTs);
        $to = \date('Y-m-d', $mondayTs + 6 * 86400);

        $shifts = $this->repo->findByCafeAndDateRange($cafeId, $from, $to);

        return Result::ok(['shifts' => $shifts, 'from' => $from, 'to' => $to]);
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

    /**
     * Actualiza un turno verificando que pertenezca al café del manager.
     *
     * @param array<string, mixed> $data Campos a actualizar (shift_date, shift_start, shift_end, notes)
     */
    #[Override]
    public function updateShift(int $shiftId, int $cafeId, array $data): Result
    {
        try {
            $shift = $this->repo->findById($shiftId);

            if ($shift === null || $shift->cafe_id !== $cafeId) {
                return Result::fail('Turno no encontrado o no pertenece a tu café', 'shift_not_found');
            }

            $start = isset($data['shift_start']) ? (string) $data['shift_start'] : null;
            $end = isset($data['shift_end']) ? (string) $data['shift_end'] : null;

            if ($start !== null && $end !== null && $start >= $end) {
                return Result::fail(
                    'La hora de inicio debe ser anterior a la hora de fin',
                    'invalid_shift_hours'
                );
            }

            $ok = $this->repo->update($shiftId, $data);

            if (!$ok) {
                return Result::fail('No se pudo actualizar el turno', 'shift_update_error');
            }

            Logger::info('[StaffShiftService] Turno actualizado', [
                'shift_id' => $shiftId,
                'cafe_id' => $cafeId,
            ]);

            return Result::ok(['shift_id' => $shiftId]);
        } catch (Throwable $e) {
            Logger::error('[StaffShiftService] Error al actualizar turno', [
                'exception' => $e->getMessage(),
                'shift_id' => $shiftId,
            ]);

            return Result::fail('Error al actualizar turno', 'shift_update_error');
        }
    }

    /**
     * Elimina (soft-delete) un turno verificando que pertenezca al café del manager.
     */
    #[Override]
    public function deleteShift(int $shiftId, int $cafeId): Result
    {
        try {
            $shift = $this->repo->findById($shiftId);

            if ($shift === null || $shift->cafe_id !== $cafeId) {
                return Result::fail('Turno no encontrado o no pertenece a tu café', 'shift_not_found');
            }

            $ok = $this->repo->delete($shiftId);

            if (!$ok) {
                return Result::fail('No se pudo eliminar el turno', 'shift_delete_error');
            }

            Logger::info('[StaffShiftService] Turno eliminado', [
                'shift_id' => $shiftId,
                'cafe_id' => $cafeId,
            ]);

            return Result::ok(['shift_id' => $shiftId]);
        } catch (Throwable $e) {
            Logger::error('[StaffShiftService] Error al eliminar turno', [
                'exception' => $e->getMessage(),
                'shift_id' => $shiftId,
            ]);

            return Result::fail('Error al eliminar turno', 'shift_delete_error');
        }
    }
}
