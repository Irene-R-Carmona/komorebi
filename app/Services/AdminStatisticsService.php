<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Override;
use PDOException;

final class AdminStatisticsService implements AdminStatisticsServiceInterface
{
    public function __construct(
        private readonly StatisticsRepositoryInterface $statsRepo
    ) {
    }

    #[Override]
    public function getSystemStatistics(): array
    {
        $stats = $this->statsRepo->getSystemCounts();

        try {
            $users = $this->statsRepo->getWeeklyUserCounts();
            $reservations = $this->statsRepo->getWeeklyReservationCounts();

            $stats['users_trend'] = $this->calculateTrend($users['current_week'], $users['previous_week']);
            $stats['reservations_trend'] = $this->calculateTrend(
                $reservations['current_week'],
                $reservations['previous_week']
            );
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] Error calculating trends: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
            ]);
            $stats['users_trend'] = '0%';
            $stats['reservations_trend'] = '0%';
        }

        return $stats;
    }

    #[Override]
    public function getMonthlyStats(int $month, int $year): array
    {
        $raw = $this->statsRepo->getMonthlyStats($month, $year);

        $r = $raw['reservations'];
        $u = $raw['users'];
        $v = $raw['reviews'];

        return [
            'reservations' => (int) $r['total_reservations'],
            'guests' => (int) $r['total_guests'],
            'unique_users' => (int) $r['unique_users'],
            'completed' => (int) $r['completed_reservations'],
            'cancelled' => (int) $r['cancelled_reservations'],
            'no_shows' => (int) $r['no_shows'],
            'new_users' => (int) $u['new_users'],
            'reviews' => (int) $v['total_reviews'],
            'avg_rating' => \round((float) $v['avg_rating'], 2),
        ];
    }

    #[Override]
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        return $this->statsRepo->getCafePerformanceStats($dateFrom, $dateTo, $limit);
    }

    #[Override]
    public function getReservationTrendStats(string $dateFrom, string $dateTo): array
    {
        return $this->statsRepo->getReservationTrendStats($dateFrom, $dateTo);
    }

    #[Override]
    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array
    {
        return $this->statsRepo->getReservationsByCafeType($dateFrom, $dateTo);
    }

    #[Override]
    public function getUserDistributionByRole(): array
    {
        return $this->statsRepo->getUserDistributionByRole();
    }

    #[Override]
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $results = $this->statsRepo->getTopCafes($dateFrom, $dateTo, $limit);

        foreach ($results as &$cafe) {
            $cafe['avg_rating'] = \round((float) $cafe['avg_rating'], 2);
        }

        return $results;
    }

    private function calculateTrend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $percentChange = (($current - $previous) / $previous) * 100;
        $sign = $percentChange >= 0 ? '+' : '';

        return $sign . \round($percentChange) . '%';
    }
}
