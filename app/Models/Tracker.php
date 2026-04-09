<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\Traits\ValidatesData;
use PDO;
use RuntimeException;

/**
 * Modelo Tracker
 *
 * Gestiona los dispositivos de seguimiento de clientes.
 */
final class Tracker
{
    use ValidatesData;

    private PDO $db;

    // Constantes
    public const string STATUS_AVAILABLE = 'available';
    public const string STATUS_IN_USE = 'in_use';
    public const string STATUS_LOST = 'lost';

    public const string TYPE_TOKEN = 'token';
    public const string TYPE_BEEPER = 'beeper';
    public const string TYPE_NFC = 'nfc';
    public const string TYPE_QR = 'qr';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    private const SELECT_FIELDS = 'id, cafe_id, code, type, status, last_assigned_at';

    /**
     * Busca un tracker por ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.id, t.cafe_id, t.code, t.type, t.status, c.name AS cafe_name
             FROM trackers t
             JOIN cafes c ON c.id = t.cafe_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Busca un tracker por código en un café.
     */
    public function findByCode(int $cafeId, string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SELECT_FIELDS . ' FROM trackers
             WHERE cafe_id = :cafe_id AND code = :code LIMIT 1'
        );
        $stmt->execute(['cafe_id' => $cafeId, 'code' => \strtoupper(\trim($code))]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Obtiene trackers de un café.
     */
    public function findByCafe(int $cafeId, ?string $status = null): array
    {
        $sql = 'SELECT ' . self::SELECT_FIELDS . ' FROM trackers WHERE cafe_id = :cafe_id';
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY code ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene trackers disponibles.
     */
    public function findAvailable(int $cafeId): array
    {
        return $this->findByCafe($cafeId, self::STATUS_AVAILABLE);
    }

    /**
     * Asigna un tracker (marca como en uso).
     */
    public function assign(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE trackers SET status = :status
             WHERE id = :id AND status = 'available'"
        );
        $stmt->execute(['id' => $id, 'status' => self::STATUS_IN_USE]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Tracker no disponible.');
        }

        return true;
    }

    /**
     * Libera un tracker (marca como disponible).
     */
    public function release(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE trackers SET status = :status WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => self::STATUS_AVAILABLE]);
    }

    /**
     * Marca un tracker como perdido.
     */
    public function markLost(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE trackers SET status = :status WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => self::STATUS_LOST]);
    }

    /**
     * Obtiene estadísticas de trackers de un café.
     */
    public function getStats(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) as count
             FROM trackers WHERE cafe_id = :cafe_id
             GROUP BY status'
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $stats = [
            self::STATUS_AVAILABLE => 0,
            self::STATUS_IN_USE => 0,
            self::STATUS_LOST => 0,
        ];

        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = (int) $row['count'];
        }

        $stats['total'] = \array_sum($stats);

        return $stats;
    }
}
