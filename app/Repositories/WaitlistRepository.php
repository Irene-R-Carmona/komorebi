<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\WaitlistEntryDTO;
use App\Domain\Mappers\WaitlistMapper;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use Override;
use PDO;

/**
 * Repositorio de Waitlist
 *
 * Encapsula el acceso a datos de listas de espera.
 */
final class WaitlistRepository extends AbstractRepository implements WaitlistRepositoryInterface
{
    private WaitlistMapper $mapper;

    public function __construct(?PDO $db = null, ?WaitlistMapper $mapper = null)
    {
        parent::__construct($db);
        $this->mapper = $mapper ?? new WaitlistMapper();
    }

    #[Override]
    protected function getTable(): string
    {
        return 'waitlist';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'user_id', 'time_slot_id', 'position', 'guest_count', 'status', 'token', 'expires_at', 'created_at'];
    }

    #[Override]
    public function findById(int $id): ?WaitlistEntryDTO
    {
        $sql = '
            SELECT w.*, ts.slot_date, ts.slot_time, c.name as cafe_name
            FROM waitlist w
            INNER JOIN time_slots ts ON w.time_slot_id = ts.id
            INNER JOIN cafes c ON ts.cafe_id = c.id
            WHERE w.id = :id
            LIMIT 1
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->mapper->toDTO($result) : null;
    }

    /**
     * Buscar entrada por token de confirmación
     */
    public function findByToken(string $token): ?WaitlistEntryDTO
    {
        $sql = '
            SELECT w.*, ts.slot_date, ts.slot_time, c.name as cafe_name
            FROM waitlist w
            INNER JOIN time_slots ts ON w.time_slot_id = ts.id
            INNER JOIN cafes c ON ts.cafe_id = c.id
            WHERE w.token = :token
            LIMIT 1
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['token' => $token]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->mapper->toDTO($result) : null;
    }

    /**
     * Obtener waitlists activas de un usuario
     */
    public function findActiveByUserId(int $userId): array
    {
        $sql = "
            SELECT w.*, ts.slot_date, ts.slot_time, c.name as cafe_name
            FROM waitlist w
            INNER JOIN time_slots ts ON w.time_slot_id = ts.id
            INNER JOIN cafes c ON ts.cafe_id = c.id
            WHERE w.user_id = :user_id
              AND w.status IN ('waiting', 'promoted')
            ORDER BY w.created_at DESC
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtener posición en la lista de espera
     */
    public function getPosition(int $timeSlotId, int $userId): ?int
    {
        $sql = "
            SELECT position
            FROM waitlist
            WHERE time_slot_id = :time_slot_id
              AND user_id = :user_id
              AND status = 'waiting'
            LIMIT 1
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['time_slot_id' => $timeSlotId, 'user_id' => $userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int) $result['position'] : null;
    }

    /**
     * Obtener siguiente persona en la lista de espera
     */
    public function getNextInLine(int $timeSlotId): ?array
    {
        $sql = "
            SELECT w.*, u.email as user_email, u.name as user_name
            FROM waitlist w
            INNER JOIN users u ON w.user_id = u.id
            WHERE w.time_slot_id = :time_slot_id
              AND w.status = 'waiting'
            ORDER BY w.position ASC, w.created_at ASC
            LIMIT 1
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['time_slot_id' => $timeSlotId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    #[Override]
    public function create(array $data): int
    {
        // Calcular posición automáticamente
        $stmtPosition = $this->getDb()->prepare('
            SELECT COALESCE(MAX(position), 0) + 1 as next_position
            FROM waitlist
            WHERE time_slot_id = :time_slot_id
        ');
        $stmtPosition->execute(['time_slot_id' => $data['time_slot_id']]);
        $positionResult = $stmtPosition->fetch(PDO::FETCH_ASSOC);
        $position = $positionResult['next_position'] ?? 1;

        $sql = '
            INSERT INTO waitlist (
                user_id, time_slot_id, position, guest_count,
                special_requests, status, token,
                expires_at, contact_email, contact_phone,
                created_at
            )
            VALUES (
                :user_id, :time_slot_id, :position, :guest_count,
                :special_requests, :status, :token,
                :expires_at, :contact_email, :contact_phone,
                NOW()
            )
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'time_slot_id' => $data['time_slot_id'],
            'position' => $position,
            'guest_count' => $data['guest_count'] ?? 1,
            'special_requests' => $data['special_requests'] ?? null,
            'status' => $data['status'] ?? 'waiting',
            'token' => $data['confirmation_token'] ?? ($data['token'] ?? \bin2hex(\random_bytes(16))),
            'expires_at' => $data['token_expires_at'] ?? ($data['expires_at'] ?? null),
            'contact_email' => $data['contact_email'] ?? '',
            'contact_phone' => $data['contact_phone'] ?? null,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    /**
     * Actualizar estado de waitlist con datos adicionales
     */
    public function updateStatusWithData(int $id, string $status, array $additionalData = []): bool
    {
        $fields = ['status = :status', 'updated_at = NOW()'];
        $params = ['id' => $id, 'status' => $status];

        if (isset($additionalData['expires_at'])) {
            $fields[] = 'expires_at = :expires_at';
            $params['expires_at'] = $additionalData['expires_at'];
        }

        if (isset($additionalData['reservation_id'])) {
            $fields[] = 'reservation_id = :reservation_id';
            $params['reservation_id'] = $additionalData['reservation_id'];
        }

        $sql = 'UPDATE waitlist SET ' . \implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Reordenar posiciones después de promoción/cancelación
     */
    public function reorderPositions(int $timeSlotId, int $fromPosition): bool
    {
        $sql = "
            UPDATE waitlist
            SET position = position - 1,
                updated_at = NOW()
            WHERE time_slot_id = :time_slot_id
              AND position > :from_position
              AND status = 'waiting'
        ";

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute([
            'time_slot_id' => $timeSlotId,
            'from_position' => $fromPosition,
        ]);
    }

    /**
     * Verificar si usuario ya está en waitlist de un time slot
     */
    public function userInWaitlist(int $userId, int $timeSlotId): bool
    {
        $sql = "
            SELECT 1
            FROM waitlist
            WHERE user_id = :user_id
              AND time_slot_id = :time_slot_id
              AND status IN ('waiting', 'promoted')
            LIMIT 1
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'time_slot_id' => $timeSlotId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Actualizar estado de waitlist
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = 'UPDATE waitlist SET status = :status, updated_at = NOW() WHERE id = :id';

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Actualizar token y expiración
     */
    public function updateToken(int $id, string $token, string $expiresAt): bool
    {
        $sql = '
            UPDATE waitlist
            SET token = :token,
                expires_at = :expires_at,
                updated_at = NOW()
            WHERE id = :id
        ';

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Cancelar waitlist
     */
    public function cancel(int $id, int $userId): bool
    {
        $sql = "
            UPDATE waitlist
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = :id AND user_id = :user_id AND status = 'waiting'
        ";

        $stmt = $this->getDb()->prepare($sql);

        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /**
     * Expirar tokens vencidos
     */
    public function expireTokens(): int
    {
        $sql = "
            UPDATE waitlist
            SET status = 'expired', updated_at = NOW()
            WHERE status = 'promoted'
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
        ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Obtener historial de waitlists de un usuario
     */
    public function getUserHistory(int $userId, int $limit = 10): array
    {
        $sql = '
            SELECT w.*, ts.slot_date, ts.slot_time, c.name as cafe_name
            FROM waitlist w
            INNER JOIN time_slots ts ON w.time_slot_id = ts.id
            INNER JOIN cafes c ON ts.cafe_id = c.id
            WHERE w.user_id = :user_id
            ORDER BY w.created_at DESC
            LIMIT :limit
        ';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAllWithDetails(array $filters = []): array
    {
        $sql = 'SELECT w.id, w.time_slot_id, w.user_id, w.position, w.status,
                       w.guest_count, w.special_requests, w.created_at,
                       w.notified_at, w.expires_at,
                       ts.slot_date, ts.slot_time, ts.cafe_id,
                       c.name AS cafe_name,
                       u.name AS user_name, u.email AS user_email
                FROM waitlist w
                INNER JOIN time_slots ts ON w.time_slot_id = ts.id
                INNER JOIN cafes c ON ts.cafe_id = c.id
                INNER JOIN users u ON w.user_id = u.id
                WHERE 1=1';

        $params = [];

        if (!empty($filters['cafe_id'])) {
            $sql .= ' AND ts.cafe_id = :cafe_id';
            $params['cafe_id'] = $filters['cafe_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND w.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $sql .= ' AND ts.slot_date = :date';
            $params['date'] = $filters['date'];
        }

        $sql .= ' ORDER BY ts.slot_date, ts.slot_time, w.position';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSummaryByStatus(): array
    {
        $stmt = $this->getDb()->query(
            "SELECT status, COUNT(*) AS count
             FROM waitlist
             WHERE status IN ('waiting','notified','confirmed','cancelled','expired')
             GROUP BY status"
        );

        $summary = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[$row['status']] = (int) $row['count'];
        }

        return $summary;
    }

    public function cancelById(int $id): bool
    {
        $stmt = $this->getDb()->prepare(
            "UPDATE waitlist SET status = 'cancelled' WHERE id = :id"
        );

        return $stmt->execute(['id' => $id]);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->getDb()->prepare('SELECT * FROM waitlist WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countByTimeSlotAndStatus(int $timeSlotId, string $status): int
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM waitlist WHERE time_slot_id = ? AND status = ?');
        $stmt->execute([$timeSlotId, $status]);

        return (int) $stmt->fetchColumn();
    }
}
