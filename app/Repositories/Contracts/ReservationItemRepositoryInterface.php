<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\ReservationItemDTO;

interface ReservationItemRepositoryInterface
{
    public function findById(int $id): ?ReservationItemDTO;

    /** @return array<string, mixed> */
    public function findByReservation(int $reservationId): array;

    /** @return array<int, array<string, mixed>> Items pendientes de una estación para KDS */
    public function findPendingByStation(int $cafeId, string $station): array;

    /** @return array<int, array<string, mixed>> Todos los items activos hoy (pending + kitchen) */
    public function findAllPendingByCafe(int $cafeId): array;

    /** @return array<int, array<string, mixed>> Items servidos hoy */
    public function findCompletedToday(int $cafeId): array;

    public function add(int $reservationId, int $productId, int $quantity, float $unitPrice): int;

    public function updateStatus(int $id, string $status): bool;

    public function markReady(int $id): bool;

    public function markServed(int $id): bool;

    /** Marca como listos todos los items pending/kitchen de una reserva. Devuelve filas afectadas. */
    public function bumpTicket(int $reservationId): int;

    /** @return array{pending: int, in_progress: int, ready: int, served: int, avg_prep_time: float} */
    public function getDailyStats(int $cafeId): array;

    /** Suma de (prep_time × quantity) de items aún en cocina. */
    public function getEstimatedWaitTime(int $cafeId): int;

    /**
     * Número de ítems con status='ready' por reserva.
     *
     * @param  int[]           $ids
     * @return array<int, int> reservation_id => count
     */
    public function getReadyCountsByReservations(array $ids): array;

    /**
     * @param  int[] $ids
     * @return array<int, list<array{id: int, product_name: string, quantity: int}>>
     */
    public function getReadyItemsByReservations(array $ids): array;
}
