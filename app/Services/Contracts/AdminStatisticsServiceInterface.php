<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface AdminStatisticsServiceInterface
{
    /**
     * @return array{users: int, cafes: int, reservations: int, reviews: int, pending_reviews: int, users_trend: string, reservations_trend: string}
     */
    public function getSystemStatistics(): array;

    public function getMonthlyStats(int $month, int $year): array;

    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array;

    public function getReservationTrendStats(string $dateFrom, string $dateTo): array;

    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array;

    public function getUserDistributionByRole(): array;

    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array;
}
