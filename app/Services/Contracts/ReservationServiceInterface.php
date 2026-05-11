<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReservationServiceInterface
{
    public function create(array $data, CartServiceInterface|null $cart = null): Result;

    public function cancel(int $reservationId, int $userId): Result;

    public function adminCancel(int $id): Result;

    public function adminConfirm(int $id): Result;

    /**
     * Cambiar estado de una reserva con justificación (uso manager)
     */
    public function managerUpdateStatus(int $id, string $newStatus, string $reason): Result;

    /**
     * Registrar devolución de una reserva cancelada (uso manager)
     */
    public function managerRecordRefund(int $id, int $amountCents, string $notes): Result;

    /**
     * @return array
     */
    public function getByUser(int $userId, ?string $status = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcoming(int $userId, int $limit = 5): array;

    /**
     * @param  array<int|string, int> $cartItems
     * @return array<int, array<string, mixed>>
     */
    public function enrichCartItems(array $cartItems): array;
}
