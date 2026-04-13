<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Services\Contracts\AdminActivityServiceInterface;
use PDO;
use PDOException;

final class AdminActivityService implements AdminActivityServiceInterface
{
    private ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    #[\Override]
    public function getRecentReservations(int $limit = 10): array
    {
        try {
            $stmt = $this->getDb()->prepare('
                SELECT r.id, r.reservation_date as date, r.reservation_time as time_slot,
                       r.status, r.guest_count as guests,
                       c.name as cafe_name,
                       u.name as customer_name,
                       r.created_at
                FROM reservations r
                LEFT JOIN cafes c ON r.cafe_id = c.id
                LEFT JOIN users u ON r.user_id = u.id
                ORDER BY r.created_at DESC
                LIMIT :limit
            ');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] getRecentReservations ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return [];
        }
    }

    #[\Override]
    public function getUsersWithRoles(): array
    {
        $stmt = $this->getDb()->query('
            SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                   r.id as role_id, r.name as role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            ORDER BY u.created_at DESC
        ');

        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usersMap = [];

        foreach ($rawData as $row) {
            $userId = $row['id'];

            if (!isset($usersMap[$userId])) {
                $usersMap[$userId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'is_active' => $row['is_active'],
                    'created_at' => $row['created_at'],
                    'roles' => [],
                    'role_id' => null,
                ];
            }

            if ($row['role_name']) {
                $usersMap[$userId]['roles'][] = $row['role_name'];
                if ($usersMap[$userId]['role_id'] === null) {
                    $usersMap[$userId]['role_id'] = $row['role_id'];
                }
            }
        }

        return \array_values($usersMap);
    }

    #[\Override]
    public function getProductsWithCategories(): array
    {
        $stmt = $this->getDb()->query('
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            ORDER BY p.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getReservationsWithDetails(int $limit = 100): array
    {
        $stmt = $this->getDb()->prepare('
            SELECT r.*,
                   c.name as cafe_name,
                   c.image_url as cafe_image,
                   u.name as customer_name,
                   u.email as customer_email
            FROM reservations r
            LEFT JOIN cafes c ON r.cafe_id = c.id
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.reservation_date DESC, r.reservation_time DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function getRecentActivity(int $limit = 10): array
    {
        try {
            $activities = [];

            $stmt = $this->getDb()->query("
                SELECT 'reservation_confirmed' as type, r.created_at, c.name as cafe_name, u.name as user_name
                FROM reservations r
                LEFT JOIN cafes c ON r.cafe_id = c.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.status = 'confirmed' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY r.created_at DESC LIMIT 3
            ");
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reservations as $res) {
                $activities[] = [
                    'type' => 'success',
                    'icon' => 'check-lg',
                    'text' => 'Reserva confirmada',
                    'meta' => ($res['cafe_name'] ?? 'Cafetería') . ' · ' . $this->timeAgo($res['created_at']),
                    'timestamp' => $res['created_at'],
                ];
            }

            $stmt = $this->getDb()->query('
                SELECT name, email, created_at FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC LIMIT 3
            ');
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                $activities[] = [
                    'type' => 'info',
                    'icon' => 'person-plus',
                    'text' => 'Usuario nuevo registrado',
                    'meta' => ($user['email'] ?? 'Usuario') . ' · ' . $this->timeAgo($user['created_at']),
                    'timestamp' => $user['created_at'],
                ];
            }

            $stmt = $this->getDb()->query("
                SELECT r.created_at, c.name as cafe_name FROM reviews r
                LEFT JOIN cafes c ON r.cafe_id = c.id
                WHERE r.status = 'pending'
                ORDER BY r.created_at DESC LIMIT 2
            ");
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reviews as $review) {
                $activities[] = [
                    'type' => 'warning',
                    'icon' => 'star-fill',
                    'text' => 'Reseña pendiente',
                    'meta' => 'Requiere moderación · ' . $this->timeAgo($review['created_at']),
                    'timestamp' => $review['created_at'],
                ];
            }

            \usort($activities, static function ($a, $b) {
                return \strtotime($b['timestamp']) - \strtotime($a['timestamp']);
            });

            return \array_slice($activities, 0, $limit);
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] getRecentActivity ERROR: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array{database: string, cache: string, email: string}
     */
    #[\Override]
    public function getSystemStatus(): array
    {
        $status = [];

        try {
            $this->getDb()->query('SELECT 1')->execute();
            $status['database'] = 'online';
        } catch (PDOException $e) {
            Logger::error('[AdminActivityService] Database check failed: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
            $status['database'] = 'offline';
        }

        try {
            if (\extension_loaded('redis')) {
                $redis = new \Redis();
                $host = Env::get('REDIS_HOST', 'cache');
                $port = (int) Env::get('REDIS_PORT', '6379');
                $password = Env::get('REDIS_PASSWORD');

                if ($redis->connect($host, $port, 1)) {
                    if ($password && $password !== '') {
                        $redis->auth($password);
                    }
                    $redis->ping();
                    $status['cache'] = 'online';
                    $redis->close();
                } else {
                    $status['cache'] = 'offline';
                }
            } else {
                $status['cache'] = 'offline';
            }
        } catch (\Exception $e) {
            Logger::error('[AdminActivityService] Redis check failed: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
            $status['cache'] = 'offline';
        }

        $smtpHost = Env::get('MAIL_HOST');
        if ($smtpHost && $smtpHost !== 'mailpit') {
            try {
                $socket = @\fsockopen($smtpHost, (int) Env::get('MAIL_PORT', '587'), $errno, $errstr, 5);
                $status['email'] = $socket ? 'online' : 'offline';
                if ($socket) {
                    \fclose($socket);
                }
            } catch (\Exception) {
                $status['email'] = 'offline';
            }
        } else {
            $status['email'] = 'online';
        }

        return $status;
    }

    /**
     * @return array{labels: array, values: array}
     */
    #[\Override]
    public function getReservationsChartData(): array
    {
        try {
            $labels = [];
            $values = [];
            $daysEs = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

            for ($i = 6; $i >= 0; $i--) {
                $date = \date('Y-m-d', \strtotime("-$i days"));
                $dayOfWeek = (int) \date('w', \strtotime($date));
                $labels[] = $daysEs[$dayOfWeek];

                $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = :date');
                $stmt->execute(['date' => $date]);
                $values[] = (int) $stmt->fetchColumn();
            }

            return ['labels' => $labels, 'values' => $values];
        } catch (\Exception $e) {
            Logger::error('[AdminActivityService] Error generating chart data: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return [
                'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                'values' => [0, 0, 0, 0, 0, 0, 0],
            ];
        }
    }

    private function timeAgo(string $timestamp): string
    {
        $time = \strtotime($timestamp);
        $diff = \time() - $time;

        if ($diff < 60) {
            return 'hace un momento';
        }

        if ($diff < 3600) {
            $mins = \floor($diff / 60);

            return "hace $mins min";
        }

        if ($diff < 86400) {
            $hours = \floor($diff / 3600);

            return "hace {$hours}h";
        }

        $days = \floor($diff / 86400);

        return "hace {$days}d";
    }
}
