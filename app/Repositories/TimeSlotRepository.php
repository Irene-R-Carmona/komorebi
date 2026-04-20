<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de Time Slots
 *
 * Implementa acceso a datos de slots de tiempo con prepared statements
 * y operaciones atómicas para manejo de concurrencia.
 */
final class TimeSlotRepository implements TimeSlotRepositoryInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                id, cafe_id, slot_date, slot_time, total_capacity,
                available_spots, reserved_spots, is_blocked, blocked_reason,
                duration_minutes, created_by, created_at, updated_at
            FROM time_slots
            WHERE id = ?
        ');

        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableCapacity(int $timeSlotId): int
    {
        $stmt = $this->db->prepare('
            SELECT available_spots
            FROM time_slots
            WHERE id = ?
        ');

        $stmt->execute([$timeSlotId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int) $result['available_spots'] : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isFull(int $timeSlotId): bool
    {
        return $this->getAvailableCapacity($timeSlotId) <= 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isBlocked(int $timeSlotId): bool
    {
        $stmt = $this->db->prepare('
            SELECT is_blocked
            FROM time_slots
            WHERE id = ?
        ');

        $stmt->execute([$timeSlotId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (bool) $result['is_blocked'] : false;
    }

    /**
     * {@inheritDoc}
     */
    public function reserveSpots(int $timeSlotId, int $spots): bool
    {
        // Operación atómica: decrementar available_spots e incrementar reserved_spots
        // Solo si hay capacidad suficiente
        $stmt = $this->db->prepare('
            UPDATE time_slots
            SET
                available_spots = available_spots - :spots,
                reserved_spots = reserved_spots + :spots,
                updated_at = NOW()
            WHERE id = :id
              AND available_spots >= :spots
              AND is_blocked = 0
        ');

        $stmt->execute([
            'id' => $timeSlotId,
            'spots' => $spots,
        ]);

        // Retornar true solo si se modificó la fila
        return $stmt->rowCount() > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSpots(int $timeSlotId, int $spots): bool
    {
        // Operación atómica: incrementar available_spots y decrementar reserved_spots
        $stmt = $this->db->prepare('
            UPDATE time_slots
            SET
                available_spots = available_spots + :spots,
                reserved_spots = reserved_spots - :spots,
                updated_at = NOW()
            WHERE id = :id
              AND reserved_spots >= :spots
        ');

        $stmt->execute([
            'id' => $timeSlotId,
            'spots' => $spots,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function findAvailableSlots(int $cafeId, string $date): array
    {
        $stmt = $this->db->prepare('
            SELECT
                id, cafe_id, slot_date, slot_time, total_capacity,
                available_spots, reserved_spots, duration_minutes
            FROM time_slots
            WHERE cafe_id = ?
              AND slot_date = ?
              AND is_blocked = 0
              AND available_spots > 0
            ORDER BY slot_time ASC
        ');

        $stmt->execute([$cafeId, $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAvailableRange(int $cafeId, string $startDate, string $endDate, int $minSpots = 1): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cafe_id, slot_date, slot_time, total_capacity,
                    available_spots, reserved_spots, duration_minutes,
                    ROUND((reserved_spots / total_capacity) * 100, 2) AS occupancy_percentage,
                    CASE
                        WHEN available_spots = 0 THEN \'full\'
                        WHEN available_spots <= 5 THEN \'limited\'
                        ELSE \'available\'
                    END AS availability_status
             FROM time_slots
             WHERE cafe_id = :cafe_id
               AND slot_date BETWEEN :start_date AND :end_date
               AND is_blocked = FALSE
               AND available_spots >= :min_spots
             ORDER BY slot_date ASC, slot_time ASC'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'start_date' => $startDate, 'end_date' => $endDate, 'min_spots' => $minSpots]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOccupancyStats(int $cafeId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_slots,
                SUM(total_capacity) AS total_capacity_sum,
                SUM(reserved_spots) AS total_reserved,
                SUM(available_spots) AS total_available,
                ROUND(AVG((reserved_spots / total_capacity) * 100), 2) AS avg_occupancy_percentage,
                COUNT(CASE WHEN available_spots = 0 THEN 1 END) AS fully_booked_count,
                COUNT(CASE WHEN is_blocked = TRUE THEN 1 END) AS blocked_count
             FROM time_slots
             WHERE cafe_id = :cafe_id
               AND slot_date BETWEEN :start_date AND :end_date'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'start_date' => $startDate, 'end_date' => $endDate]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function findAvailableByDateFiltered(string $date, ?int $cafeId = null, ?int $guests = null): array
    {
        $sql = '
            SELECT
                ts.id,
                ts.cafe_id,
                ts.slot_date,
                ts.slot_time,
                ts.available_spots,
                ts.total_capacity,
                ts.duration_minutes
            FROM time_slots ts
            WHERE ts.slot_date      = :date
              AND ts.is_blocked     = 0
              AND ts.available_spots > 0
        ';
        $params = [':date' => $date];

        if ($cafeId !== null) {
            $sql .= ' AND ts.cafe_id = :cafe_id';
            $params[':cafe_id'] = $cafeId;
        }

        if ($guests !== null) {
            $sql .= ' AND ts.available_spots >= :guests';
            $params[':guests'] = $guests;
        }

        $sql .= ' ORDER BY ts.slot_time ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
