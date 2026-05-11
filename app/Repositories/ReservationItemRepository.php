<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\ReservationItemDTO;
use App\Domain\Mappers\ReservationItemMapper;
use App\Models\ReservationItem;
use App\Repositories\Contracts\ReservationItemRepositoryInterface;
use Override;
use PDO;

final class ReservationItemRepository extends AbstractRepository implements ReservationItemRepositoryInterface
{
    private ReservationItemMapper $mapper;

    public function __construct(?PDO $db = null, ?ReservationItemMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new ReservationItemMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'reservation_items';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'reservation_id', 'product_id', 'quantity', 'unit_price', 'status', 'created_at'];
    }

    #[Override]
    public function findById(int $id): ?ReservationItemDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, reservation_id, product_id, quantity, unit_price, status, created_at
             FROM reservation_items
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->mapper->toDTO($row) : null;
    }

    private const ITEM_SELECT = '
        ri.id, ri.quantity, ri.status, ri.created_at,
        UNIX_TIMESTAMP(ri.created_at) AS created_ts,
        ri.reservation_id,
        p.id AS product_id, p.name AS product_name, p.station,
        p.prep_time, p.recipe_steps, p.ingredients_list, p.critical_check,
        t.code AS tracker_code,
        r.guest_count AS guests,
        GROUP_CONCAT(DISTINCT CONCAT_WS(\'|\', al.code, al.name, al.icon_color, al.severity)
            ORDER BY al.name SEPARATOR \';;\'
        ) AS allergen_data
    ';

    public function findByReservation(int $reservationId): array
    {
        $sql = '
            SELECT ri.id, ri.reservation_id, ri.product_id, ri.quantity,
                   ri.unit_price, ri.status, ri.created_at,
                   p.name AS product_name, p.station
            FROM reservation_items ri
            JOIN products p ON p.id = ri.product_id
            WHERE ri.reservation_id = :reservation_id
            ORDER BY ri.created_at
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['reservation_id' => $reservationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPendingByStation(int $cafeId, string $station): array
    {
        $sql = '
            SELECT ' . self::ITEM_SELECT . '
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            LEFT JOIN trackers t ON r.tracker_id = t.id
            LEFT JOIN product_allergens pa ON pa.product_id = p.id
            LEFT JOIN allergens al ON al.id = pa.allergen_id
            WHERE r.cafe_id = :cafe_id
              AND p.station = :station
              AND r.reservation_date = CURDATE()
              AND ri.status IN (\'pending\', \'kitchen\')
              AND r.status IN (\'confirmed\', \'active\')
            GROUP BY ri.id
            ORDER BY ri.created_at
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'station' => $station]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAllPendingByCafe(int $cafeId): array
    {
        $sql = '
            SELECT ' . self::ITEM_SELECT . '
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            LEFT JOIN trackers t ON r.tracker_id = t.id
            LEFT JOIN product_allergens pa ON pa.product_id = p.id
            LEFT JOIN allergens al ON al.id = pa.allergen_id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
              AND ri.status IN (\'pending\', \'kitchen\')
              AND r.status IN (\'confirmed\', \'active\')
            GROUP BY ri.id
            ORDER BY ri.created_at
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCompletedToday(int $cafeId): array
    {
        $sql = '
            SELECT ' . self::ITEM_SELECT . '
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            LEFT JOIN trackers t ON r.tracker_id = t.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
              AND ri.status = \'served\'
            ORDER BY ri.created_at DESC
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function add(int $reservationId, int $productId, int $quantity, float $unitPrice): int
    {
        $sql = '
            INSERT INTO reservation_items (reservation_id, product_id, quantity, unit_price)
            VALUES (:reservation_id, :product_id, :quantity, :unit_price)
        ';

        $this->getDb()->prepare($sql)->execute([
            'reservation_id' => $reservationId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE reservation_items SET status = :status WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function markReady(int $id): bool
    {
        return $this->updateStatus($id, ReservationItem::STATUS_READY);
    }

    public function markServed(int $id): bool
    {
        return $this->updateStatus($id, ReservationItem::STATUS_SERVED);
    }

    public function bumpTicket(int $reservationId): int
    {
        $sql = '
            UPDATE reservation_items
            SET status = :status
            WHERE reservation_id = :reservation_id
              AND status IN (\'pending\', \'kitchen\')
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([
            'reservation_id' => $reservationId,
            'status' => ReservationItem::STATUS_READY,
        ]);

        return $stmt->rowCount();
    }

    public function getDailyStats(int $cafeId): array
    {
        $sql = '
            SELECT
                COUNT(CASE WHEN ri.status = \'pending\' THEN 1 END) AS pending,
                COUNT(CASE WHEN ri.status = \'kitchen\' THEN 1 END) AS in_progress,
                COUNT(CASE WHEN ri.status = \'ready\'   THEN 1 END) AS ready,
                COUNT(CASE WHEN ri.status = \'served\'  THEN 1 END) AS served,
                AVG(
                    CASE WHEN ri.status IN (\'ready\', \'served\')
                    THEN TIMESTAMPDIFF(MINUTE, ri.created_at, NOW())
                    END
                ) AS avg_prep_time
            FROM reservation_items ri
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'pending' => 0,
            'in_progress' => 0,
            'ready' => 0,
            'served' => 0,
            'avg_prep_time' => null,
        ];
    }

    public function getEstimatedWaitTime(int $cafeId): int
    {
        $sql = '
            SELECT SUM(p.prep_time * ri.quantity) AS total_prep_time
            FROM reservation_items ri
            JOIN products p ON ri.product_id = p.id
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE r.cafe_id = :cafe_id
              AND r.reservation_date = CURDATE()
              AND ri.status IN (\'pending\', \'kitchen\')
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    #[Override]
    public function getReadyCountsByReservations(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
        $sql = "SELECT reservation_id, COUNT(*) AS cnt
                FROM reservation_items
                WHERE reservation_id IN ({$placeholders})
                  AND status = 'ready'
                GROUP BY reservation_id";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(\array_values($ids));

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(int) $row['reservation_id']] = (int) $row['cnt'];
        }

        return $result;
    }
}
