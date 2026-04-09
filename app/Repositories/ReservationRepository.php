<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Reservation\ReservationStateMachine;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use PDO;

/**
 * Repositorio de Reservas.
 *
 * Encapsula toda la lógica de acceso a datos de reservas,
 * separándola del modelo y de la lógica de negocio.
 */
final class ReservationRepository extends AbstractRepository implements ReservationRepositoryInterface
{
    #[\Override]
    protected function getTable(): string
    {
        return 'reservations';
    }

    #[\Override]
    protected function getSelectFields(): array
    {
        return [
            'id',
            'user_id',
            'cafe_id',
            'pass_product_id',
            'pass_name',
            'pass_unit_price',
            'pass_duration_minutes',
            'tracker_id',
            'current_zone_id',
            'reservation_date',
            'reservation_time',
            'guest_count',
            'status',
            'check_in_at',
            'check_out_at',
            'protocol_hygiene',
            'protocol_briefing',
            'protocol_shoes',
            'final_amount',
            'payment_status',
            'payment_method',
            'payment_notes',
            'notes',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Buscar reservas activas de un usuario.
     */
    public function findActiveByUser(int $userId): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT {$fields}
             FROM reservations
             WHERE user_id = :user_id
             AND status IN ('pending', 'confirmed', 'active')
             AND deleted_at IS NULL
             ORDER BY reservation_date DESC, reservation_time DESC"
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar reserva por UUID público.
     */
    public function findByUuid(string $uuid): ?array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT $fields FROM reservations WHERE uuid = :uuid LIMIT 1"
        );
        $stmt->execute(['uuid' => $uuid]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Buscar reserva por ID con detalles del café (para PDFs/emails)
     */
    public function findByIdWithCafeDetails(int $id): ?array
    {
        $reservationFields = array_map(
            fn($field) => "r.$field",
            $this->getSelectFields()
        );
        $fields = implode(', ', $reservationFields);

        $stmt = $this->db->prepare(
            "SELECT {$fields},
                    c.name AS cafe_name,
                    c.location AS cafe_location,
                    c.opening_time,
                    c.closing_time,
                    c.animal_type AS cafe_animal_type
             FROM reservations r
             LEFT JOIN cafes c ON r.cafe_id = c.id
             WHERE r.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Buscar reservas de un café en una fecha específica.
     */
    public function findByCafeAndDate(int $cafeId, string $date): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT {$fields}
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = :date
             AND status IN ('pending', 'confirmed', 'active')
             AND deleted_at IS NULL
             ORDER BY reservation_time "
        );
        $stmt->execute([
            'cafe_id' => $cafeId,
            'date' => $date,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar reservas de un café con filtros opcionales de estado y fecha.
     * Uso exclusivo del panel de manager.
     *
     * @param int         $cafeId
     * @param string|null $status Filtrar por estado (null = todos)
     * @param string|null $date   Filtrar por fecha Y-m-d (null = todas)
     * @param int         $limit  Máximo de resultados
     * @return array<int, array<string, mixed>>
     */
    public function findByCafeWithFilters(int $cafeId, ?string $status = null, ?string $date = null, int $limit = 50): array
    {
        $fields = implode(', ', $this->getSelectFields());
        $where  = ['cafe_id = :cafe_id', 'deleted_at IS NULL'];
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $where[]          = 'status = :status';
            $params['status'] = $status;
        }

        if ($date !== null) {
            $where[]        = 'reservation_date = :date';
            $params['date'] = $date;
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->db->prepare(
            "SELECT {$fields}
             FROM reservations
             WHERE {$whereClause}
             ORDER BY reservation_date DESC, reservation_time DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar disponibilidad en un slot de tiempo.
     */
    public function isSlotAvailable(int $cafeId, string $date, string $time): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = :date
             AND reservation_time = :time
             AND status IN ('pending', 'confirmed', 'active')
             AND deleted_at IS NULL"
        );
        $stmt->execute([
            'cafe_id' => $cafeId,
            'date' => $date,
            'time' => $time,
        ]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Actualizar estado de una reserva.
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Registrar check-in.
     */
    public function checkIn(int $id, array $protocolData = []): bool
    {
        $data = [
            'status' => 'active',
            'check_in_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($protocolData['hygiene'])) {
            $data['protocol_hygiene'] = (bool) $protocolData['hygiene'];
        }
        if (isset($protocolData['briefing'])) {
            $data['protocol_briefing'] = (bool) $protocolData['briefing'];
        }
        if (isset($protocolData['shoes'])) {
            $data['protocol_shoes'] = (bool) $protocolData['shoes'];
        }
        if (isset($protocolData['tracker_id'])) {
            $data['tracker_id'] = (int) $protocolData['tracker_id'];
        }
        if (isset($protocolData['zone_id'])) {
            $data['current_zone_id'] = (int) $protocolData['zone_id'];
        }

        return $this->update($id, $data);
    }

    /**
     * Registrar check-out.
     */
    public function checkOut(int $id, array $paymentData = []): bool
    {
        $data = [
            'status' => 'completed',
            'check_out_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($paymentData['final_amount'])) {
            $data['final_amount'] = (int) $paymentData['final_amount'];
        }
        if (isset($paymentData['payment_status'])) {
            $data['payment_status'] = $paymentData['payment_status'];
        }
        if (isset($paymentData['payment_method'])) {
            $data['payment_method'] = $paymentData['payment_method'];
        }
        if (isset($paymentData['payment_notes'])) {
            $data['payment_notes'] = $paymentData['payment_notes'];
        }

        return $this->update($id, $data);
    }

    /**
     * Cancelar reserva (soft delete + cambio de estado).
     */
    public function cancel(int $id, int $userId): bool
    {
        // Verificar que la reserva pertenezca al usuario
        $reservation = $this->findById($id);

        if (!$reservation || (int) $reservation['user_id'] !== $userId) {
            return false;
        }

        // Verificar que sea cancelable
        if (!ReservationStateMachine::isValidTransition($reservation['status'], 'cancelled')) {
            return false;
        }

        return $this->update($id, [
            'status' => 'cancelled',
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marcar como no-show.
     */
    public function markAsNoShow(int $id): bool
    {
        return $this->update($id, [
            'status' => 'no_show',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Contar reservas de un usuario.
     */
    public function countByUser(int $userId, ?string $status = null): int
    {
        $conditions = ['user_id' => $userId];

        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return $this->count($conditions);
    }

    /**
     * Obtener estadísticas de reservas de un café.
     */
    public function getStatsForCafe(int $cafeId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                status,
                COUNT(*) as count,
                SUM(final_amount) as total_amount,
                AVG(guest_count) as avg_guests
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date BETWEEN :start_date AND :end_date
             AND deleted_at IS NULL
             GROUP BY status"
        );
        $stmt->execute([
            'cafe_id' => $cafeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar reservas de un usuario con paginación y filtro de estado.
     */
    public function findByUser(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['r.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($status !== null) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $whereClause = implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM reservations r WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos con detalles del café
        $fields = implode(', ', array_map(fn($f) => "r.$f", $this->getSelectFields()));
        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE {$whereClause}
                ORDER BY r.reservation_date DESC, r.reservation_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Buscar próximas reservas de un usuario.
     */
    public function findUpcomingByUser(int $userId, int $limit = 5): array
    {
        $fields = implode(', ', array_map(fn($f) => "r.$f", $this->getSelectFields()));

        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE r.user_id = :user_id
                  AND r.status IN ('pending', 'confirmed')
                  AND (r.reservation_date > CURDATE()
                       OR (r.reservation_date = CURDATE() AND r.reservation_time >= CURTIME()))
                ORDER BY r.reservation_date, r.reservation_time
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener slots de tiempo disponibles para un café en una fecha.
     */
    public function getAvailableSlots(int $cafeId, string $date): array
    {
        // Obtener info del café
        $stmt = $this->db->prepare(
            'SELECT capacity_max, opening_time, closing_time
             FROM cafes WHERE id = :id AND is_active = 1 AND has_reservations = 1'
        );
        $stmt->execute(['id' => $cafeId]);
        $cafe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cafe) {
            return [];
        }

        // Obtener ocupación por hora
        $sql = "SELECT reservation_time, SUM(guest_count) as booked
                FROM reservations
                WHERE cafe_id = :cafe_id
                  AND reservation_date = :date
                  AND status IN ('pending', 'confirmed', 'active')
                GROUP BY reservation_time";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date]);

        $bookings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookings[$row['reservation_time']] = (int) $row['booked'];
        }

        // Generar slots
        $slots = [];
        $opening = new \DateTimeImmutable($cafe['opening_time']);
        $closing = new \DateTimeImmutable($cafe['closing_time']);
        $current = $opening;

        while ($current < $closing) {
            $timeStr = $current->format('H:i:s');
            $booked = $bookings[$timeStr] ?? 0;
            $available = max(0, (int) $cafe['capacity_max'] - $booked);

            $slots[] = [
                'time' => $current->format('H:i'),
                'available' => $available,
                'bookable' => $available > 0,
            ];

            $current = $current->modify('+1 hour');
        }

        return $slots;
    }

    /**
     * Check if reservation exists for user and datetime
     */
    public function existsForUserAndDateTime(int $userId, int $cafeId, string $date, string $time): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM reservations
             WHERE user_id = :user_id
             AND cafe_id = :cafe_id
             AND reservation_date = :date
             AND reservation_time = :time
             AND status NOT IN ('cancelled', 'no_show')
             AND deleted_at IS NULL
             LIMIT 1"
        );

        $stmt->execute([
            'user_id' => $userId,
            'cafe_id' => $cafeId,
            'date' => $date,
            'time' => $time,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * Get all reservations for a user
     */
    public function findByUserId(int $userId): array
    {
        $fields = implode(', ', $this->getSelectFields());

        $stmt = $this->db->prepare(
            "SELECT {$fields}
             FROM reservations
             WHERE user_id = :user_id
             AND deleted_at IS NULL
             ORDER BY reservation_date DESC, reservation_time DESC"
        );

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
