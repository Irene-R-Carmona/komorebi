<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReservationServiceInterface
{
    public function create(array $data, ?CartServiceInterface $cart = null): Result;

    public function cancel(int $reservationId, int $userId): bool;

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
