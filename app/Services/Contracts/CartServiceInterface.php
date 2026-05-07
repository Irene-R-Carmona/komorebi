<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface CartServiceInterface
{
    public function get(): array;

    public function getWithDetails(): array;

    public function isEmpty(): bool;

    public function getQuantity(int $productId): int;

    public function add(int $productId, int $quantity = 1): Result;

    public function setQuantity(int $productId, int $quantity): Result;

    public function remove(int $productId): Result;

    public function updateItem(int $productId, int $change): Result;

    public function clear(): void;

    public function getItemsForReservation(): array;

    public function transferToReservation(int $reservationId): bool;
}
