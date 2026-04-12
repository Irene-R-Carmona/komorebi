<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface KitchenServiceInterface
{
    public function getPendingByStation(int $cafeId): array;

    public function getPendingForStation(int $cafeId, string $station): array;

    public function getAllPending(int $cafeId): array;

    public function startPreparing(int $itemId): bool;

    public function markReady(int $itemId): bool;

    public function markServed(int $itemId): bool;

    public function bumpTicket(int $reservationId): int;

    public function getDailyStats(int $cafeId): array;

    public function getEstimatedWaitTime(int $cafeId): int;

    public function getCompletedToday(int $cafeId): array;
}
