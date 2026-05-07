<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface DashboardServiceInterface
{
    public function getDashboardMetrics(int $cafeId): array;

    public function getReservationsToday(int $cafeId): int;

    public function getRevenueToday(int $cafeId): float;

    public function getActiveStaffCount(int $cafeId): int;

    public function getAnimalsCount(int $cafeId): int;

    public function getWeeklyRevenue(int $cafeId): array;

    public function getMonthlyReservationsCount(int $cafeId): int;

    public function getAverageRating(int $cafeId): float;

    public function getPendingReservationsCount(int $cafeId): int;

    public function getTopAnimals(int $cafeId, int $limit = 5): array;

    public function getReservationStatusDistribution(int $cafeId): array;

    public function getReservationReport(
        int $cafeId,
        ?string $from = null,
        ?string $to = null,
        ?int $limit = 100,
    ): array;
}
