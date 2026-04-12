<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Contracts\TimeSlotServiceInterface;
use PDO;

/**
 * Servicio de consulta de slots de tiempo disponibles.
 *
 * Proporciona disponibilidad horaria desde la tabla `time_slots`
 * para el endpoint público de reservas.
 */
class TimeSlotService implements TimeSlotServiceInterface
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Retorna los slots de tiempo disponibles para una fecha dada.
     *
     * @param string   $date    Fecha en formato YYYY-MM-DD
     * @param int|null $cafeId  Filtrar por café (opcional)
     * @param int|null $guests  Filtrar por plazas mínimas necesarias (opcional)
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function getAvailableSlots(string $date, ?int $cafeId = null, ?int $guests = null): array
    {
        if ($this->db === null) {
            return [];
        }

        $sql    = '
            SELECT
                ts.id,
                ts.cafe_id,
                ts.slot_date,
                ts.slot_time,
                ts.available_spots,
                ts.total_capacity,
                ts.duration_minutes
            FROM time_slots ts
            WHERE ts.slot_date   = :date
              AND ts.is_blocked  = 0
              AND ts.available_spots > 0
        ';
        $params = [':date' => $date];

        if ($cafeId !== null) {
            $sql             .= ' AND ts.cafe_id = :cafe_id';
            $params[':cafe_id'] = $cafeId;
        }

        if ($guests !== null) {
            $sql              .= ' AND ts.available_spots >= :guests';
            $params[':guests'] = $guests;
        }

        $sql .= ' ORDER BY ts.slot_time ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
