<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface StaffShiftServiceInterface
{
    /**
     * Obtiene turnos de un café para la semana indicada por `$weekOffset` (0 = semana actual).
     * Retorna data: ['shifts' => array, 'from' => string, 'to' => string]
     */
    public function getWeekShifts(int $cafeId, int $weekOffset = 0): Result;

    public function getStaffHistory(int $userId, int $cafeId): Result;

    public function assignShift(
        int $userId,
        int $cafeId,
        string $date,
        string $start,
        string $end,
        ?string $notes,
        int $createdBy,
    ): Result;

    public function getPerformanceMetrics(int $userId, int $cafeId): Result;

    /**
     * Actualizar un turno verificando que pertenezca al café del manager.
     *
     * @param array<string, mixed> $data Campos a actualizar
     */
    public function updateShift(int $shiftId, int $cafeId, array $data): Result;

    /**
     * Eliminar (soft-delete) un turno verificando que pertenezca al café del manager.
     */
    public function deleteShift(int $shiftId, int $cafeId): Result;
}
