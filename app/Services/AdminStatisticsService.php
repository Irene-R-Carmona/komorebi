<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\Contracts\AdminStatisticsServiceInterface;
use PDO;
use PDOException;

final class AdminStatisticsService implements AdminStatisticsServiceInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @return array{users: int, cafes: int, reservations: int, reviews: int, pending_reviews: int, users_trend: string, reservations_trend: string}
     */
    #[\Override]
    public function getSystemStatistics(): array
    {
        $stats = [
            'users' => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'cafes' => (int) $this->db->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
            'reservations' => (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
            'reviews' => (int) $this->db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
            'pending_reviews' => (int) $this->db->query('SELECT COUNT(*) FROM reviews WHERE status = "pending"')->fetchColumn(),
        ];

        try {
            $usersCurrentWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();
            $usersPreviousWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();

            $reservationsCurrentWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();
            $reservationsPreviousWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();

            $stats['users_trend'] = $this->calculateTrend($usersCurrentWeek, $usersPreviousWeek);
            $stats['reservations_trend'] = $this->calculateTrend($reservationsCurrentWeek, $reservationsPreviousWeek);
        } catch (PDOException $e) {
            Logger::error('[AdminStatisticsService] Error calculating trends: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
            $stats['users_trend'] = '0%';
            $stats['reservations_trend'] = '0%';
        }

        return $stats;
    }

    #[\Override]
    public function getMonthlyStats(int $month, int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT r.id) as total_reservations,
                COALESCE(SUM(r.guest_count), 0) as total_guests,
                COUNT(DISTINCT r.user_id) as unique_users,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'no_show' THEN r.id END) as no_shows
            FROM reservations r
            WHERE MONTH(r.reservation_date) = :month
              AND YEAR(r.reservation_date) = :year
        ");
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reservationStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('SELECT COUNT(*) as new_users FROM users WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year');
        $stmt->execute(['month' => $month, 'year' => $year]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as avg_rating FROM reviews WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year AND status = 'approved'");
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reviewStats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'reservations' => (int) $reservationStats['total_reservations'],
            'guests' => (int) $reservationStats['total_guests'],
            'unique_users' => (int) $reservationStats['unique_users'],
            'completed' => (int) $reservationStats['completed_reservations'],
            'cancelled' => (int) $reservationStats['cancelled_reservations'],
            'no_shows' => (int) $reservationStats['no_shows'],
            'new_users' => (int) $userStats['new_users'],
            'reviews' => (int) $reviewStats['total_reviews'],
            'avg_rating' => \round((float) $reviewStats['avg_rating'], 2),
        ];
    }

    #[\Override]
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.category as type,
                COUNT(DISTINCT r.id) as total_reservations,
                SUM(r.guest_count) as total_guests,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) * 100.0) /
                    NULLIF(COUNT(DISTINCT r.id), 0), 2
                ) as completion_rate
            FROM cafes c
            LEFT JOIN reservations r ON c.id = r.cafe_id
                AND r.reservation_date BETWEEN :date_from AND :date_to
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.category
            ORDER BY total_reservations DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getReservationTrendStats(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.reservation_date as date,
                COUNT(DISTINCT r.id) as total_reservations,
                SUM(r.guest_count) as total_guests,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled
            FROM reservations r
            WHERE r.reservation_date BETWEEN :date_from AND :date_to
            GROUP BY r.reservation_date
            ORDER BY r.reservation_date
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare('
            SELECT
                c.category as type,
                COUNT(DISTINCT r.id) as total_reservations,
                SUM(r.guest_count) as total_guests,
                ROUND(
                    (COUNT(DISTINCT r.id) * 100.0) /
                    NULLIF((SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN :date_from_sub AND :date_to_sub), 0),
                    2
                ) as percentage
            FROM cafes c
            INNER JOIN reservations r ON c.id = r.cafe_id
            WHERE r.reservation_date BETWEEN :date_from AND :date_to
            GROUP BY c.category
            ORDER BY total_reservations DESC
        ');
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_sub' => $dateFrom,
            'date_to_sub' => $dateTo,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getUserDistributionByRole(): array
    {
        $stmt = $this->db->query('
            SELECT r.name as role_name, r.code as role_code, COUNT(DISTINCT ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.name, r.code
            ORDER BY user_count DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.category as type, c.location,
                COUNT(DISTINCT r.id) as total_reservations,
                SUM(r.guest_count) as total_guests,
                COALESCE(AVG(rev.rating), 0) as avg_rating,
                COUNT(DISTINCT rev.id) as review_count
            FROM cafes c
            INNER JOIN reservations r ON c.id = r.cafe_id
            LEFT JOIN reviews rev ON c.id = rev.cafe_id AND rev.status = 'approved'
            WHERE r.reservation_date BETWEEN :date_from AND :date_to
              AND c.is_active = 1
            GROUP BY c.id, c.name, c.category, c.location
            ORDER BY total_reservations DESC, avg_rating DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
