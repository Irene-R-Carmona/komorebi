<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use PDO;

final class StatisticsRepository implements StatisticsRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getSystemCounts(): array
    {
        return [
            'users'           => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'cafes'           => (int) $this->db->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
            'reservations'    => (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
            'reviews'         => (int) $this->db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
            'pending_reviews' => (int) $this->db->query('SELECT COUNT(*) FROM reviews WHERE status = "pending"')->fetchColumn(),
        ];
    }

    public function getWeeklyUserCounts(): array
    {
        return [
            'current_week'  => (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
            'previous_week' => (int) $this->db->query(
                'SELECT COUNT(*) FROM users
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                   AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
        ];
    }

    public function getWeeklyReservationCounts(): array
    {
        return [
            'current_week'  => (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
            'previous_week' => (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                   AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
        ];
    }

    public function getMonthlyStats(int $month, int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT r.id)                                                       AS total_reservations,
                COALESCE(SUM(r.guest_count), 0)                                            AS total_guests,
                COUNT(DISTINCT r.user_id)                                                  AS unique_users,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END)             AS completed_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END)             AS cancelled_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'no_show'   THEN r.id END)             AS no_shows
            FROM reservations r
            WHERE MONTH(r.reservation_date) = :month
              AND YEAR(r.reservation_date)  = :year
        ");
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reservations = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS new_users FROM users WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year'
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $users = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating
             FROM reviews
             WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year AND status = 'approved'"
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reviews = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'reservations' => (array) $reservations,
            'users'        => (array) $users,
            'reviews'      => (array) $reviews,
        ];
    }

    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.category AS type,
                COUNT(DISTINCT r.id)                                                        AS total_reservations,
                SUM(r.guest_count)                                                          AS total_guests,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END)             AS completed,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END)             AS cancelled,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) * 100.0) /
                    NULLIF(COUNT(DISTINCT r.id), 0), 2
                ) AS completion_rate
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getReservationTrendStats(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.reservation_date AS date,
                COUNT(DISTINCT r.id)                                                        AS total_reservations,
                SUM(r.guest_count)                                                          AS total_guests,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END)             AS completed,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END)             AS cancelled
            FROM reservations r
            WHERE r.reservation_date BETWEEN :date_from AND :date_to
            GROUP BY r.reservation_date
            ORDER BY r.reservation_date
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare('
            SELECT
                c.category AS type,
                COUNT(DISTINCT r.id)   AS total_reservations,
                SUM(r.guest_count)     AS total_guests,
                ROUND(
                    (COUNT(DISTINCT r.id) * 100.0) /
                    NULLIF(
                        (SELECT COUNT(*) FROM reservations
                         WHERE reservation_date BETWEEN :date_from_sub AND :date_to_sub),
                        0
                    ), 2
                ) AS percentage
            FROM cafes c
            INNER JOIN reservations r ON c.id = r.cafe_id
            WHERE r.reservation_date BETWEEN :date_from AND :date_to
            GROUP BY c.category
            ORDER BY total_reservations DESC
        ');
        $stmt->execute([
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'date_from_sub' => $dateFrom,
            'date_to_sub'   => $dateTo,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getUserDistributionByRole(): array
    {
        $stmt = $this->db->query('
            SELECT r.name AS role_name, r.code AS role_code, COUNT(DISTINCT ur.user_id) AS user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.name, r.code
            ORDER BY user_count DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.category AS type, c.location,
                COUNT(DISTINCT r.id)          AS total_reservations,
                SUM(r.guest_count)            AS total_guests,
                COALESCE(AVG(rev.rating), 0)  AS avg_rating,
                COUNT(DISTINCT rev.id)        AS review_count
            FROM cafes c
            INNER JOIN reservations r  ON c.id = r.cafe_id
            LEFT JOIN  reviews     rev ON c.id = rev.cafe_id AND rev.status = 'approved'
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCafeStats(): array|false
    {
        return $this->db->query('
            SELECT
                COUNT(*) as total,
                SUM(IF(is_active = 1, 1, 0)) as active,
                SUM(IF(has_reservations = 1, 1, 0)) as with_reservations,
                COUNT(DISTINCT category) as categories,
                COUNT(DISTINCT animal_type) as animal_types
            FROM cafes
        ')->fetch(PDO::FETCH_ASSOC);
    }

    public function getReportsSummary(string $dateFrom, string $dateTo): array
    {
        $p = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute($p);
        $totalReservations = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COALESCE(SUM(guest_count), 0) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute($p);
        $totalGuests = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved' AND created_at BETWEEN :date_from AND :date_to");
        $stmt->execute($p);
        $avgRating = \round((float) $stmt->fetchColumn(), 2);

        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT user_id) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to AND user_id IS NOT NULL');
        $stmt->execute($p);
        $activeUsers = (int) $stmt->fetchColumn();

        return [
            'total_reservations'        => $totalReservations,
            'total_guests'              => $totalGuests,
            'avg_rating'                => $avgRating,
            'active_users'              => $activeUsers,
            'avg_guests_per_reservation' => $totalReservations > 0 ? \round($totalGuests / $totalReservations, 2) : 0,
        ];
    }

    public function getDataViewerStats(): array
    {
        return [
            'users'                    => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'staff'                    => (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role != 'user'")->fetchColumn(),
            'cafes'                    => (int) $this->db->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
            'animals'                  => (int) $this->db->query('SELECT COUNT(*) FROM animals')->fetchColumn(),
            'products'                 => (int) $this->db->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'reservations'             => (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
            'reservations_with_slot'   => (int) $this->db->query('SELECT COUNT(*) FROM reservations WHERE time_slot_id IS NOT NULL')->fetchColumn(),
            'time_slots'               => (int) $this->db->query('SELECT COUNT(*) FROM time_slots')->fetchColumn(),
            'time_slots_available'     => (int) $this->db->query('SELECT COUNT(*) FROM time_slots WHERE slot_date >= CURDATE() AND is_blocked = 0')->fetchColumn(),
            'reviews'                  => (int) $this->db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
            'incidents'                => (int) $this->db->query('SELECT COUNT(*) FROM animal_health_checks')->fetchColumn(),
        ];
    }

    public function getRecentReservations(int $limit = 10): array
    {
        $stmt = $this->db->query(\sprintf('
            SELECT r.id, r.reservation_date AS date, r.reservation_time AS time_slot,
                   r.status, r.guest_count AS guests,
                   c.name AS cafe_name, u.name AS customer_name, r.created_at
            FROM reservations r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
            LIMIT %d
        ', $limit));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReservationsWithDetails(int $limit = 100): array
    {
        $stmt = $this->db->query(\sprintf('
            SELECT r.*, c.name AS cafe_name, c.image_url AS cafe_image,
                   u.name AS customer_name, u.email AS customer_email
            FROM reservations r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.reservation_date DESC, r.reservation_time DESC
            LIMIT %d
        ', $limit));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductsWithCategories(): array
    {
        $stmt = $this->db->query('
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            ORDER BY p.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity(int $limit = 10): array
    {
        $activities = [];

        $stmt = $this->db->query("
            SELECT 'reservation_confirmed' AS type, r.created_at,
                   c.name AS cafe_name, u.name AS user_name
            FROM reservations r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.status = 'confirmed' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY r.created_at DESC LIMIT 3
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $res) {
            $activities[] = ['type' => 'success', 'icon' => 'check-lg',
                'text' => 'Reserva confirmada', 'timestamp' => $res['created_at'],
                'meta' => ($res['cafe_name'] ?? 'Cafetería')];
        }

        $stmt = $this->db->query('
            SELECT name, email, created_at FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC LIMIT 3
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $activities[] = ['type' => 'info', 'icon' => 'person-plus',
                'text' => 'Usuario nuevo registrado', 'timestamp' => $user['created_at'],
                'meta' => ($user['email'] ?? 'Usuario')];
        }

        $stmt = $this->db->query("
            SELECT r.created_at, c.name AS cafe_name FROM reviews r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC LIMIT 2
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
            $activities[] = ['type' => 'warning', 'icon' => 'star-fill',
                'text' => 'Reseña pendiente', 'timestamp' => $review['created_at'],
                'meta' => 'Requiere moderación'];
        }

        \usort($activities, static fn ($a, $b) => \strtotime($b['timestamp']) - \strtotime($a['timestamp']));

        return \array_slice($activities, 0, $limit);
    }

    public function getReservationsChartData(): array
    {
        $labels  = [];
        $values  = [];
        $daysEs  = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        for ($i = 6; $i >= 0; $i--) {
            $date        = \date('Y-m-d', \strtotime("-$i days"));
            $labels[]    = $daysEs[(int) \date('w', \strtotime($date))];
            $stmt        = $this->db->prepare('SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = :date');
            $stmt->execute(['date' => $date]);
            $values[]    = (int) $stmt->fetchColumn();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function getDataViewerSamples(): array
    {
        return [
            'cafes'        => $this->db->query('SELECT name, animal_type, capacity_max, opening_time, closing_time, NULL AS rating_avg FROM cafes LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            'products'     => $this->db->query('SELECT name, japanese_name, price, duration_minutes AS duration, min_pax, max_pax FROM products LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            'staff'        => $this->db->query("SELECT u.name, u.email, u.role AS roles, NULL AS cafe FROM users u WHERE u.role != 'user' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC),
            'users'        => $this->db->query("SELECT name, email, role AS roles FROM users WHERE role = 'user' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC),
            'reservations' => $this->db->query('SELECT u.name AS user, c.name AS cafe, p.name AS pass_name, p.price AS pass_unit_price, r.reservation_date, r.reservation_time, r.guest_count, r.status, r.time_slot_id FROM reservations r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN cafes c ON c.id = r.cafe_id LEFT JOIN products p ON p.id = r.pass_product_id ORDER BY r.id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            'time_slots'   => $this->db->query('SELECT id, cafe_id, slot_date, start_time, end_time, capacity_max, booked_count, is_blocked FROM time_slots ORDER BY slot_date DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            'reviews'      => $this->db->query('SELECT id, user_id, cafe_id, rating, comment, is_approved FROM reviews ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
            'incidents'    => $this->db->query('SELECT id, animal_id, check_date, appetite, energy_level, notes FROM animal_health_checks ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
