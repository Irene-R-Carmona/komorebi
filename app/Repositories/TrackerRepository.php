<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\TrackerDTO;
use App\Domain\Mappers\TrackerMapper;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use Override;
use PDO;
use RuntimeException;

/**
 * Repositorio de Trackers.
 *
 * Encapsula el acceso a datos de dispositivos de seguimiento de clientes.
 * Status lifecycle: available → in_use → available | lost.
 */
final class TrackerRepository extends AbstractRepository implements TrackerRepositoryInterface
{
    private const string STATUS_AVAILABLE = 'available';
    private const string STATUS_IN_USE = 'in_use';
    private const string STATUS_LOST = 'lost';

    private TrackerMapper $mapper;

    public function __construct(?PDO $db = null, ?TrackerMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new TrackerMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'trackers';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'cafe_id', 'code', 'type', 'status', 'last_assigned_at'];
    }

    #[Override]
    public function findById(int $id): ?TrackerDTO
    {
        $sql = 'SELECT t.id, t.cafe_id, t.code, t.type, t.status, c.name AS cafe_name
                FROM trackers t
                JOIN cafes c ON c.id = t.cafe_id
                WHERE t.id = :id LIMIT 1';

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute(['id' => $id]), $sql, ['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapper->toDTO($row) : null;
    }

    #[Override]
    public function findByCode(int $cafeId, string $code): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $sql = "SELECT $fields FROM trackers WHERE cafe_id = :cafe_id AND code = :code LIMIT 1";
        $params = ['cafe_id' => $cafeId, 'code' => \strtoupper(\trim($code))];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    #[Override]
    public function findByCafe(int $cafeId, ?string $status = null): array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $sql = "SELECT $fields FROM trackers WHERE cafe_id = :cafe_id";
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY code ASC';

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function findAvailable(int $cafeId): array
    {
        return $this->findByCafe($cafeId, self::STATUS_AVAILABLE);
    }

    #[Override]
    public function assign(int $id): bool
    {
        $sql = "UPDATE trackers SET status = :status, last_assigned_at = NOW()
                WHERE id = :id AND status = 'available'";
        $params = ['id' => $id, 'status' => self::STATUS_IN_USE];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Tracker no disponible.');
        }

        return true;
    }

    #[Override]
    public function release(int $id): bool
    {
        $sql = 'UPDATE trackers SET status = :status WHERE id = :id';
        $params = ['id' => $id, 'status' => self::STATUS_AVAILABLE];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    #[Override]
    public function markLost(int $id): bool
    {
        $sql = 'UPDATE trackers SET status = :status WHERE id = :id';
        $params = ['id' => $id, 'status' => self::STATUS_LOST];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    #[Override]
    public function getStats(int $cafeId): array
    {
        $sql = 'SELECT status, COUNT(*) as count FROM trackers WHERE cafe_id = :cafe_id GROUP BY status';
        $params = ['cafe_id' => $cafeId];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        $stats = [
            self::STATUS_AVAILABLE => 0,
            self::STATUS_IN_USE => 0,
            self::STATUS_LOST => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int) $row['count'];
        }

        $stats['total'] = \array_sum($stats);

        return $stats;
    }
}
