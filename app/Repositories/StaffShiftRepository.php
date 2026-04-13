<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use PDO;

/**
 * Repositorio de turnos de staff.
 *
 * Encapsula todas las queries sobre la tabla staff_shifts.
 */
final class StaffShiftRepository extends AbstractRepository implements StaffShiftRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'staff_shifts';
    }

    #[\Override]
    protected function getSelectFields(): array
    {
        return [
            'id',
            'user_id',
            'cafe_id',
            'shift_date',
            'shift_start',
            'shift_end',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ];
    }

    #[\Override]
    public function findByCafeAndDateRange(int $cafeId, string $from, string $to): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT ss.id, ss.user_id, ss.cafe_id, ss.shift_date,
                    ss.shift_start, ss.shift_end, ss.notes, ss.created_at,
                    u.name AS staff_name
             FROM staff_shifts ss
             JOIN users u ON ss.user_id = u.id
             WHERE ss.cafe_id = :cafe_id
               AND ss.shift_date BETWEEN :from AND :to
               AND ss.deleted_at IS NULL
             ORDER BY ss.shift_date ASC, ss.shift_start ASC"
        );
        $stmt->execute(['cafe_id' => $cafeId, 'from' => $from, 'to' => $to]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function findRecentByUserAndCafe(int $userId, int $cafeId, int $limit = 50): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, user_id, cafe_id, shift_date, shift_start,
                    shift_end, notes, created_at
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND deleted_at IS NULL
             ORDER BY shift_date DESC, shift_start DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function hasOverlap(int $userId, string $date, string $start, string $end): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id FROM staff_shifts
             WHERE user_id = :user_id
               AND shift_date = :date
               AND deleted_at IS NULL
               AND (
                   (shift_start <= :start AND shift_end > :start)
                   OR (shift_start < :end AND shift_end >= :end)
                   OR (shift_start >= :start AND shift_end <= :end)
               )
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'date'    => $date,
            'start'   => $start,
            'end'     => $end,
        ]);

        return (bool) $stmt->fetch();
    }

    #[\Override]
    public function getPerformanceMetrics(int $userId, int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) AS total_shifts,
                    COALESCE(SUM(TIMESTAMPDIFF(HOUR, shift_start, shift_end)), 0) AS total_hours
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND deleted_at IS NULL"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $this->getDb()->prepare(
            "SELECT COUNT(*) AS shifts_this_month
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND MONTH(shift_date) = MONTH(CURDATE())
               AND YEAR(shift_date) = YEAR(CURDATE())
               AND deleted_at IS NULL"
        );
        $stmt2->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $totalShifts = (int) ($row['total_shifts'] ?? 0);
        $totalHours  = (float) ($row['total_hours'] ?? 0);

        return [
            'total_shifts'       => $totalShifts,
            'total_hours'        => $totalHours,
            'shifts_this_month'  => (int) ($row2['shifts_this_month'] ?? 0),
            'avg_shift_duration' => $totalShifts > 0 ? round($totalHours / $totalShifts, 2) : 0.0,
        ];
    }
}
