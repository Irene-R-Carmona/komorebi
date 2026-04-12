<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReceptionServiceInterface
{
    public function getDashboard(int $cafeId): array;

    public function getPendingArrivals(int $cafeId): array;

    public function getActiveGroups(int $cafeId): array;

    public function processCheckin(int $reservationId, int $trackerId): Result;

    public function processCheckout(int $reservationId): Result;

    public function assignTracker(int $reservationId, int $trackerId): bool;

    public function getAvailableTrackers(int $cafeId): array;

    public function completeProtocol(int $reservationId, string $protocol): bool;

    public function getProtocolStatus(int $reservationId): Result;

    public function getCapacityInfo(int $cafeId): array;

    public function getDailyStats(int $cafeId, string $date): array;
}
