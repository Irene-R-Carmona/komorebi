<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use Override;
use PDOException;

final class AdminStatisticsService implements AdminStatisticsServiceInterface
{
    public function __construct(
        private readonly StatisticsRepositoryInterface $statsRepo
    ) {}

    #[Override]
    public function getSystemStatistics(): Result
    {
        try {
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

            return Result::ok($stats);
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getSystemStatistics ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getMonthlyStats(int $month, int $year): Result
    {
        try {
            $raw = $this->statsRepo->getMonthlyStats($month, $year);

            $r = $raw['reservations'];
            $u = $raw['users'];
            $v = $raw['reviews'];

            return Result::ok([
                'reservations' => (int) $r['total_reservations'],
                'guests' => (int) $r['total_guests'],
                'unique_users' => (int) $r['unique_users'],
                'completed' => (int) $r['completed_reservations'],
                'cancelled' => (int) $r['cancelled_reservations'],
                'no_shows' => (int) $r['no_shows'],
                'revenue' => (int) ($r['monthly_revenue'] ?? 0),
                'new_users' => (int) $u['new_users'],
                'reviews' => (int) $v['total_reviews'],
                'avg_rating' => \round((float) $v['avg_rating'], 2),
            ]);
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getMonthlyStats ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): Result
    {
        try {
            return Result::ok($this->statsRepo->getCafePerformanceStats($dateFrom, $dateTo, $limit));
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getCafePerformanceStats ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getReservationTrendStats(string $dateFrom, string $dateTo): Result
    {
        try {
            return Result::ok($this->statsRepo->getReservationTrendStats($dateFrom, $dateTo));
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getReservationTrendStats ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getReservationsByCafeType(string $dateFrom, string $dateTo): Result
    {
        try {
            return Result::ok($this->statsRepo->getReservationsByCafeType($dateFrom, $dateTo));
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getReservationsByCafeType ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getUserDistributionByRole(): Result
    {
        try {
            return Result::ok($this->statsRepo->getUserDistributionByRole());
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getUserDistributionByRole ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
    }

    #[Override]
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): Result
    {
        try {
            $results = $this->statsRepo->getTopCafes($dateFrom, $dateTo, $limit);

            foreach ($results as &$cafe) {
                $cafe['avg_rating'] = \round((float) $cafe['avg_rating'], 2);
            }

            return Result::ok($results);
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] getTopCafes ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail($e->getMessage(), 'db_error');
        }
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
