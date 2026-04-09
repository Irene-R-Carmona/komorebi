<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

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
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

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
     * Get all reservations for a user
     *
     * @param int $userId
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId): array;

    /**
     * Cancelar una reserva verificando pertenencia al usuario
     *
     * @param int $id ID de la reserva
     * @param int $userId ID del usuario (validación de pertenencia)
     * @return bool True si se canceló exitosamente
     */
    public function cancel(int $id, int $userId): bool;

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
     * @throws \DateMalformedStringException
     */
    public function getAvailableSlots(int $cafeId, string $date): array;
}
