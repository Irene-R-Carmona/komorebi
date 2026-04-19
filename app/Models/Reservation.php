<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Result;
use App\Domain\Reservation\ReservationStateMachine;
use App\Exceptions\DateMalformedStringException;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo Reservation
 *
 * Gestiona reservas de clientes en los cafés.
 *
 * Estados del ciclo de vida:
 * - pending: Reserva creada, pendiente de confirmación
 * - confirmed: Confirmada, esperando llegada del cliente
 * - active: Cliente en el café (tras check-in)
 * - completed: Visita finalizada (tras check-out)
 * - cancelled: Cancelada por el cliente o staff
 * - no_show: Cliente no se presentó
 */
final class Reservation
{
    private PDO $db;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Estados posibles de una reserva */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_NO_SHOW = 'no_show';

    /** Estados que permiten cancelación */
    public const array CANCELLABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
    ];

    /** Estados "activos" (reserva vigente) */
    public const array ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_ACTIVE,
    ];

    /** Horas de antelación mínima para reservar */
    public const int MIN_ADVANCE_HOURS = 2;

    /** Días máximos de antelación para reservar */
    public const int MAX_ADVANCE_DAYS = 30;

    /** Campos para SELECT (evita SELECT *) */
    private const array SELECT_FIELDS = [
        'r.id',
        'r.user_id',
        'r.cafe_id',
        'r.pass_product_id',
        'r.pass_name',
        'r.pass_unit_price',
        'r.pass_duration_minutes',
        'r.tracker_id',
        'r.current_zone_id',
        'r.reservation_date',
        'r.reservation_time',
        'r.guest_count',
        'r.status',
        'r.check_in_at',
        'r.check_out_at',
        'r.protocol_hygiene',
        'r.protocol_briefing',
        'r.protocol_shoes',
        'r.final_amount',
        'r.payment_status',
        'r.payment_method',
        'r.payment_notes',
        'r.notes',
        'r.deleted_at',
        'r.created_at',
        'r.updated_at',
    ];

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca una reserva por ID.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE r.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : (array) $row;
    }

    /**
     * Busca una reserva verificando que pertenezca al usuario.
     * Útil para operaciones del cliente (cancelar, ver detalle).
     *
     * @return array<string,mixed>|null
     */
    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE r.id = :id AND r.user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : (array) $row;
    }

    // ─────────────────────────────────────────────────────────────
    // Listados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el historial de reservas de un usuario.
     *
     * @param integer     $userId
     * @param string|null $status Filtrar por estado (null = todos)
     * @param integer     $limit
     * @param integer     $offset
     * @return array{data: array, total: int}
     */
    public function findByUser(
        int $userId,
        ?string $status = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = ['r.user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($status !== null) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $whereClause = \implode(' AND ', $where);

        // Contar total
        $countSql = "SELECT COUNT(*) FROM reservations r WHERE $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Obtener datos
        $fields = \implode(', ', self::SELECT_FIELDS);
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
     * Obtiene las reservas próximas de un usuario.
     * (Reservas futuras confirmadas o pendientes)
     */
    public function findUpcomingByUser(int $userId, int $limit = 5): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $statuses = "'" . \implode("','", [self::STATUS_PENDING, self::STATUS_CONFIRMED]) . "'";

        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug, c.image_url AS cafe_image
                FROM reservations r
                JOIN cafes c ON c.id = r.cafe_id
                WHERE r.user_id = :user_id
                  AND r.status IN ($statuses)
                  AND (r.reservation_date > CURDATE()
                       OR (r.reservation_date = CURDATE() AND r.reservation_time >= CURTIME()))
                ORDER BY r.reservation_date , r.reservation_time
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene reservas de un café para una fecha (vista recepción).
     */
    public function findByCafeAndDate(int $cafeId, string $date): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields,
                       u.name AS user_name, u.email AS user_email,
                       t.code AS tracker_code
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN trackers t ON t.id = r.tracker_id
                WHERE r.cafe_id = :cafe_id
                  AND r.reservation_date = :date
                ORDER BY r.reservation_time ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene reservas activas de un café (clientes actualmente en el local).
     */
    public function findActiveByCafe(int $cafeId): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields,
                       u.name AS user_name,
                       t.code AS tracker_code,
                       cz.name AS zone_name
                FROM reservations r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN trackers t ON t.id = r.tracker_id
                LEFT JOIN cafe_zones cz ON cz.id = r.current_zone_id
                WHERE r.cafe_id = :cafe_id
                  AND r.status = :status
                ORDER BY r.check_in_at ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'cafe_id' => $cafeId,
            'status' => self::STATUS_ACTIVE,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─────────────────────────────────────────────────────────────
    // Creación
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea una nueva reserva.
     *
     * @param array{
     *     user_id: int,
     *     cafe_id: int,
     *     pass_product_id: int,
     *     pass_name: string,
     *     pass_unit_price: int,
     *     pass_duration_minutes: int,
     *     reservation_date: string,
     *     reservation_time: string,
     *     guests: int,
     *     comments?: string
     * } $data
     * @return Result
     * @throws \DateMalformedStringException
     */
    public function create(array $data): Result
    {
        // Validar datos requeridos
        $required = [
            'user_id',
            'cafe_id',
            'pass_product_id',
            'pass_name',
            'pass_unit_price',
            'pass_duration_minutes',
            'reservation_date',
            'reservation_time',
            'guests',
        ];

        try {
            $this->validateRequired($data, $required);
        } catch (RuntimeException $e) {
            return Result::fail('Datos requeridos faltantes: ' . $e->getMessage());
        }

        // Validar fecha y hora
        try {
            $this->validateDateTime($data['reservation_date'], $data['reservation_time']);
        } catch (RuntimeException $e) {
            return Result::fail($e->getMessage());
        }

        // Validar disponibilidad (sin slot si no se proporciona time_slot_id)
        if (!isset($data['time_slot_id'])) {
            if (
                !$this->checkAvailability(
                    (int) $data['cafe_id'],
                    $data['reservation_date'],
                    $data['reservation_time'],
                    (int) $data['guests']
                )
            ) {
                return Result::fail('No hay disponibilidad para la fecha y hora seleccionadas.');
            }
        }

        $sql = 'INSERT INTO reservations (
                    user_id, cafe_id, pass_product_id, pass_name,
                    pass_unit_price, pass_duration_minutes,
                    reservation_date, reservation_time, guest_count, notes, status, time_slot_id
                ) VALUES (
                    :user_id, :cafe_id, :pass_product_id, :pass_name,
                    :pass_unit_price, :pass_duration_minutes,
                    :reservation_date, :reservation_time, :guest_count, :notes, :status, :time_slot_id
                )';

        try {
            $this->db->prepare($sql)->execute([
                'user_id' => (int) $data['user_id'],
                'cafe_id' => (int) $data['cafe_id'],
                'pass_product_id' => (int) $data['pass_product_id'],
                'pass_name' => $data['pass_name'],
                'pass_unit_price' => (int) $data['pass_unit_price'],
                'pass_duration_minutes' => (int) $data['pass_duration_minutes'],
                'reservation_date' => $data['reservation_date'],
                'reservation_time' => $data['reservation_time'],
                'guest_count' => (int) $data['guests'],
                'notes' => $data['comments'] ?? null,
                'status' => self::STATUS_CONFIRMED,
                'time_slot_id' => $data['time_slot_id'] ?? null,
            ]);

            $reservationId = (int) $this->db->lastInsertId();

            return Result::ok([
                'reservation_id' => $reservationId,
                'time_slot_id' => $data['time_slot_id'] ?? null,
            ]);
        } catch (PDOException $e) {
            return Result::fail('Error al crear reserva: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Cambios de Estado
    // ─────────────────────────────────────────────────────────────

    /**
     * Cancela una reserva.
     * Solo si está en estado cancelable.
     *
     * @return Result
     */
    public function cancel(int $id, ?int $userId = null): Result
    {
        $reservation = $userId !== null
            ? $this->findByIdAndUser($id, $userId)
            : $this->findById($id);

        if (!$reservation) {
            return Result::fail('Reserva no encontrada.');
        }

        if (!ReservationStateMachine::isValidTransition($reservation['status'], self::STATUS_CANCELLED)) {
            return Result::fail('Esta reserva no puede ser cancelada.');
        }

        if ($this->updateStatus($id, self::STATUS_CANCELLED)) {
            return Result::ok(['reservation_id' => $id]);
        }

        return Result::fail('Error al cancelar la reserva.');
    }

    /**
     * Confirma una reserva pendiente
     * Solo se pueden confirmar reservas en estado 'pending'
     *
     * @param int $id
     * @return Result
     */
    public function confirm(int $id): Result
    {
        $reservation = $this->findById($id);

        if (!$reservation) {
            return Result::fail('Reserva no encontrada.');
        }

        if (!ReservationStateMachine::isValidTransition($reservation['status'], self::STATUS_CONFIRMED)) {
            return Result::fail('Solo se pueden confirmar reservas pendientes.');
        }

        if ($this->updateStatus($id, self::STATUS_CONFIRMED)) {
            return Result::ok(['reservation_id' => $id]);
        }

        return Result::fail('Error al confirmar la reserva.');
    }

    /**
     * Marca una reserva como no-show.
     * (Cliente no se presentó)
     */
    public function markNoShow(int $id): bool
    {
        $reservation = $this->findById($id);

        if (!$reservation || !ReservationStateMachine::isValidTransition($reservation['status'], self::STATUS_NO_SHOW)) {
            throw new RuntimeException('Solo se puede marcar no-show en reservas confirmadas.');
        }

        return $this->updateStatus($id, self::STATUS_NO_SHOW);
    }

    /**
     * Realiza el check-in de una reserva.
     */
    public function checkIn(int $id, ?int $trackerId = null): bool
    {
        $reservation = $this->findById($id);

        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }

        if (!ReservationStateMachine::isValidTransition($reservation['status'], self::STATUS_ACTIVE)) {
            throw new RuntimeException('Solo se puede hacer check-in en reservas confirmadas.');
        }

        $sql = 'UPDATE reservations
                SET status = :status,
                    check_in_at = NOW(),
                    tracker_id = :tracker_id
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'status' => self::STATUS_ACTIVE,
            'tracker_id' => $trackerId,
        ]);
    }

    /**
     * Realiza el check-out de una reserva.
     * Calcula el precio final.
     */
    public function checkOut(int $id): bool
    {
        $reservation = $this->findById($id);

        if (!$reservation) {
            throw new RuntimeException('Reserva no encontrada.');
        }

        if (!ReservationStateMachine::isValidTransition($reservation['status'], self::STATUS_COMPLETED)) {
            throw new RuntimeException('Solo se puede hacer check-out en reservas activas.');
        }

        // Calcular precio final (pase + items)
        $finalPrice = $this->calculateFinalPrice($id, $reservation);

        // Liberar tracker si tiene uno
        if ($reservation['tracker_id']) {
            $this->releaseTracker((int) $reservation['tracker_id']);
        }

        $sql = 'UPDATE reservations
                SET status = :status,
                    check_out_at = NOW(),
                    final_price = :final_price,
                    tracker_id = NULL
                WHERE id = :id';

        return $this->db->prepare($sql)->execute([
            'id' => $id,
            'status' => self::STATUS_COMPLETED,
            'final_price' => $finalPrice,
        ]);
    }

    /**
     * Actualiza el estado de una reserva.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $validStatuses = [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ];

        if (!\in_array($status, $validStatuses, true)) {
            throw new RuntimeException('Estado inválido.');
        }

        $stmt = $this->db->prepare(
            'UPDATE reservations SET status = :status WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    // ─────────────────────────────────────────────────────────────
    // Protocolos de Entrada
    // ─────────────────────────────────────────────────────────────

    /**
     * Marca un protocolo como completado.
     *
     * @param integer $id       ID de la reserva
     * @param string  $protocol 'hygiene', 'briefing', 'shoes'
     */
    public function completeProtocol(int $id, string $protocol): bool
    {
        $validProtocols = ['hygiene', 'briefing', 'shoes'];

        if (!\in_array($protocol, $validProtocols, true)) {
            throw new RuntimeException('Protocolo inválido.');
        }

        $column = "protocol_$protocol";

        $stmt = $this->db->prepare(
            "UPDATE reservations SET $column = TRUE WHERE id = :id"
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Verifica si todos los protocolos están completados.
     */
    public function allProtocolsCompleted(array $reservation): bool
    {
        return $reservation['protocol_hygiene']
            && $reservation['protocol_briefing']
            && $reservation['protocol_shoes'];
    }

    // ─────────────────────────────────────────────────────────────
    // Tracker y Zona
    // ─────────────────────────────────────────────────────────────

    /**
     * Asigna un tracker a la reserva.
     */
    public function assignTracker(int $reservationId, int $trackerId): bool
    {
        // Marcar tracker como en uso
        $stmt = $this->db->prepare(
            "UPDATE trackers SET status = 'in_use' WHERE id = :id AND status = 'available'"
        );
        $stmt->execute(['id' => $trackerId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Tracker no disponible.');
        }

        // Asignar a reserva
        $stmt = $this->db->prepare(
            'UPDATE reservations SET tracker_id = :tracker_id WHERE id = :id'
        );

        return $stmt->execute(['id' => $reservationId, 'tracker_id' => $trackerId]);
    }

    /**
     * Actualiza la zona actual del cliente.
     */
    public function updateZone(int $reservationId, int $zoneId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE reservations SET current_zone_id = :zone_id WHERE id = :id'
        );

        return $stmt->execute(['id' => $reservationId, 'zone_id' => $zoneId]);
    }

    /**
     * Libera un tracker (lo marca como disponible).
     */
    private function releaseTracker(int $trackerId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE trackers SET status = 'available' WHERE id = :id"
        );
        $stmt->execute(['id' => $trackerId]);
    }

    // ─────────────────────────────────────────────────────────────
    // Disponibilidad
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica disponibilidad para una reserva.
     *
     * @param integer $cafeId ID del café
     * @param string  $date   Fecha (Y-m-d)
     * @param string  $time   Hora (H:i)
     * @param integer $guests Número de personas
     */
    public function checkAvailability(int $cafeId, string $date, string $time, int $guests): bool
    {
        // Obtener capacidad del café
        $stmt = $this->db->prepare(
            'SELECT capacity_max, opening_time, closing_time, is_active, has_reservations
             FROM cafes WHERE id = :id'
        );
        $stmt->execute(['id' => $cafeId]);
        $cafe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cafe || !$cafe['is_active'] || !$cafe['has_reservations']) {
            return false;
        }

        // Verificar horario
        if ($time < $cafe['opening_time'] || $time >= $cafe['closing_time']) {
            return false;
        }

        // Contar ocupación en ese horario (reservas que se solapan)
        // SELECT FOR UPDATE: Lock pesimista para evitar race conditions
        $sql = "SELECT COALESCE(SUM(guest_count), 0) as total_guests
                FROM reservations
                WHERE cafe_id = :cafe_id
                  AND reservation_date = :date
                  AND reservation_time = :time
                  AND status IN ('confirmed', 'active')
                FOR UPDATE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'cafe_id' => $cafeId,
            'date' => $date,
            'time' => $time,
        ]);

        $currentOccupancy = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_guests'];

        return ($currentOccupancy + $guests) <= $cafe['capacity_max'];
    }

    /**
     * Obtiene los slots disponibles para un café en una fecha.
     *
     * @param int $cafeId
     * @param string $date
     * @return array<string, array{time: string, available: int}>
     * @throws DateMalformedStringException
     */
    /**
     * Obtiene slots disponibles para una fecha en un café.
     *
     * @param integer $cafeId ID del café
     * @param string  $date   Fecha en formato Y-m-d
     * @return list<array{time: string, available: int, bookable: bool}>
     * @throws \DateMalformedStringException
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

        // Generar slots (cada hora desde apertura hasta cierre)
        $slots = [];
        if (!isset($cafe['opening_time'], $cafe['closing_time']) || !\is_string($cafe['opening_time']) || !\is_string($cafe['closing_time'])) {
            return [];
        }

        try {
            $opening = new DateTimeImmutable($cafe['opening_time']);
            $closing = new DateTimeImmutable($cafe['closing_time']);
        } catch (Exception $e) {
            // Fecha/horario malformed: no generar slots
            return [];
        }

        $current = $opening;

        while ($current < $closing) {
            $timeStr = $current->format('H:i:s');
            $booked = $bookings[$timeStr] ?? 0;
            $capacityMax = isset($cafe['capacity_max']) ? (int) $cafe['capacity_max'] : 0;
            $available = \max(0, $capacityMax - $booked);

            $slots[] = [
                'time' => $current->format('H:i'),
                'available' => $available,
                'bookable' => $available > 0,
            ];

            $current = $current->modify('+1 hour');
        }

        return $slots;
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Cuenta reservas por estado para un usuario.
     *
     * @return array<string, int>
     */
    public function countByStatusForUser(int $userId): array
    {
        $sql = 'SELECT status, COUNT(*) as count
                FROM reservations
                WHERE user_id = :user_id
                GROUP BY status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $counts = \array_fill_keys([
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ], 0);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Cuenta reservas activas (estados activos del sistema).
     *
     * @param integer|null $cafeId Opcional: filtrar por café específico
     * @return integer Total de reservas activas
     */
    public function countActive(?int $cafeId = null): int
    {
        $where = [];
        $params = [];

        $statuses = "'" . \implode("','", self::ACTIVE_STATUSES) . "'";
        $where[] = "status IN ($statuses)";

        if ($cafeId !== null) {
            $where[] = 'cafe_id = :cafe_id';
            $params['cafe_id'] = $cafeId;
        }

        $whereClause = \implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM reservations WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Estadísticas diarias para un café.
     */
    public function getDailyStats(int $cafeId, string $date): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(IF(status = 'completed', 1, 0)) as completed,
                    SUM(IF(status = 'cancelled', 1, 0)) as cancelled,
                    SUM(IF(status = 'no_show', 1, 0)) as no_shows,
                    SUM(IF(status IN ('confirmed', 'active'), guest_count, 0)) as current_guests,
                    COALESCE(SUM(final_price), 0) as total_revenue
                FROM reservations
                WHERE cafe_id = :cafe_id AND reservation_date = :date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cafe_id' => $cafeId, 'date' => $date]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Calcula el precio final de una reserva.
     */
    private function calculateFinalPrice(int $reservationId, array $reservation): float
    {
        // Precio base del pase
        $basePrice = (float) $reservation['pass_unit_price'] * (int) $reservation['guests'];

        // Sumar items pedidos
        $sql = 'SELECT COALESCE(SUM(quantity * unit_price), 0) as items_total
                FROM reservation_items
                WHERE reservation_id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $reservationId]);
        $itemsTotal = (float) $stmt->fetchColumn();

        return $basePrice + $itemsTotal;
    }

    /**
     * Valida fecha y hora de reserva.
     * @param string $date
     * @param string $time
     * @throws \DateMalformedStringException
     */
    /**
     * Valida fecha y hora de reserva.
     * @throws RuntimeException
     */
    private function validateDateTime(string $date, string $time): void
    {
        $reservationDateTime = new DateTimeImmutable("$date $time");
        $now = new DateTimeImmutable();

        // Mínimo de antelación
        $minDateTime = $now->modify('+' . self::MIN_ADVANCE_HOURS . ' hours');
        if ($reservationDateTime < $minDateTime) {
            throw new RuntimeException(
                'Las reservas deben hacerse con al menos ' . self::MIN_ADVANCE_HOURS . ' horas de antelación.'
            );
        }

        // Máximo de antelación
        $maxDateTime = $now->modify('+' . self::MAX_ADVANCE_DAYS . ' days');
        if ($reservationDateTime > $maxDateTime) {
            throw new RuntimeException(
                'No se puede reservar con más de ' . self::MAX_ADVANCE_DAYS . ' días de antelación.'
            );
        }
    }

    /**
     * Valida campos requeridos.
     * @throws RuntimeException
     */
    private function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (\is_string($data[$field]) && \trim($data[$field]) === '')) {
                throw new RuntimeException("El campo '$field' es requerido.");
            }
        }
    }
}
