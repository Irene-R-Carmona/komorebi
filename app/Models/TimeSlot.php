<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Result;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Modelo TimeSlot
 *
 * Representa un slot de tiempo con disponibilidad de plazas en un café específico.
 *
 * Responsabilidades:
 * - Consultar disponibilidad de slots
 * - Decrementar/incrementar plazas disponibles (operaciones atómicas)
 * - Bloquear/desbloquear slots administrativamente
 * - Generar slots programados para fechas futuras
 *
 * @package App\Models
 * @psalm-type SlotData = array{
 *     id: int,
 *     cafe_id: int,
 *     slot_date: string,
 *     slot_time: string,
 *     total_capacity: int,
 *     available_spots: int,
 *     reserved_spots: int,
 *     is_blocked: bool,
 *     blocked_reason: ?string,
 *     duration_minutes: int,
 *     created_at: string,
 *     updated_at: string
 * }
 */
final class TimeSlot
{
    private PDO $db;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Duración por defecto en minutos */
    public const int DEFAULT_DURATION_MINUTES = 60;

    /** Capacidad por defecto de un slot */
    public const int DEFAULT_CAPACITY = 20;

    /** Antelación mínima en horas para reservar */
    public const int MIN_ADVANCE_HOURS = 2;

    /** Antelación máxima en días para reservar */
    public const int MAX_ADVANCE_DAYS = 30;

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ─────────────────────────────────────────────────────────────
    // Consultas de disponibilidad
    // ─────────────────────────────────────────────────────────────

    /**
     * Buscar slots disponibles para un café en un rango de fechas
     *
     * @param integer $cafeId    ID del café
     * @param string  $startDate Fecha inicio (Y-m-d)
     * @param string  $endDate   Fecha fin (Y-m-d)
     * @param integer $minSpots  Plazas mínimas requeridas
     * @return Result
     */
    public function findAvailable(
        int $cafeId,
        string $startDate,
        string $endDate,
        int $minSpots = 1
    ): Result {
        try {
            $sql = <<<SQL
                    SELECT
                        id,
                        cafe_id,
                        slot_date,
                        slot_time,
                        total_capacity,
                        available_spots,
                        reserved_spots,
                        duration_minutes,
                        ROUND((reserved_spots / total_capacity) * 100, 2) AS occupancy_percentage,
                        CASE
                            WHEN available_spots = 0 THEN 'full'
                            WHEN available_spots <= 5 THEN 'limited'
                            ELSE 'available'
                        END AS availability_status
                    FROM time_slots
                    WHERE cafe_id = :cafe_id
                      AND slot_date BETWEEN :start_date AND :end_date
                      AND is_blocked = FALSE
                      AND available_spots >= :min_spots
                    ORDER BY slot_date ASC, slot_time ASC
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cafe_id' => $cafeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'min_spots' => $minSpots,
            ]);

            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Result::ok($slots);
        } catch (PDOException $e) {
            return Result::fail('Error al buscar slots disponibles: ' . $e->getMessage());
        }
    }

    /**
     * Buscar un slot específico por ID
     *
     * @param integer $slotId ID del slot
     * @return Result
     */
    public function findById(int $slotId): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        id,
                        cafe_id,
                        slot_date,
                        slot_time,
                        total_capacity,
                        available_spots,
                        reserved_spots,
                        is_blocked,
                        blocked_reason,
                        duration_minutes,
                        min_advance_hours,
                        max_advance_days,
                        created_at,
                        updated_at
                    FROM time_slots
                    WHERE id = :id
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $slotId]);

            $slot = $stmt->fetch(PDO::FETCH_ASSOC);

            return Result::ok($slot ?: null);
        } catch (PDOException $e) {
            return Result::fail('Error al buscar slot: ' . $e->getMessage());
        }
    }

    /**
     * Buscar un slot específico por ID usando SELECT ... FOR UPDATE
     * No inicia/gestiona transacciones: el caller debe envolver en transaction cuando sea necesario.
     *
     * @param int $slotId
     * @return Result
     */
    public function findByIdForUpdate(int $slotId): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        id,
                        cafe_id,
                        slot_date,
                        slot_time,
                        total_capacity,
                        available_spots,
                        reserved_spots,
                        is_blocked,
                        blocked_reason,
                        duration_minutes,
                        min_advance_hours,
                        max_advance_days,
                        created_at,
                        updated_at
                    FROM time_slots
                    WHERE id = :id
                    FOR UPDATE
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $slotId]);

            $slot = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$slot) {
                return Result::fail('Slot no encontrado');
            }

            return Result::ok($slot);
        } catch (PDOException $e) {
            return Result::fail('Error al buscar slot (for update): ' . $e->getMessage());
        }
    }

    /**
     * Verificar si un slot tiene disponibilidad
     *
     * @param integer $slotId        ID del slot
     * @param integer $requiredSpots Plazas requeridas
     * @return Result
     */
    public function hasAvailability(int $slotId, int $requiredSpots = 1): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        available_spots >= :required_spots AS has_space,
                        is_blocked
                    FROM time_slots
                    WHERE id = :id
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $slotId,
                'required_spots' => $requiredSpots,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return Result::fail('Slot no encontrado');
            }

            $isAvailable = (bool) $result['has_space'] && !$result['is_blocked'];

            return Result::ok($isAvailable);
        } catch (PDOException $e) {
            return Result::fail('Error al verificar disponibilidad: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Operaciones de capacidad (atómicas)
    // ─────────────────────────────────────────────────────────────

    /**
     * Decrementar plazas disponibles (al confirmar reserva)
     *
     * Operación atómica con SELECT FOR UPDATE para evitar race conditions.
     *
     * @param integer $slotId ID del slot
     * @param integer $spots  Número de plazas a reservar
     * @return Result
     */
    public function decrementSpots(int $slotId, int $spots = 1): Result
    {
        try {
            $this->db->beginTransaction();

            // Lock optimista: verificar disponibilidad
            $sql = <<<SQL
                    SELECT available_spots, total_capacity, is_blocked
                    FROM time_slots
                    WHERE id = :id
                    FOR UPDATE
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $slotId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$slot) {
                $this->db->rollBack();

                return Result::fail('Slot no encontrado');
            }

            if ($slot['is_blocked']) {
                $this->db->rollBack();

                return Result::fail('El slot está bloqueado administrativamente');
            }

            if ($slot['available_spots'] < $spots) {
                $this->db->rollBack();

                return Result::fail('No hay suficientes plazas disponibles');
            }

            // Actualizar capacidad
            $updateSql = <<<SQL
                    UPDATE time_slots
                    SET available_spots = available_spots - :spots,
                        reserved_spots = reserved_spots + :spots,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'id' => $slotId,
                'spots' => $spots,
            ]);

            $this->db->commit();

            return Result::ok(true);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return Result::fail('Error al decrementar plazas: ' . $e->getMessage());
        }
    }

    /**
     * Incrementar plazas disponibles (al cancelar reserva)
     *
     * Operación atómica para liberar plazas.
     *
     * @param integer $slotId ID del slot
     * @param integer $spots  Número de plazas a liberar
     * @return Result
     */
    public function incrementSpots(int $slotId, int $spots = 1): Result
    {
        try {
            $this->db->beginTransaction();

            // Verificar que no se exceda la capacidad total
            $sql = <<<SQL
                    SELECT total_capacity, available_spots
                    FROM time_slots
                    WHERE id = :id
                    FOR UPDATE
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $slotId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$slot) {
                $this->db->rollBack();

                return Result::fail('Slot no encontrado');
            }

            // Calcular nuevo valor sin exceder capacidad total
            $newAvailable = \min(
                $slot['available_spots'] + $spots,
                $slot['total_capacity']
            );

            $updateSql = <<<SQL
                    UPDATE time_slots
                    SET available_spots = :new_available,
                        reserved_spots = GREATEST(reserved_spots - :spots, 0),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'id' => $slotId,
                'new_available' => $newAvailable,
                'spots' => $spots,
            ]);

            $this->db->commit();

            return Result::ok(true);
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return Result::fail('Error al incrementar plazas: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Gestión administrativa
    // ─────────────────────────────────────────────────────────────

    /**
     * Bloquear un slot administrativamente
     *
     * @param integer      $slotId      ID del slot
     * @param string       $reason      Motivo del bloqueo
     * @param integer|null $adminUserId ID del administrador
     * @return Result
     */
    public function blockSlot(int $slotId, string $reason, ?int $adminUserId = null): Result
    {
        try {
            $sql = <<<SQL
                    UPDATE time_slots
                    SET is_blocked = TRUE,
                        blocked_reason = :reason,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $slotId,
                'reason' => $reason,
            ]);

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al bloquear slot: ' . $e->getMessage());
        }
    }

    /**
     * Desbloquear un slot administrativamente
     *
     * @param integer $slotId ID del slot
     * @return Result
     */
    public function unblockSlot(int $slotId): Result
    {
        try {
            $sql = <<<SQL
                    UPDATE time_slots
                    SET is_blocked = FALSE,
                        blocked_reason = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $slotId]);

            return Result::ok(true);
        } catch (PDOException $e) {
            return Result::fail('Error al desbloquear slot: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Creación y generación de slots
    // ─────────────────────────────────────────────────────────────

    /**
     * Crear un nuevo slot de tiempo
     *
     * @param array<string, mixed> $data Datos del slot
     * @return Result
     */
    public function create(array $data): Result
    {
        try {
            $sql = <<<SQL
                    INSERT INTO time_slots (
                        cafe_id,
                        slot_date,
                        slot_time,
                        total_capacity,
                        available_spots,
                        duration_minutes,
                        min_advance_hours,
                        max_advance_days,
                        created_by
                    ) VALUES (
                        :cafe_id,
                        :slot_date,
                        :slot_time,
                        :total_capacity,
                        :available_spots,
                        :duration_minutes,
                        :min_advance_hours,
                        :max_advance_days,
                        :created_by
                    )
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cafe_id' => $data['cafe_id'],
                'slot_date' => $data['slot_date'],
                'slot_time' => $data['slot_time'],
                'total_capacity' => $data['total_capacity'] ?? self::DEFAULT_CAPACITY,
                'available_spots' => $data['available_spots'] ?? $data['total_capacity'] ?? self::DEFAULT_CAPACITY,
                'duration_minutes' => $data['duration_minutes'] ?? self::DEFAULT_DURATION_MINUTES,
                'min_advance_hours' => $data['min_advance_hours'] ?? self::MIN_ADVANCE_HOURS,
                'max_advance_days' => $data['max_advance_days'] ?? self::MAX_ADVANCE_DAYS,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $slotId = (int) $this->db->lastInsertId();

            return Result::ok($slotId);
        } catch (PDOException $e) {
            return Result::fail('Error al crear slot: ' . $e->getMessage());
        }
    }

    /**
     * Generar slots automáticamente para un café en un rango de fechas
     *
     * @param integer            $cafeId    ID del café
     * @param string             $startDate Fecha inicio (Y-m-d)
     * @param string             $endDate   Fecha fin (Y-m-d)
     * @param array<int, string> $timeSlots Horarios (ejemplo: ['10:00', '11:00', '12:00'])
     * @param integer            $capacity  Capacidad por slot
     * @return Result
     */
    public function generateSlots(
        int $cafeId,
        string $startDate,
        string $endDate,
        array $timeSlots,
        int $capacity = self::DEFAULT_CAPACITY
    ): Result {
        try {
            $this->db->beginTransaction();

            $createdCount = 0;
            $start = new DateTimeImmutable($startDate);
            $end = new DateTimeImmutable($endDate);

            $currentDate = $start;
            while ($currentDate <= $end) {
                foreach ($timeSlots as $time) {
                    $result = $this->create([
                        'cafe_id' => $cafeId,
                        'slot_date' => $currentDate->format('Y-m-d'),
                        'slot_time' => $time . ':00',
                        'total_capacity' => $capacity,
                        'available_spots' => $capacity,
                    ]);

                    if ($result->isOk()) {
                        $createdCount++;
                    }
                }

                $currentDate = $currentDate->modify('+1 day');
            }

            $this->db->commit();

            return Result::ok($createdCount);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return Result::fail('Error al generar slots: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas y reportes
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtener estadísticas de ocupación de un café
     *
     * @param integer $cafeId    ID del café
     * @param string  $startDate Fecha inicio
     * @param string  $endDate   Fecha fin
     * @return Result
     */
    public function getOccupancyStats(int $cafeId, string $startDate, string $endDate): Result
    {
        try {
            $sql = <<<SQL
                    SELECT
                        COUNT(*) AS total_slots,
                        SUM(total_capacity) AS total_capacity_sum,
                        SUM(reserved_spots) AS total_reserved,
                        SUM(available_spots) AS total_available,
                        ROUND(AVG((reserved_spots / total_capacity) * 100), 2) AS avg_occupancy_percentage,
                        COUNT(CASE WHEN available_spots = 0 THEN 1 END) AS fully_booked_count,
                        COUNT(CASE WHEN is_blocked = TRUE THEN 1 END) AS blocked_count
                    FROM time_slots
                    WHERE cafe_id = :cafe_id
                      AND slot_date BETWEEN :start_date AND :end_date
                SQL;

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cafe_id' => $cafeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return Result::ok($stats ?: []);
        } catch (PDOException $e) {
            return Result::fail('Error al obtener estadísticas: ' . $e->getMessage());
        }
    }
}
