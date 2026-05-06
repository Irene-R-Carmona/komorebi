<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\StaffShiftDTO;
use App\Domain\Mappers\StaffShiftMapper;
use App\Repositories\Contracts\StaffShiftRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de turnos de staff.
 *
 * Encapsula todas las queries sobre la tabla staff_shifts.
 */
final class StaffShiftRepository extends AbstractRepository implements StaffShiftRepositoryInterface
{
    private StaffShiftMapper $mapper;

    public function __construct(?PDO $db = null, ?StaffShiftMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new StaffShiftMapper();
    }

    #[Override]
    public function findById(int $id): ?StaffShiftDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT ss.id, ss.user_id, ss.cafe_id, ss.shift_date,
                    ss.shift_start, ss.shift_end, ss.notes, ss.created_by,
                    ss.created_at, ss.updated_at, ss.deleted_at,
                    u.name AS staff_name
             FROM staff_shifts ss
             LEFT JOIN users u ON ss.user_id = u.id
             WHERE ss.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->mapper->toDTO($row) : null;
    }
    #[Override]
    protected function getTable(): string
    {
        return 'staff_shifts';
    }

    #[Override]
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

    #[Override]
    public function findByCafeAndDateRange(int $cafeId, string $from, string $to): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT ss.id, ss.user_id, ss.cafe_id, ss.shift_date,
                    ss.shift_start, ss.shift_end, ss.notes, ss.created_at,
                    u.name AS staff_name
             FROM staff_shifts ss
             JOIN users u ON ss.user_id = u.id
             WHERE ss.cafe_id = :cafe_id
               AND ss.shift_date BETWEEN :from AND :to
               AND ss.deleted_at IS NULL
             ORDER BY ss.shift_date ASC, ss.shift_start ASC'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'from' => $from, 'to' => $to]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function findRecentByUserAndCafe(int $userId, int $cafeId, int $limit = 50): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, user_id, cafe_id, shift_date, shift_start,
                    shift_end, notes, created_at
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND deleted_at IS NULL
             ORDER BY shift_date DESC, shift_start DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function hasOverlap(int $userId, string $date, string $start, string $end): bool
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id FROM staff_shifts
             WHERE user_id = :user_id
               AND shift_date = :date
               AND deleted_at IS NULL
               AND (
                   (shift_start <= :start1 AND shift_end > :start2)
                   OR (shift_start < :end1 AND shift_end >= :end2)
                   OR (shift_start >= :start3 AND shift_end <= :end3)
               )
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'date' => $date,
            'start1' => $start,
            'start2' => $start,
            'start3' => $start,
            'end1' => $end,
            'end2' => $end,
            'end3' => $end,
        ]);

        return (bool) $stmt->fetch();
    }

    #[Override]
    public function getPerformanceMetrics(int $userId, int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) AS total_shifts,
                    COALESCE(SUM(TIMESTAMPDIFF(HOUR, shift_start, shift_end)), 0) AS total_hours
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND deleted_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $this->getDb()->prepare(
            'SELECT COUNT(*) AS shifts_this_month
             FROM staff_shifts
             WHERE user_id = :user_id
               AND cafe_id = :cafe_id
               AND MONTH(shift_date) = MONTH(CURDATE())
               AND YEAR(shift_date) = YEAR(CURDATE())
               AND deleted_at IS NULL'
        );
        $stmt2->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $totalShifts = (int) ($row['total_shifts'] ?? 0);
        $totalHours = (float) ($row['total_hours'] ?? 0);

        return [
            'total_shifts' => $totalShifts,
            'total_hours' => $totalHours,
            'shifts_this_month' => (int) ($row2['shifts_this_month'] ?? 0),
            'avg_shift_duration' => $totalShifts > 0 ? \round($totalHours / $totalShifts, 2) : 0.0,
        ];
    }

    #[Override]
    public function update(int $id, array $data): bool
    {
        $allowed = ['shift_date', 'shift_start', 'shift_end', 'notes'];
        $sets = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (\array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = 'updated_at = :updated_at';
        $params['updated_at'] = \date('Y-m-d H:i:s');

        $stmt = $this->getDb()->prepare(
            'UPDATE staff_shifts SET ' . \implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    #[Override]
    public function delete(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE staff_shifts SET deleted_at = :now WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['now' => \date('Y-m-d H:i:s'), 'id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
