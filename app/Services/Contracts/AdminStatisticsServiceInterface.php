<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AdminStatisticsServiceInterface
{
    public function getSystemStatistics(): Result;

    public function getMonthlyStats(int $month, int $year): Result;

    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): Result;

    public function getReservationTrendStats(string $dateFrom, string $dateTo): Result;

    public function getReservationsByCafeType(string $dateFrom, string $dateTo): Result;

    public function getUserDistributionByRole(): Result;

    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): Result;
}
