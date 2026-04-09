<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\TimeSlotRepositoryInterface;
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
        $stmt = $this->db->prepare("
            SELECT
                id, cafe_id, slot_date, slot_time, total_capacity,
                available_spots, reserved_spots, is_blocked, blocked_reason,
                duration_minutes, created_by, created_at, updated_at
            FROM time_slots
            WHERE id = ?
        ");

        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableCapacity(int $timeSlotId): int
    {
        $stmt = $this->db->prepare("
            SELECT available_spots
            FROM time_slots
            WHERE id = ?
        ");

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
        $stmt = $this->db->prepare("
            SELECT is_blocked
            FROM time_slots
            WHERE id = ?
        ");

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
        $stmt = $this->db->prepare("
            UPDATE time_slots
            SET
                available_spots = available_spots - :spots,
                reserved_spots = reserved_spots + :spots,
                updated_at = NOW()
            WHERE id = :id
              AND available_spots >= :spots
              AND is_blocked = 0
        ");

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
        $stmt = $this->db->prepare("
            UPDATE time_slots
            SET
                available_spots = available_spots + :spots,
                reserved_spots = reserved_spots - :spots,
                updated_at = NOW()
            WHERE id = :id
              AND reserved_spots >= :spots
        ");

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
        $stmt = $this->db->prepare("
            SELECT
                id, cafe_id, slot_date, slot_time, total_capacity,
                available_spots, reserved_spots, duration_minutes
            FROM time_slots
            WHERE cafe_id = ?
              AND slot_date = ?
              AND is_blocked = 0
              AND available_spots > 0
            ORDER BY slot_time ASC
        ");

        $stmt->execute([$cafeId, $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
