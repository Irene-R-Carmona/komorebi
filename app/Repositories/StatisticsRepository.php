<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\StatisticsRepositoryInterface;
use LogicException;
use Override;
use PDO;

final class StatisticsRepository extends AbstractRepository implements StatisticsRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'users';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id'];
    }

    #[Override]
    public function create(array $data): int
    {
        throw new LogicException('Mutations not allowed on StatisticsRepository');
    }

    #[Override]
    public function update(int $id, array $data): bool
    {
        throw new LogicException('Mutations not allowed on StatisticsRepository');
    }

    #[Override]
    public function delete(int $id): bool
    {
        throw new LogicException('Mutations not allowed on StatisticsRepository');
    }

    #[Override]
    public function getSystemCounts(): array
    {
        $row = $this->getDb()->query("
            SELECT
                (SELECT COUNT(*) FROM users)                                  AS users,
                (SELECT COUNT(*) FROM cafes)                                  AS cafes,
                (SELECT COUNT(*) FROM reservations)                           AS reservations,
                (SELECT COUNT(*) FROM reviews)                                AS reviews,
                (SELECT COUNT(*) FROM reviews WHERE status = 'pending')       AS pending_reviews
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'users' => (int) ($row['users'] ?? 0),
            'cafes' => (int) ($row['cafes'] ?? 0),
            'reservations' => (int) ($row['reservations'] ?? 0),
            'reviews' => (int) ($row['reviews'] ?? 0),
            'pending_reviews' => (int) ($row['pending_reviews'] ?? 0),
        ];
    }

    #[Override]
    public function getWeeklyUserCounts(): array
    {
        return [
            'current_week' => (int) $this->getDb()->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
            'previous_week' => (int) $this->getDb()->query(
                'SELECT COUNT(*) FROM users
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                   AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
        ];
    }

    #[Override]
    public function getWeeklyReservationCounts(): array
    {
        return [
            'current_week' => (int) $this->getDb()->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
            'previous_week' => (int) $this->getDb()->query(
                'SELECT COUNT(*) FROM reservations
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                   AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn(),
        ];
    }

    #[Override]
    public function getMonthlyStats(int $month, int $year): array
    {
        $stmt = $this->getDb()->prepare("
            SELECT
                COUNT(DISTINCT r.id)                                                       AS total_reservations,
                COALESCE(SUM(r.guest_count), 0)                                            AS total_guests,
                COUNT(DISTINCT r.user_id)                                                  AS unique_users,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END)             AS completed_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END)             AS cancelled_reservations,
                COUNT(DISTINCT CASE WHEN r.status = 'no_show'   THEN r.id END)             AS no_shows,
                COALESCE(SUM(CASE WHEN r.status = 'completed' AND r.payment_status = 'paid' THEN r.final_amount ELSE 0 END), 0) AS monthly_revenue
            FROM reservations r
            WHERE MONTH(r.reservation_date) = :month
              AND YEAR(r.reservation_date)  = :year
        ");
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reservations = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->getDb()->prepare(
            'SELECT COUNT(*) AS new_users FROM users WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year'
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $users = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating
             FROM reviews
             WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year AND status = 'approved'"
        );
        $stmt->execute(['month' => $month, 'year' => $year]);
        $reviews = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'reservations' => (array) $reservations,
            'users' => (array) $users,
            'reviews' => (array) $reviews,
        ];
    }

    #[Override]
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->getDb()->prepare("
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

    #[Override]
    public function getReservationTrendStats(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->getDb()->prepare("
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

    #[Override]
    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->getDb()->prepare('
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
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_sub' => $dateFrom,
            'date_to_sub' => $dateTo,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[Override]
    public function getUserDistributionByRole(): array
    {
        $stmt = $this->getDb()->query('
            SELECT r.name AS role_name, r.code AS role_code, COUNT(DISTINCT ur.user_id) AS user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.name, r.code
            ORDER BY user_count DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[Override]
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->getDb()->prepare("
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

    #[Override]
    public function getCafeStats(): array|false
    {
        return $this->getDb()->query('
            SELECT
                COUNT(*) as total,
                SUM(IF(is_active = 1, 1, 0)) as active,
                SUM(IF(has_reservations = 1, 1, 0)) as with_reservations,
                COUNT(DISTINCT category) as categories,
                COUNT(DISTINCT animal_type) as animal_types
            FROM cafes
        ')->fetch(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function getReportsSummary(string $dateFrom, string $dateTo): array
    {
        $p = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute($p);
        $totalReservations = (int) $stmt->fetchColumn();

        $stmt = $this->getDb()->prepare('SELECT COALESCE(SUM(guest_count), 0) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute($p);
        $totalGuests = (int) $stmt->fetchColumn();

        $stmt = $this->getDb()->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved' AND created_at BETWEEN :date_from AND :date_to");
        $stmt->execute($p);
        $avgRating = \round((float) $stmt->fetchColumn(), 2);

        $stmt = $this->getDb()->prepare('SELECT COUNT(DISTINCT user_id) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to AND user_id IS NOT NULL');
        $stmt->execute($p);
        $activeUsers = (int) $stmt->fetchColumn();

        return [
            'total_reservations' => $totalReservations,
            'total_guests' => $totalGuests,
            'avg_rating' => $avgRating,
            'active_users' => $activeUsers,
            'avg_guests_per_reservation' => $totalReservations > 0 ? \round($totalGuests / $totalReservations, 2) : 0,
        ];
    }

    #[Override]
    public function getDataViewerStats(): array
    {
        return [
            'users' => (int) $this->getDb()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'staff' => (int) $this->getDb()->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.code != 'user'")->fetchColumn(),
            'cafes' => (int) $this->getDb()->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
            'animals' => (int) $this->getDb()->query('SELECT COUNT(*) FROM animals')->fetchColumn(),
            'products' => (int) $this->getDb()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
            'reservations' => (int) $this->getDb()->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
            'reservations_with_slot' => (int) $this->getDb()->query('SELECT COUNT(*) FROM reservations WHERE time_slot_id IS NOT NULL')->fetchColumn(),
            'time_slots' => (int) $this->getDb()->query('SELECT COUNT(*) FROM time_slots')->fetchColumn(),
            'time_slots_available' => (int) $this->getDb()->query('SELECT COUNT(*) FROM time_slots WHERE slot_date >= CURDATE() AND is_blocked = 0')->fetchColumn(),
            'reviews' => (int) $this->getDb()->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
            'incidents' => (int) $this->getDb()->query('SELECT COUNT(*) FROM animal_health_checks')->fetchColumn(),
        ];
    }

    #[Override]
    public function getRecentReservations(int $limit = 10): array
    {
        $stmt = $this->getDb()->query(\sprintf('
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

    #[Override]
    public function getReservationsWithDetails(int $limit = 100): array
    {
        $stmt = $this->getDb()->query(\sprintf('
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

    #[Override]
    public function getProductsWithCategories(): array
    {
        $stmt = $this->getDb()->query('
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            ORDER BY p.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function getRecentActivity(int $limit = 10): array
    {
        $activities = [];

        $stmt = $this->getDb()->query("
            SELECT 'reservation_confirmed' AS type, r.created_at,
                   c.name AS cafe_name, u.name AS user_name
            FROM reservations r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.status = 'confirmed' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY r.created_at DESC LIMIT 3
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $res) {
            $activities[] = [
                'type' => 'success',
                'icon' => 'check-lg',
                'text' => 'Reserva confirmada',
                'timestamp' => $res['created_at'],
                'meta' => ($res['cafe_name'] ?? 'Cafetería'),
            ];
        }

        $stmt = $this->getDb()->query('
            SELECT name, email, created_at FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC LIMIT 3
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $activities[] = [
                'type' => 'info',
                'icon' => 'person-plus',
                'text' => 'Usuario nuevo registrado',
                'timestamp' => $user['created_at'],
                'meta' => ($user['email'] ?? 'Usuario'),
            ];
        }

        $stmt = $this->getDb()->query("
            SELECT r.created_at, c.name AS cafe_name FROM reviews r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            WHERE r.status = 'pending'
            ORDER BY r.created_at DESC LIMIT 2
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
            $activities[] = [
                'type' => 'warning',
                'icon' => 'star-fill',
                'text' => 'Reseña pendiente',
                'timestamp' => $review['created_at'],
                'meta' => 'Requiere moderación',
            ];
        }

        \usort($activities, static fn ($a, $b) => \strtotime($b['timestamp']) - \strtotime($a['timestamp']));

        return \array_slice($activities, 0, $limit);
    }

    #[Override]
    public function getReservationsChartData(): array
    {
        $labels = [];
        $values = [];
        $daysEs = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        for ($i = 6; $i >= 0; $i--) {
            $date = \date('Y-m-d', \strtotime("-$i days"));
            $labels[] = $daysEs[(int) \date('w', \strtotime($date))];
            $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = :date');
            $stmt->execute(['date' => $date]);
            $values[] = (int) $stmt->fetchColumn();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    #[Override]
    public function getDataViewerSamples(int $page = 1, int $perPage = 10): array
    {
        $page = \max(1, $page);
        $perPage = \min(100, \max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $fetch = $perPage + 1;

        $db = $this->getDb();

        $run = static function (string $sql, array $params = []) use ($db): array {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };

        $cafes = $run("SELECT name, animal_type, capacity_max, opening_time, closing_time, (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.cafe_id = cafes.id AND r.status = 'approved') AS rating_avg FROM cafes LIMIT ? OFFSET ?", [$fetch, $offset]);
        $products = $run('SELECT name, japanese_name, price, duration_minutes AS duration, min_pax, max_pax FROM products LIMIT ? OFFSET ?', [$fetch, $offset]);
        $staff = $run("SELECT u.name, u.email, GROUP_CONCAT(r.code SEPARATOR ',') AS roles, c.name AS cafe FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id LEFT JOIN cafes c ON u.cafe_id = c.id WHERE r.code != 'user' GROUP BY u.id, u.name, u.email, c.name LIMIT ? OFFSET ?", [$fetch, $offset]);
        $users = $run("SELECT u.name, u.email, r.code AS roles FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.code = 'user' LIMIT ? OFFSET ?", [$fetch, $offset]);
        $reservations = $run("SELECT u.name AS user, c.name AS cafe, p.name AS pass_name, p.price AS pass_unit_price, r.reservation_date, r.reservation_time, r.guest_count, r.status, IF(r.time_slot_id IS NOT NULL, 'Sí', 'No') AS has_slot FROM reservations r LEFT JOIN users u ON u.id = r.user_id LEFT JOIN cafes c ON c.id = r.cafe_id LEFT JOIN products p ON p.id = r.pass_product_id ORDER BY r.id DESC LIMIT ? OFFSET ?", [$fetch, $offset]);
        $time_slots = $run('SELECT ts.slot_date, ts.slot_time, c.name AS cafe, ts.total_capacity, ts.reserved_spots, ts.available_spots, ts.is_blocked FROM time_slots ts LEFT JOIN cafes c ON c.id = ts.cafe_id ORDER BY ts.slot_date DESC LIMIT ? OFFSET ?', [$fetch, $offset]);
        $reviews = $run("SELECT rv.rating, COALESCE(rv.title, '') AS title, c.name AS cafe, u.name AS user, rv.created_at FROM reviews rv LEFT JOIN cafes c ON c.id = rv.cafe_id LEFT JOIN users u ON u.id = rv.user_id ORDER BY rv.id DESC LIMIT ? OFFSET ?", [$fetch, $offset]);
        $incidents = $run("SELECT 'health' AS type, a.name AS animal, c.name AS cafe, CONCAT('Apetito: ', hc.appetite, ', Energía: ', hc.energy_level, '. ', COALESCE(hc.notes, '')) AS description, CASE WHEN hc.appetite = 'none' OR hc.energy_level = 'low' THEN 'high' WHEN hc.appetite = 'reduced' THEN 'medium' ELSE 'low' END AS severity, u.name AS reported_by FROM animal_health_checks hc LEFT JOIN animals a ON a.id = hc.animal_id LEFT JOIN cafes c ON c.id = a.cafe_id LEFT JOIN users u ON u.id = hc.checked_by ORDER BY hc.id DESC LIMIT ? OFFSET ?", [$fetch, $offset]);

        $hasNextPage = \count($cafes) > $perPage
            || \count($products) > $perPage
            || \count($staff) > $perPage
            || \count($users) > $perPage
            || \count($reservations) > $perPage
            || \count($time_slots) > $perPage
            || \count($reviews) > $perPage
            || \count($incidents) > $perPage;

        return [
            'cafes' => \array_slice($cafes, 0, $perPage),
            'products' => \array_slice($products, 0, $perPage),
            'staff' => \array_slice($staff, 0, $perPage),
            'users' => \array_slice($users, 0, $perPage),
            'reservations' => \array_slice($reservations, 0, $perPage),
            'time_slots' => \array_slice($time_slots, 0, $perPage),
            'reviews' => \array_slice($reviews, 0, $perPage),
            'incidents' => \array_slice($incidents, 0, $perPage),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'has_next_page' => $hasNextPage],
        ];
    }
}
