<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Repositories\RepositoryInterface;

/**
 * Interfaz del repositorio de turnos de staff.
 *
 * Define operaciones de acceso a datos específicas de staff_shifts.
 */
interface StaffShiftRepositoryInterface extends RepositoryInterface
{
    /**
     * Obtener turnos de un café en un rango de fechas, incluyendo nombre del staff.
     *
     * @param int    $cafeId ID del café
     * @param string $from   Fecha inicio (YYYY-MM-DD)
     * @param string $to     Fecha fin (YYYY-MM-DD)
     * @return array<int, array<string, mixed>>
     */
    public function findByCafeAndDateRange(int $cafeId, string $from, string $to): array;

    /**
     * Obtener historial de turnos de un usuario en un café (últimos 30 días).
     *
     * @param int $userId ID del staff member
     * @param int $cafeId ID del café
     * @param int $limit  Máximo de resultados
     * @return array<int, array<string, mixed>>
     */
    public function findRecentByUserAndCafe(int $userId, int $cafeId, int $limit = 50): array;

    /**
     * Verificar si existe solapamiento de turno para un usuario.
     *
     * @param int    $userId ID del usuario
     * @param string $date   Fecha (YYYY-MM-DD)
     * @param string $start  Hora inicio (HH:MM:SS)
     * @param string $end    Hora fin (HH:MM:SS)
     */
    public function hasOverlap(int $userId, string $date, string $start, string $end): bool;

    /**
     * Obtener métricas de performance de un staff en un café (últimos 30 días).
     *
     * @return array{total_shifts: int, total_hours: float, shifts_this_month: int, avg_shift_duration: float}
     */
    public function getPerformanceMetrics(int $userId, int $cafeId): array;
}
