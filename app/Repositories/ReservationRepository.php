<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Reservation\ReservationStateMachine;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Repositorio de Reservas.
 *
 * Encapsula toda la lógica de acceso a datos de reservas,
 * separándola del modelo y de la lógica de negocio.
 */
final class ReservationRepository extends AbstractRepository implements ReservationRepositoryInterface
{
    #[Override]
    protected function getTable(): string
    {
        return 'reservations';
    }

    #[Override]
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
            'reservation_date',
            'reservation_time',
            'guest_count',
            'status',
            'check_in_at',
            'check_out_at',
            'final_amount',
            'payment_status',
            'payment_method',
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
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
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
     * Buscar reserva por ID con detalles del café (para PDFs/emails)
     */
    public function findByIdWithCafeDetails(int $id): ?array
    {
        $reservationFields = \array_map(
            fn ($field) => "r.$field",
            $this->getSelectFields()
        );
        $fields = \implode(', ', $reservationFields);

        $stmt = $this->getDb()->prepare(
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
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
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
        $fields = \implode(', ', $this->getSelectFields());
        $where = ['cafe_id = :cafe_id', 'deleted_at IS NULL'];
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($date !== null) {
            $where[] = 'reservation_date = :date';
            $params['date'] = $date;
        }

        $whereClause = \implode(' AND ', $where);

        $stmt = $this->getDb()->prepare(
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
     * Actualizar estado de una reserva.
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Registrar check-in.
     */
    public function checkIn(int $id, array $protocolData = []): bool
    {
        $data = [
            'status' => 'active',
            'check_in_at' => \date('Y-m-d H:i:s'),
            'updated_at' => \date('Y-m-d H:i:s'),
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
            'check_out_at' => \date('Y-m-d H:i:s'),
            'updated_at' => \date('Y-m-d H:i:s'),
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
            'deleted_at' => \date('Y-m-d H:i:s'),
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
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

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM reservations r WHERE $whereClause";
        $stmt = $this->getDb()->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos con detalles del café
        $fields = \implode(', ', \array_map(fn ($f) => "r.$f", $this->getSelectFields()));
        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE {$whereClause}
                ORDER BY r.reservation_date DESC, r.reservation_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->getDb()->prepare($sql);
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
        $fields = \implode(', ', \array_map(fn ($f) => "r.$f", $this->getSelectFields()));

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

        $stmt = $this->getDb()->prepare($sql);
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
        $stmt = $this->getDb()->prepare(
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

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date]);

        $bookings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookings[$row['reservation_time']] = (int) $row['booked'];
        }

        // Generar slots
        $slots = [];
        $opening = new DateTimeImmutable($cafe['opening_time']);
        $closing = new DateTimeImmutable($cafe['closing_time']);
        $current = $opening;

        while ($current < $closing) {
            $timeStr = $current->format('H:i:s');
            $booked = $bookings[$timeStr] ?? 0;
            $available = \max(0, (int) $cafe['capacity_max'] - $booked);

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
        $stmt = $this->getDb()->prepare(
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

    public function findActiveByCafe(int $cafeId): array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields},
                    u.name AS user_name,
                    t.code AS tracker_code,
                    cz.name AS zone_name
             FROM reservations r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN trackers t ON t.id = r.tracker_id
             LEFT JOIN cafe_zones cz ON cz.id = r.current_zone_id
             WHERE r.cafe_id = :cafe_id
               AND r.status = 'active'
             ORDER BY r.check_in_at"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignTracker(int $reservationId, int $trackerId): bool
    {
        $this->getDb()->prepare(
            "UPDATE trackers SET status = 'in_use' WHERE id = :id AND status = 'available'"
        )->execute(['id' => $trackerId]);

        return $this->getDb()->prepare(
            'UPDATE reservations SET tracker_id = :tracker_id WHERE id = :id'
        )->execute(['id' => $reservationId, 'tracker_id' => $trackerId]);
    }

    public function completeProtocol(int $id, string $protocol): bool
    {
        $column = 'protocol_' . $protocol;

        return $this->getDb()->prepare(
            "UPDATE reservations SET {$column} = TRUE WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $fields = \implode(', ', \array_map(static fn ($f) => "r.$f", $this->getSelectFields()));

        $stmt = $this->getDb()->prepare(
            "SELECT $fields, c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
             FROM reservations r
             JOIN cafes c ON c.id = r.cafe_id
             WHERE r.id = :id AND r.user_id = :user_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getDailyStats(int $cafeId, string $date): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(IF(status = 'completed', 1, 0)) as completed,
                SUM(IF(status = 'cancelled', 1, 0)) as cancelled,
                SUM(IF(status = 'no_show', 1, 0)) as no_shows,
                SUM(IF(status IN ('confirmed', 'active'), guest_count, 0)) as current_guests,
                COALESCE(SUM(final_amount), 0) as total_revenue
             FROM reservations
             WHERE cafe_id = :cafe_id AND reservation_date = :date"
        );
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByUuid(string $uuid): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields} FROM reservations WHERE uuid = :uuid LIMIT 1"
        );
        $stmt->execute(['uuid' => $uuid]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function isSlotAvailable(int $cafeId, string $date, string $time): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*)
             FROM reservations
             WHERE cafe_id = :cafe_id
               AND reservation_date = :date
               AND reservation_time = :time
               AND status IN ('pending', 'confirmed', 'active')
               AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date, 'time' => $time]);

        return (int) $stmt->fetchColumn() === 0;
    }

    public function countByUser(int $userId): int
    {
        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) FROM reservations WHERE user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function hasCompletedReservation(int $userId, int $cafeId): bool
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE user_id = :user_id AND cafe_id = :cafe_id AND status = 'completed'"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findWithOperationalData(int $id): ?array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT *
             FROM reservations
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
