<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface CartServiceInterface
{
    public function get(): array;

    public function getWithDetails(): array;

    public function isEmpty(): bool;

    public function getQuantity(int $productId): int;

    public function add(int $productId, int $quantity = 1): array;

    public function setQuantity(int $productId, int $quantity): array;

    public function remove(int $productId): array;

    public function updateItem(int $productId, int $change): array;

    public function clear(): void;

    public function getItemsForReservation(): array;

    public function transferToReservation(int $reservationId): bool;
}
