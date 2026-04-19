<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Result;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Modelo Waitlist
 *
 * Gestiona la lista de espera FIFO cuando un slot está completo.
 *
 * Responsabilidades:
 * - Añadir usuarios a lista de espera
 * - Obtener siguiente en cola para notificar
 * - Confirmar acceso mediante token único
 * - Expirar registros automáticamente
 * - Gestionar posiciones en cola (FIFO)
 *
 * Estados del ciclo de vida:
 * - waiting: En cola, esperando disponibilidad
 * - notified: Notificado de disponibilidad, esperando confirmación
 * - confirmed: Confirmó y se creó su reserva
 * - expired: Expiró sin respuesta
 * - cancelled: Canceló voluntariamente
 *
 * @package App\Models
 */
final class Waitlist
{
    private PDO $db;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Estados posibles */
    public const string STATUS_WAITING = 'waiting';
    public const string STATUS_NOTIFIED = 'notified';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_EXPIRED = 'expired';
    public const string STATUS_CANCELLED = 'cancelled';

    /** Tiempo por defecto para responder notificación (minutos) */
    public const int DEFAULT_RESPONSE_TIMEOUT = 15;

    /** Longitud del token de confirmación */
    private const int TOKEN_LENGTH = 32;

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ─────────────────────────────────────────────────────────────
    // Añadir a lista de espera
    // ─────────────────────────────────────────────────────────────

    /**
     * Añadir un usuario a la lista de espera de un slot
     *
     * @param integer              $timeSlotId ID del slot
     * @param integer              $userId     ID del usuario
     * @param array<string, mixed> $data       Datos adicionales (email, phone, guest_count, etc.)
     * @return Result
     */
    public function addToWaitlist(int $timeSlotId, int $userId, array $data): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            // Verificar si el usuario ya está en la waitlist para este slot
            $checkSql = <<<SQL
                    SELECT id, status
                    FROM waitlist
                    WHERE time_slot_id = :time_slot_id
                      AND user_id = :user_id
                      AND status IN ('waiting', 'notified')
                SQL;

            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([
                'time_slot_id' => $timeSlotId,
                'user_id' => $userId,
            ]);

            if ($checkStmt->fetch()) {
                $this->db->rollBack();

                return Result::fail('Ya estás en la lista de espera para este horario');
            }

            // Obtener siguiente posición disponible
            $positionSql = <<<SQL
                    SELECT COALESCE(MAX(position), 0) + 1 AS next_position
                    FROM waitlist
                    WHERE time_slot_id = :time_slot_id
                SQL;

            $positionStmt = $this->db->prepare($positionSql);
            $positionStmt->execute(['time_slot_id' => $timeSlotId]);
            $position = (int) $positionStmt->fetchColumn();

            // Generar token único
            $token = $this->generateToken();

            // Calcular tiempo de expiración (por defecto: 24 horas desde ahora)
            $expiresAt = new DateTimeImmutable('+24 hours');

            // Insertar en waitlist
            $insertSql = <<<SQL
                    INSERT INTO waitlist (
                        time_slot_id,
                        user_id,
                        position,
                        token,
                        expires_at,
                        response_timeout_minutes,
                        status,
                        contact_email,
                        contact_phone,
                        guest_count,
                        special_requests
                    ) VALUES (
                        :time_slot_id,
                        :user_id,
                        :position,
                        :token,
                        :expires_at,
                        :response_timeout_minutes,
                        :status,
                        :contact_email,
                        :contact_phone,
                        :guest_count,
                        :special_requests
                    )
                SQL;

            $insertStmt = $this->db->prepare($insertSql);
            $insertStmt->execute([
                'time_slot_id' => $timeSlotId,
                'user_id' => $userId,
                'position' => $position,
                'token' => $token,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'response_timeout_minutes' => $data['response_timeout_minutes'] ?? self::DEFAULT_RESPONSE_TIMEOUT,
                'status' => self::STATUS_WAITING,
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'guest_count' => $data['guest_count'] ?? 1,
                'special_requests' => $data['special_requests'] ?? null,
            ]);

            $waitlistId = (int) $this->db->lastInsertId();

            if ($startedTransaction) {
                $this->db->commit();
            }

            return Result::ok([
                'id' => $waitlistId,
                'token' => $token,
                'position' => $position,
            ]);
        } catch (PDOException $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return Result::fail('Error al añadir a lista de espera: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Obtener siguiente en cola
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener el siguiente usuario en cola para un slot (menor posición)
     *
     * @param integer $timeSlotId ID del slot
     * @return Result
     */
    public function getNextInQueue(int $timeSlotId): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        w.id,
                        w.time_slot_id,
                        w.user_id,
                        w.position,
                        w.token,
                        w.expires_at,
                        w.response_timeout_minutes,
                        w.status,
                        w.contact_email,
                        w.contact_phone,
                        w.guest_count,
                        w.special_requests,
                        w.created_at,
                        u.name AS user_name,
                        u.email AS user_email
                    FROM waitlist w
                    INNER JOIN users u ON w.user_id = u.id
                    WHERE w.time_slot_id = :time_slot_id
                      AND w.status = :status
                    ORDER BY w.position ASC
                    LIMIT 1
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'time_slot_id' => $timeSlotId,
                'status' => self::STATUS_WAITING,
            ]);

            $next = $stmt->fetch(PDO::FETCH_ASSOC);

            return Result::ok($next ?: null);
        } catch (PDOException $e) {
            return Result::fail('Error al obtener siguiente en cola: ' . $e->getMessage());
        }
    }

    /**
     * Marcar el siguiente en cola como notificado
     *
     * @param integer $waitlistId ID del registro en waitlist
     * @return Result
     */
    public function markAsNotified(int $waitlistId): Result
    {
        try {
            // Calcular nueva fecha de expiración basada en response_timeout_minutes
            $sql = <<<SQL
                    UPDATE waitlist
                    SET status = :status,
                        notified_at = CURRENT_TIMESTAMP,
                        expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL response_timeout_minutes MINUTE),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                      AND status = :current_status
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $waitlistId,
                'status' => self::STATUS_NOTIFIED,
                'current_status' => self::STATUS_WAITING,
            ]);

            $rowsAffected = $stmt->rowCount();

            if ($rowsAffected === 0) {
                return Result::fail('No se pudo marcar como notificado');
            }

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al marcar como notificado: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar el estado y campos adicionales de un registro de waitlist
     *
     * @param integer $waitlistId
     * @param string  $status
     * @param array   $fields   Campos adicionales a setear (expires_at, reservation_id, etc.)
     * @return Result
     */
    public function updateStatus(int $waitlistId, string $status, array $fields = []): Result
    {
        try {
            // Detectar driver para timestamp correcto
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $timestamp = $driver === 'sqlite' ? "datetime('now')" : 'CURRENT_TIMESTAMP';

            $set = ['status = :status', "updated_at = {$timestamp}"];
            $params = ['id' => $waitlistId, 'status' => $status];

            foreach ($fields as $k => $v) {
                $set[] = "{$k} = :{$k}";
                $params[$k] = $v;
            }

            $sql = 'UPDATE waitlist SET ' . \implode(', ', $set) . ' WHERE id = :id';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                return Result::fail('No se pudo actualizar el estado');
            }

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al actualizar estado: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Confirmación mediante token
    // ─────────────────────────────────────────────────────────────

    /**
     * Confirmar acceso mediante token único
     *
     * @param string $token Token de confirmación
     * @return Result
     */
    public function confirmByToken(string $token): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            // Buscar registro por token
            $sql = <<<SQL
                    SELECT
                        id,
                        time_slot_id,
                        user_id,
                        guest_count,
                        status,
                        expires_at
                    FROM waitlist
                    WHERE token = :token
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['token' => $token]);
            $waitlist = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$waitlist) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Token no válido');
            }

            // Verificar que esté en estado notified
            if ($waitlist['status'] !== self::STATUS_NOTIFIED) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Este enlace ya no es válido');
            }

            // Verificar que no haya expirado
            $expiresAt = new DateTimeImmutable($waitlist['expires_at']);
            $now = new DateTimeImmutable();

            if ($now > $expiresAt) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('El tiempo de confirmación ha expirado');
            }

            // Marcar como confirmado
            $updateSql = <<<SQL
                    UPDATE waitlist
                    SET status = :status,
                        confirmed_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'id' => $waitlist['id'],
                'status' => self::STATUS_CONFIRMED,
            ]);

            if ($startedTransaction) {
                $this->db->commit();
            }

            return Result::ok([
                'waitlist_id' => (int) $waitlist['id'],
                'time_slot_id' => (int) $waitlist['time_slot_id'],
                'user_id' => (int) $waitlist['user_id'],
                'guest_count' => (int) $waitlist['guest_count'],
            ]);
        } catch (PDOException $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return Result::fail('Error al confirmar: ' . $e->getMessage());
        }
    }

    /**
     * Vincular una reserva a un registro de waitlist confirmado
     *
     * @param integer $waitlistId    ID del waitlist
     * @param integer $reservationId ID de la reserva creada
     * @return Result
     */
    public function linkReservation(int $waitlistId, int $reservationId): Result
    {
        try {
            $sql = <<<SQL
                    UPDATE waitlist
                    SET reservation_id = :reservation_id,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $waitlistId,
                'reservation_id' => $reservationId,
            ]);

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al vincular reserva: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Cancelación y limpieza
    // ─────────────────────────────────────────────────────────────

    /**
     * Cancelar una entrada de waitlist
     *
     * @param integer $waitlistId ID del waitlist
     * @param integer $userId     ID del usuario (verificación)
     * @return Result
     */
    public function cancel(int $waitlistId, int $userId): Result
    {
        try {
            $sql = <<<SQL
                    UPDATE waitlist
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                      AND user_id = :user_id
                      AND status IN ('waiting', 'notified')
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $waitlistId,
                'user_id' => $userId,
                'status' => self::STATUS_CANCELLED,
            ]);

            $rowsAffected = $stmt->rowCount();

            if ($rowsAffected === 0) {
                return Result::fail('No se pudo cancelar la entrada');
            }

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al cancelar: ' . $e->getMessage());
        }
    }

    /**
     * Expirar registros que superaron su tiempo límite
     *
     * Esta función es llamada por el evento MySQL automático,
     * pero también puede invocarse manualmente.
     *
     * @return Result
     */
    public function expireOldEntries(): Result
    {
        try {
            $sql = <<<SQL
                    UPDATE waitlist
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE status IN ('waiting', 'notified')
                      AND expires_at < CURRENT_TIMESTAMP
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['status' => self::STATUS_EXPIRED]);

            $expiredCount = $stmt->rowCount();

            return Result::ok($expiredCount);
        } catch (PDOException $e) {
            return Result::fail('Error al expirar entradas: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Consultas y estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Buscar entrada de waitlist por token
     *
     * @param string $token Token de confirmación
     * @return array<string, mixed>|null Datos del waitlist o null si no existe
     */
    public function findByToken(string $token): ?array
    {
        try {
            $sql = <<<SQL
                    SELECT
                        w.id,
                        w.time_slot_id,
                        w.user_id,
                        w.position,
                        w.token,
                        w.notified_at,
                        w.confirmed_at,
                        w.expires_at,
                        w.response_timeout_minutes,
                        w.status,
                        w.contact_email,
                        w.contact_phone,
                        w.guest_count,
                        w.special_requests,
                        w.created_at,
                        u.name AS user_name,
                        u.email AS user_email
                    FROM waitlist w
                    INNER JOIN users u ON w.user_id = u.id
                    WHERE w.token = :token
                    LIMIT 1
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['token' => $token]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Buscar entrada de waitlist por ID
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        try {
            $sql = <<<'SQL'
                    SELECT
                        w.id,
                        w.time_slot_id,
                        w.user_id,
                        w.position,
                        w.token,
                        w.notified_at,
                        w.confirmed_at,
                        w.expires_at,
                        w.response_timeout_minutes,
                        w.status,
                        w.contact_email,
                        w.contact_phone,
                        w.guest_count,
                        w.special_requests,
                        w.created_at,
                        u.name AS user_name,
                        u.email AS user_email
                    FROM waitlist w
                    INNER JOIN users u ON w.user_id = u.id
                    WHERE w.id = :id
                    LIMIT 1
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Obtener entradas de waitlist de un usuario
     *
     * @param integer $userId     ID del usuario
     * @param boolean $activeOnly Solo entradas activas (waiting, notified)
     * @return Result
     */
    public function getByUser(int $userId, bool $activeOnly = true): Result
    {
        try {
            $statusFilter = $activeOnly
                ? "AND w.status IN ('waiting', 'notified')"
                : '';

            $sql = <<<SQL
                    SELECT
                        w.id,
                        w.time_slot_id,
                        w.position,
                        w.status,
                        w.expires_at,
                        w.created_at,
                        w.guest_count,
                        ts.slot_date,
                        ts.slot_time,
                        c.name AS cafe_name,
                        c.slug AS cafe_slug
                    FROM waitlist w
                    INNER JOIN time_slots ts ON w.time_slot_id = ts.id
                    INNER JOIN cafes c ON ts.cafe_id = c.id
                    WHERE w.user_id = :user_id
                      {$statusFilter}
                    ORDER BY w.created_at DESC
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);

            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Result::ok($entries);
        } catch (PDOException $e) {
            return Result::fail('Error al obtener waitlist: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de waitlist para un slot
     *
     * @param integer $timeSlotId ID del slot
     * @return Result
     */
    public function getStatsForSlot(int $timeSlotId): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        COUNT(*) AS total,
                        COUNT(CASE WHEN status = 'waiting' THEN 1 END) AS waiting,
                        COUNT(CASE WHEN status = 'notified' THEN 1 END) AS notified,
                        AVG(position) AS avg_position,
                        MIN(position) AS min_position,
                        MAX(position) AS max_position
                    FROM waitlist
                    WHERE time_slot_id = :time_slot_id
                      AND status IN ('waiting', 'notified')
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['time_slot_id' => $timeSlotId]);

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return Result::ok($stats ?: []);
        } catch (PDOException $e) {
            return Result::fail('Error al obtener estadísticas: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Utilidades privadas
    // ─────────────────────────────────────────────────────────────

    /**
     * Generar token aleatorio seguro
     *
     * @return string Token hexadecimal de 64 caracteres
     */
    private function generateToken(): string
    {
        return \bin2hex(\random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Obtener todas las waitlists con detalles (para admin)
     *
     * @param array $filters Filtros opcionales (cafe_id, status, date)
     * @return array
     */
    public function getAllWithDetails(array $filters = []): array
    {
        $sql = <<<SQL
            SELECT
                w.id,
                w.time_slot_id,
                w.user_id,
                w.position,
                w.status,
                w.guest_count,
                w.special_requests,
                w.created_at,
                w.notified_at,
                w.expires_at,
                ts.slot_date,
                ts.slot_time,
                ts.cafe_id,
                c.name AS cafe_name,
                u.name AS user_name,
                u.email AS user_email
            FROM waitlist w
            INNER JOIN time_slots ts ON w.time_slot_id = ts.id
            INNER JOIN cafes c ON ts.cafe_id = c.id
            INNER JOIN users u ON w.user_id = u.id
            WHERE 1=1
            SQL;

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de waitlists por estado
     *
     * @return array
     */
    public function getSummaryByStatus(): array
    {
        $sql = <<<SQL
            SELECT
                status,
                COUNT(*) AS count
            FROM waitlist
            WHERE status IN ('waiting', 'notified', 'confirmed', 'cancelled', 'expired')
            GROUP BY status
            SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $summary = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary[$row['status']] = (int) $row['count'];
        }

        return $summary;
    }

    /**
     * Reordenar posiciones tras eliminar/confirmar/expirar una entrada
     * Decrementa en 1 todas las posiciones mayores a la removida
     * @param int $timeSlotId
     * @param int $removedPosition
     * @return bool
     */
    public function reorderPositions(int $timeSlotId, int $removedPosition): bool
    {
        try {
            $sql = <<<'SQL'
                    UPDATE waitlist
                    SET position = position - 1, updated_at = CURRENT_TIMESTAMP
                    WHERE time_slot_id = :time_slot_id
                      AND position > :pos
                      AND status IN ('waiting', 'notified')
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['time_slot_id' => $timeSlotId, 'pos' => $removedPosition]);

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtener entradas expiradas (status notified o waiting con expires_at pasado)
     * @return array<int, array<string,mixed>>
     */
    public function getExpiredEntries(): array
    {
        try {
            $sql = <<<'SQL'
                    SELECT * FROM waitlist
                    WHERE status IN ('waiting', 'notified')
                      AND expires_at < CURRENT_TIMESTAMP
                SQL;

            $stmt = $this->db->query($sql);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtener la posición de un usuario en la waitlist de un slot
     * @param int $userId
     * @param int $timeSlotId
     * @return int|null
     */
    public function getUserPosition(int $userId, int $timeSlotId): ?int
    {
        try {
            $sql = 'SELECT position FROM waitlist WHERE user_id = :user_id AND time_slot_id = :time_slot_id AND status IN (\'waiting\', \'notified\') LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'time_slot_id' => $timeSlotId]);
            $pos = $stmt->fetchColumn();

            return $pos === false ? null : (int) $pos;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Contar personas en espera para un slot
     * @param int $timeSlotId
     * @return int
     */
    public function countWaitingForSlot(int $timeSlotId): int
    {
        try {
            $sql = 'SELECT COUNT(*) FROM waitlist WHERE time_slot_id = :time_slot_id AND status IN (\'waiting\', \'notified\')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['time_slot_id' => $timeSlotId]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Obtener historial de waitlist de un usuario
     * @param int $userId
     * @param int $limit
     * @return array<int, array<string,mixed>>
     */
    public function getUserHistory(int $userId, int $limit = 10): array
    {
        try {
            $sql = <<<'SQL'
                    SELECT
                        w.id,
                        w.time_slot_id,
                        w.position,
                        w.status,
                        w.guest_count,
                        w.created_at,
                        ts.slot_date,
                        ts.slot_time,
                        c.name AS cafe_name
                    FROM waitlist w
                    INNER JOIN time_slots ts ON w.time_slot_id = ts.id
                    INNER JOIN cafes c ON ts.cafe_id = c.id
                    WHERE w.user_id = :user_id
                    ORDER BY w.created_at DESC
                    LIMIT :limit
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Cancelar una waitlist por ID (por admin)
     *
     * @param integer $id
     * @return boolean
     */
    public function cancelById(int $id): bool
    {
        $sql = <<<SQL
            UPDATE waitlist
            SET status = 'cancelled'
            WHERE id = :id
            SQL;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }
}
