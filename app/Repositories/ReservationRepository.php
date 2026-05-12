<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Pagination;
use App\Domain\DTO\ReservationDTO;
use App\Domain\Mappers\ReservationMapper;
use App\Domain\Reservation\ReservationStateMachine;
use App\Domain\ReservationVocabulary;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PDO;

final class ReservationRepository extends AbstractRepository implements ReservationRepositoryInterface
{
    private ReservationMapper $mapper;

    public function __construct(?PDO $db = null, ?ReservationMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new ReservationMapper();
    }

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
            'time_slot_id',
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
            'payment_notes',
            'notes',
            'cancellation_reason',
            'manager_notes',
            'refund_amount',
            'refunded_at',
            'loyalty_awarded',
            'tracker_id',
            'current_zone_id',
            'protocol_hygiene',
            'protocol_briefing',
            'protocol_shoes',
            'deleted_at',
            'created_at',
            'updated_at',
        ];
    }

    #[Override]
    public function findById(int $id): ?ReservationDTO
    {
        $row = $this->findByIdRaw($id);

        return $row !== null ? $this->mapper->toDTO($row) : null;
    }

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
                    c.animal_type AS cafe_animal_type,
                    u.name AS user_name,
                    u.email AS user_email
             FROM reservations r
             LEFT JOIN cafes c ON r.cafe_id = c.id
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function findByCafeAndDate(int $cafeId, string $date): array
    {
        $fields = \implode(', ', \array_map(static fn ($f) => "r.$f", $this->getSelectFields()));

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields},
                    u.name AS user_name,
                    sa.table_code
             FROM reservations r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN supervisor_assignments sa ON sa.reservation_id = r.id AND sa.is_active = 1
             WHERE r.cafe_id = :cafe_id
             AND r.reservation_date = :date
             AND r.status IN ('pending', 'confirmed', 'active')
             AND r.deleted_at IS NULL
             ORDER BY r.reservation_time "
        );
        $stmt->execute([
            'cafe_id' => $cafeId,
            'date' => $date,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByCafeWithFilters(int $cafeId, ?string $status = null, ?string $date = null, int $page = 1): array
    {
        $page = \max(1, $page);
        $perPage = Pagination::DEFAULT_LIMIT;
        $fetchLimit = $perPage + 1;
        $offset = ($page - 1) * $perPage;

        $fields = \implode(', ', \array_map(static fn ($f) => "r.$f", $this->getSelectFields()));
        $where = ['r.cafe_id = :cafe_id', 'r.deleted_at IS NULL'];
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        if ($date !== null) {
            $where[] = 'r.reservation_date = :date';
            $params['date'] = $date;
        }

        $whereClause = \implode(' AND ', $where);

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields},
                    COUNT(ri.id) AS items_count
             FROM reservations r
             LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
             WHERE {$whereClause}
             GROUP BY r.id
             ORDER BY r.reservation_date DESC, r.reservation_time DESC
             LIMIT {$fetchLimit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    public function updateStatusWithReason(int $id, string $status, string $reason): bool
    {
        return $this->update($id, [
            'status' => $status,
            'cancellation_reason' => $reason,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    public function recordRefund(int $id, int $amountCents, string $notes): bool
    {
        return $this->update($id, [
            'refund_amount' => $amountCents,
            'refunded_at' => \date('Y-m-d H:i:s'),
            'payment_status' => 'cancelled',
            'manager_notes' => $notes,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    public function updateInvoicePdfUrl(int $id, string $url): bool
    {
        return $this->update($id, [
            'invoice_pdf_url' => $url,
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

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
            if (!ReservationVocabulary::isValidPaymentMethod((string) $paymentData['payment_method'])) {
                throw new InvalidArgumentException('Método de pago no válido: ' . $paymentData['payment_method']);
            }

            $data['payment_method'] = $paymentData['payment_method'];
        }
        if (isset($paymentData['payment_notes'])) {
            $data['payment_notes'] = $paymentData['payment_notes'];
        }

        return $this->update($id, $data);
    }

    public function cancel(int $id, int $userId): bool
    {
        $reservation = $this->findById($id);

        if (!$reservation || (int) $reservation->user_id !== $userId) {
            return false;
        }

        if (!ReservationStateMachine::isValidTransition($reservation->status, 'cancelled')) {
            return false;
        }

        return $this->update($id, [
            'status' => 'cancelled',
            'deleted_at' => \date('Y-m-d H:i:s'),
            'updated_at' => \date('Y-m-d H:i:s'),
        ]);
    }

    public function findByUser(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['r.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($status !== null) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $whereClause = \implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM reservations r WHERE $whereClause";
        $stmt = $this->getDb()->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $fields = \implode(', ', \array_map(fn ($f) => "r.$f", $this->getSelectFields()));
        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image,
                       GROUP_CONCAT(CONCAT(ri.quantity, 'x ', p.name) ORDER BY p.name SEPARATOR ' · ') AS order_summary
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
                LEFT JOIN products p ON p.id = ri.product_id
                WHERE {$whereClause}
                GROUP BY r.id
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

    public function getAvailableSlots(int $cafeId, string $date): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT capacity_max, opening_time, closing_time
             FROM cafes WHERE id = :id AND is_active = 1 AND has_reservations = 1'
        );
        $stmt->execute(['id' => $cafeId]);
        $cafe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cafe) {
            return [];
        }

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
        $fields = \implode(', ', \array_map(static fn ($f) => "r.$f", $this->getSelectFields()));

        $stmt = $this->getDb()->prepare(
            "SELECT {$fields},
                    u.name AS user_name,
                    t.code AS tracker_code,
                    cz.name AS zone_name,
                    COUNT(ri.id) AS items_count
             FROM reservations r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN trackers t ON t.id = r.tracker_id
             LEFT JOIN cafe_zones cz ON cz.id = r.current_zone_id
             LEFT JOIN reservation_items ri ON ri.reservation_id = r.id
             WHERE r.cafe_id = :cafe_id
               AND r.status = 'active'
             GROUP BY r.id, u.name, t.code, cz.name
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

    #[Override]
    public function getCompletedByUserAndCafe(int $userId, int $cafeId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id FROM reservations
             WHERE user_id = :user_id AND cafe_id = :cafe_id AND status = 'completed'
             AND deleted_at IS NULL"
        );
        $stmt->execute(['user_id' => $userId, 'cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    #[Override]
    public function insertPreOrderItems(int $reservationId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $placeholders = \implode(', ', \array_fill(0, \count($items), "(?, ?, ?, ?, 'pre_order')"));
        $sql = "INSERT INTO reservation_items (reservation_id, product_id, quantity, unit_price, status) VALUES {$placeholders}";

        $params = [];
        foreach ($items as $item) {
            $params[] = $reservationId;
            $params[] = (int) $item['product_id'];
            $params[] = (int) $item['qty'];
            $params[] = (int) ($item['unit_price'] ?? 0);
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
    }

    #[Override]
    public function deletePreOrderItems(int $reservationId): int
    {
        $stmt = $this->getDb()->prepare(
            "DELETE FROM reservation_items
             WHERE reservation_id = :reservation_id AND status = 'pre_order'"
        );
        $stmt->execute(['reservation_id' => $reservationId]);

        return $stmt->rowCount();
    }

    #[Override]
    public function getPreOrderItems(int $reservationId): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT ri.id, ri.product_id, ri.quantity,
                    p.name, p.price, p.stock_quantity,
                    mc.id AS category_id, mc.name AS category_name
             FROM reservation_items ri
             JOIN products p ON p.id = ri.product_id
             JOIN menu_categories mc ON mc.id = p.category_id
             WHERE ri.reservation_id = :reservation_id AND ri.status = 'pre_order'
             ORDER BY mc.display_order, p.name"
        );
        $stmt->execute(['reservation_id' => $reservationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Indica si una reserva (aún no completado el check-in) tiene ítems que
     * fueron pre-comanda y ya se activaron (status != 'pre_order').
     *
     * Solo es fiable para reservas con status='confirmed' (antes del check-in):
     * en ese estado los únicos ítems 'pending'/'kitchen'/'ready'/'served' son
     * pre-comandas activadas manualmente, ya que el POS solo está disponible
     * tras el check-in.
     */
    #[Override]
    public function countActivatedPreOrders(int $reservationId): int
    {
        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM reservation_items
             WHERE reservation_id = :reservation_id
               AND status IN ('pending', 'kitchen', 'ready', 'served')"
        );
        $stmt->execute(['reservation_id' => $reservationId]);

        return (int) $stmt->fetchColumn();
    }
}
