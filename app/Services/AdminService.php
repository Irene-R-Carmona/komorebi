<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use PDO;
use PDOException;

/**
 * Servicio de administración y dashboard
 *
 * Encapsula la lógica de negocio relacionada con
 * estadísticas del sistema, dashboard administrativo
 * y consultas complejas de datos agregados.
 *
 * @package Komorebi\Services
 */
final class AdminService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Obtiene la instancia de PDO (para queries específicas del controlador)
     *
     * @return PDO
     */
    public function getDatabase(): PDO
    {
        return $this->db;
    }

    /**
     * Obtiene estadísticas generales del sistema con tendencias
     *
     * @return array{users: int, cafes: int, reservations: int, reviews: int, pending_reviews: int, users_trend: string, reservations_trend: string}
     */
    public function getSystemStatistics(): array
    {
        // Estadísticas actuales
        $stats = [
            'users' => (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'cafes' => (int) $this->db->query('SELECT COUNT(*) FROM cafes')->fetchColumn(),
            'reservations' => (int) $this->db->query('SELECT COUNT(*) FROM reservations')->fetchColumn(),
            'reviews' => (int) $this->db->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
            'pending_reviews' => (int) $this->db->query('SELECT COUNT(*) FROM reviews WHERE status = "pending"')->fetchColumn(),
        ];

        // FASE 4: Calcular tendencias (últimos 7 días vs 7 días anteriores)
        try {
            // Usuarios - nuevos registros
            $usersCurrentWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();
            $usersPreviousWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();

            // Reservas - nuevas reservas
            $reservationsCurrentWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();
            $reservationsPreviousWeek = (int) $this->db->query(
                'SELECT COUNT(*) FROM reservations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
            )->fetchColumn();

            // Calcular porcentaje de cambio
            $stats['users_trend'] = $this->calculateTrend($usersCurrentWeek, $usersPreviousWeek);
            $stats['reservations_trend'] = $this->calculateTrend($reservationsCurrentWeek, $reservationsPreviousWeek);
        } catch (PDOException $e) {
            Logger::error('[AdminService] Error calculating trends: ' . $e->getMessage());
            $stats['users_trend'] = '0%';
            $stats['reservations_trend'] = '0%';
        }

        return $stats;
    }

    /**
     * Calcula el porcentaje de cambio entre dos valores
     *
     * @param integer $current  Valor actual
     * @param integer $previous Valor anterior
     * @return string Porcentaje formateado con signo (+12% o -5%)
     */
    private function calculateTrend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $percentChange = (($current - $previous) / $previous) * 100;
        $sign = $percentChange >= 0 ? '+' : '';

        return $sign . \round($percentChange) . '%';
    }

    /**
     * Obtiene reservas recientes con información relacionada
     *
     * @param integer $limit Número máximo de reservas
     * @return array
     */
    public function getRecentReservations(int $limit = 10): array
    {
        try {
            $stmt = $this->db->prepare('
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
            Logger::error('[AdminService] getRecentReservations ERROR: ' . $e->getMessage());
            Logger::error('[AdminService] SQL State: ' . $e->getCode());

            // Retornar array vacío en caso de error para evitar 500
            return [];
        }
    }

    /**
     * Obtiene usuarios con sus roles agrupados
     *
     * @return array
     */
    public function getUsersWithRoles(): array
    {
        $stmt = $this->db->query('
            SELECT u.id, u.name, u.email, u.is_active, u.created_at,
                   r.id as role_id, r.name as role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            ORDER BY u.created_at DESC
        ');

        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar roles por usuario
        $usersMap = [];

        foreach ($rawData as $row) {
            $userId = $row['id'];

            // Si el usuario no existe en el mapa, inicializarlo
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

            // Agregar el rol si existe
            if ($row['role_name']) {
                $usersMap[$userId]['roles'][] = $row['role_name'];
                // Guardar el primer role_id para compatibilidad con formularios
                if ($usersMap[$userId]['role_id'] === null) {
                    $usersMap[$userId]['role_id'] = $row['role_id'];
                }
            }
        }

        // Convertir mapa a array indexado
        return \array_values($usersMap);
    }

    /**
     * Obtiene productos con sus categorías
     *
     * @return array
     */
    public function getProductsWithCategories(): array
    {
        $stmt = $this->db->query('
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN menu_categories c ON p.category_id = c.id
            ORDER BY p.name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene reservas con detalles completos
     *
     * @param integer $limit Número máximo de reservas
     * @return array
     */
    public function getReservationsWithDetails(int $limit = 100): array
    {
        $stmt = $this->db->prepare('
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

    // =========================================================================
    // REPORTES Y ESTADÍSTICAS
    // =========================================================================

    /**
     * Obtiene estadísticas generales del mes
     *
     * @param integer $month Mes (1-12)
     * @param integer $year  Año
     * @return array
     */
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

        // Estadísticas de usuarios nuevos
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as new_users
            FROM users
            WHERE MONTH(created_at) = :month
              AND YEAR(created_at) = :year
        ');

        $stmt->execute(['month' => $month, 'year' => $year]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Estadísticas de reviews
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_reviews,
                COALESCE(AVG(rating), 0) as avg_rating
            FROM reviews
            WHERE MONTH(created_at) = :month
              AND YEAR(created_at) = :year
              AND status = 'approved'
        ");

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

    /**
     * Obtiene rendimiento de cafés en un rango de fechas
     *
     * @param string  $dateFrom Fecha inicio (Y-m-d)
     * @param string  $dateTo   Fecha fin (Y-m-d)
     * @param integer $limit    Límite de resultados
     * @return array
     */
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.category as type,
                COUNT(DISTINCT r.id) as total_reservations,
                SUM(r.guest_count) as total_guests,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed,
                COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) * 100.0) /
                    NULLIF(COUNT(DISTINCT r.id), 0),
                    2
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

    /**
     * Obtiene estadísticas de reservas por día en rango de fechas
     *
     * @param string $dateFrom Fecha inicio
     * @param string $dateTo   Fecha fin
     * @return array
     */
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

        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene distribución de reservas por tipo de café
     *
     * @param string $dateFrom Fecha inicio
     * @param string $dateTo   Fecha fin
     * @return array
     */
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

    /**
     * Obtiene distribución de usuarios por rol
     *
     * @return array
     */
    public function getUserDistributionByRole(): array
    {
        $stmt = $this->db->query('
            SELECT
                r.name as role_name,
                r.code as role_code,
                COUNT(DISTINCT ur.user_id) as user_count
            FROM roles r
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id, r.name, r.code
            ORDER BY user_count DESC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los cafés más populares
     *
     * @param string  $dateFrom Fecha inicio
     * @param string  $dateTo   Fecha fin
     * @param integer $limit    Número de resultados
     * @return array
     */
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.category as type,
                c.location,
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

        // Formatear ratings
        foreach ($results as &$cafe) {
            $cafe['avg_rating'] = \round((float) $cafe['avg_rating'], 2);
        }

        return $results;
    }

    /**
     * Obtiene resumen general para dashboard de reportes
     *
     * @param string $dateFrom Fecha inicio
     * @param string $dateTo   Fecha fin
     * @return array
     */
    public function getReportsSummary(string $dateFrom, string $dateTo): array
    {
        // Total reservas en el período
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM reservations
            WHERE reservation_date BETWEEN :date_from AND :date_to
        ');
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $totalReservations = (int) $stmt->fetchColumn();

        // Total invitados
        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM(guest_count), 0) FROM reservations
            WHERE reservation_date BETWEEN :date_from AND :date_to
        ');
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $totalGuests = (int) $stmt->fetchColumn();

        // Rating promedio
        $stmt = $this->db->prepare("
            SELECT COALESCE(AVG(rating), 0) FROM reviews
            WHERE status = 'approved'
              AND created_at BETWEEN :date_from AND :date_to
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $avgRating = \round((float) $stmt->fetchColumn(), 2);

        // Usuarios activos
        $stmt = $this->db->prepare('
            SELECT COUNT(DISTINCT user_id) FROM reservations
            WHERE reservation_date BETWEEN :date_from AND :date_to
              AND user_id IS NOT NULL
        ');
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $activeUsers = (int) $stmt->fetchColumn();

        return [
            'total_reservations' => $totalReservations,
            'total_guests' => $totalGuests,
            'avg_rating' => $avgRating,
            'active_users' => $activeUsers,
            'avg_guests_per_reservation' => $totalReservations > 0 ? \round($totalGuests / $totalReservations, 2) : 0,
        ];
    }

    /**
     * Obtiene actividad reciente del sistema (últimos eventos)
     *
     * @param integer $limit Número máximo de eventos
     * @return array
     */
    public function getRecentActivity(int $limit = 10): array
    {
        try {
            $activities = [];

            // Últimas reservas confirmadas (últimas 24h)
            $stmt = $this->db->query("
                SELECT
                    'reservation_confirmed' as type,
                    r.created_at,
                    c.name as cafe_name,
                    u.name as user_name
                FROM reservations r
                LEFT JOIN cafes c ON r.cafe_id = c.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.status = 'confirmed'
                  AND r.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY r.created_at DESC
                LIMIT 3
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

            // Nuevos usuarios registrados (últimas 24h)
            $stmt = $this->db->query('
                SELECT name, email, created_at
                FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 3
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

            // Reseñas pendientes de moderación
            $stmt = $this->db->query("
                SELECT r.created_at, c.name as cafe_name
                FROM reviews r
                LEFT JOIN cafes c ON r.cafe_id = c.id
                WHERE r.status = 'pending'
                ORDER BY r.created_at DESC
                LIMIT 2
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

            // Ordenar por timestamp y limitar
            \usort($activities, static function ($a, $b) {
                return \strtotime($b['timestamp']) - \strtotime($a['timestamp']);
            });

            return \array_slice($activities, 0, $limit);
        } catch (PDOException $e) {
            Logger::error('[AdminService] getRecentActivity ERROR: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Verifica el estado de los servicios del sistema
     *
     * @return array{database: string, cache: string, email: string}
     */
    public function getSystemStatus(): array
    {
        $status = [];

        // Database (MySQL)
        try {
            $this->db->query('SELECT 1')->execute();
            $status['database'] = 'online';
        } catch (PDOException $e) {
            Logger::error('[AdminService] Database check failed: ' . $e->getMessage());
            $status['database'] = 'offline';
        }

        // Cache (Redis)
        try {
            if (\extension_loaded('redis')) {
                $redis = new \Redis();
                $host = Env::get('REDIS_HOST', 'cache');
                $port = (int) Env::get('REDIS_PORT', '6379');
                $password = Env::get('REDIS_PASSWORD');

                if ($redis->connect($host, $port, 1)) {
                    // Autenticar si hay contraseña configurada
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
            Logger::error('[AdminService] Redis check failed: ' . $e->getMessage());
            $status['cache'] = 'offline';
        }

        // Email (SMTP)
        $smtpHost = Env::get('MAIL_HOST');
        if ($smtpHost && $smtpHost !== 'mailpit') {
            // Producción: verificar conexión SMTP real
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
            // Desarrollo: mailpit siempre disponible
            $status['email'] = 'online';
        }

        return $status;
    }

    /**
     * Convierte timestamp a formato "hace X tiempo"
     *
     * @param string $timestamp
     * @return string
     */
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
