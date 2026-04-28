<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\ReservationDTO;
use DateMalformedStringException;

interface ReservationRepositoryInterface
{
    /**
     * Create a new reservation
     *
     * @param array<string, mixed> $data
     * @return int Created reservation ID
     */
    public function create(array $data): int;

    /**
     * Find reservation by ID
     *
     * @param int $id
     * @return ReservationDTO|null
     */
    public function findById(int $id): ?ReservationDTO;

    /**
     * Check if reservation exists for user and datetime
     *
     * @param int $userId
     * @param int $cafeId
     * @param string $date
     * @param string $time
     * @return bool
     */
    public function existsForUserAndDateTime(int $userId, int $cafeId, string $date, string $time): bool;

    /**
     * Update reservation
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete reservation
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Listar reservas de un café con filtros opcionales.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCafeWithFilters(int $cafeId, ?string $status = null, ?string $date = null, int $limit = 50): array;

    /**
     * Cancelar una reserva verificando pertenencia al usuario
     *
     * @param int $id ID de la reserva
     * @param int $userId ID del usuario (validación de pertenencia)
     * @return bool True si se canceló exitosamente
     */
    public function cancel(int $id, int $userId): bool;

    /**
     * Actualizar el estado de una reserva directamente (uso administrativo)
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Buscar reservas activas de un usuario
     *
     * @param int $userId
     * @return array<int, array<string, mixed>> Reservas con status pending, confirmed o active
     */
    public function findActiveByUser(int $userId): array;

    /**
     * Buscar reserva por ID incluyendo detalles del café
     *
     * @param int $id
     * @return array<string, mixed>|null Reserva con datos del café (nombre, ubicación, horarios)
     */
    public function findByIdWithCafeDetails(int $id): ?array;

    /**
     * Buscar reservas de un usuario con paginación y filtro de estado
     *
     * @param int $userId
     * @param string|null $status Filtro de estado (null = activas)
     * @param int $limit
     * @param int $offset
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function findByUser(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array;

    /**
     * Buscar próximas reservas de un usuario (futuras y confirmadas/pendientes)
     *
     * @param int $userId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function findUpcomingByUser(int $userId, int $limit = 5): array;

    /**
     * Obtener slots de tiempo disponibles para un café en una fecha
     *
     * @param int $cafeId
     * @param string $date Fecha en formato Y-m-d
     * @return array<int, array{time: string, available: int, bookable: bool}>
     * @throws DateMalformedStringException
     */
    public function getAvailableSlots(int $cafeId, string $date): array;

    /** @return array<int, array<string, mixed>> Reservas confirmed/active de hoy para un café */
    public function findByCafeAndDate(int $cafeId, string $date): array;

    /** @return array<int, array<string, mixed>> Grupos activos (status=active) en el café ahora */
    public function findActiveByCafe(int $cafeId): array;

    /** Registra check-in. $protocolData puede incluir tracker_id, zone_id, hygiene, briefing, shoes. */
    public function checkIn(int $id, array $protocolData = []): bool;

    /** Registra check-out. $paymentData puede incluir final_amount, payment_status, etc. */
    public function checkOut(int $id, array $paymentData = []): bool;

    /** Asigna tracker a reserva y lo marca 'in_use'. */
    public function assignTracker(int $reservationId, int $trackerId): bool;

    /** Marca un protocolo (hygiene|briefing|shoes) como completado. */
    public function completeProtocol(int $id, string $protocol): bool;

    /** @return array{total: int, completed: int, cancelled: int, no_shows: int, current_guests: int, total_revenue: float} */
    public function getDailyStats(int $cafeId, string $date): array;

    /**
     * Find reservation by ID restricted to a specific user (ownership check).
     *
     * @return array<string, mixed>|null
     */
    public function findByIdAndUser(int $id, int $userId): ?array;

    public function hasCompletedReservation(int $userId, int $cafeId): bool;

    /**
     * Find reservation with operational fields (protocol_hygiene, protocol_briefing, protocol_shoes, tracker_id, etc).
     *
     * @return array<string, mixed>|null
     */
    public function findWithOperationalData(int $id): ?array;
}
