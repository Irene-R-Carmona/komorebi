<?php

declare(strict_types=1);

namespace App\Services\Manager;

use App\Core\Database;
use PDO;

/**
 * Servicio de Dashboard para Manager
 *
 * Proporciona métricas en tiempo real para el panel de control del gestor.
 * Todas las consultas están scopeadas al café asignado al manager.
 */
class DashboardService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtiene todas las métricas del dashboard en una sola llamada
     */
    public function getDashboardMetrics(int $cafeId): array
    {
        return [
            'reservations_today' => $this->getReservationsToday($cafeId),
            'revenue_today' => $this->getRevenueToday($cafeId),
            'active_staff' => $this->getActiveStaffCount($cafeId),
            'animals_count' => $this->getAnimalsCount($cafeId),
            'weekly_revenue' => $this->getWeeklyRevenue($cafeId),
            'monthly_reservations' => $this->getMonthlyReservationsCount($cafeId),
            'avg_rating' => $this->getAverageRating($cafeId),
            'pending_reservations' => $this->getPendingReservationsCount($cafeId),
        ];
    }

    /**
     * Número de reservas de hoy para el café
     */
    public function getReservationsToday(int $cafeId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = CURDATE()
             AND status IN ('confirmed', 'active', 'completed')
             AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Ingresos totales de hoy (reservas completadas)
     */
    public function getRevenueToday(int $cafeId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(final_amount), 0) AS revenue
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date = CURDATE()
             AND status = 'completed'
             AND payment_status = 'paid'
             AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float) ($result['revenue'] ?? 0.0);
    }

    /**
     * Número de staff activo asignado al café
     */
    public function getActiveStaffCount(int $cafeId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT u.id) AS total
             FROM users u
             INNER JOIN user_roles ur ON u.id = ur.user_id
             INNER JOIN roles r ON ur.role_id = r.id
             WHERE u.cafe_id = :cafe_id
             AND r.code IN ('manager', 'keeper', 'staff')
             AND u.is_active = 1"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Número total de animales en el café
     */
    public function getAnimalsCount(int $cafeId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM animals
             WHERE cafe_id = :cafe_id
             AND current_status = 'active'"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Ingresos de los últimos 7 días (para gráfico Chart.js)
     */
    public function getWeeklyRevenue(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                reservation_date AS date,
                COALESCE(SUM(final_amount), 0) AS revenue
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             AND status = 'completed'
             AND payment_status = 'paid'
             AND deleted_at IS NULL
             GROUP BY reservation_date
             ORDER BY reservation_date"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Número de reservas por mes actual
     */
    public function getMonthlyReservationsCount(int $cafeId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND MONTH(reservation_date) = MONTH(CURDATE())
             AND YEAR(reservation_date) = YEAR(CURDATE())
             AND status IN ('confirmed', 'active', 'completed')
             AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Promedio de rating del café
     */
    public function getAverageRating(int $cafeId): float
    {
        $stmt = $this->db->prepare(
            "SELECT rating_avg
             FROM cafes
             WHERE id = :cafe_id"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float) ($result['rating_avg'] ?? 0.0);
    }

    /**
     * Reservas pendientes de confirmación
     */
    public function getPendingReservationsCount(int $cafeId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND status = 'pending'
             AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['total'] ?? 0);
    }

    /**
     * Obtiene los 5 animales más populares (más interacciones)
     */
    public function getTopAnimals(int $cafeId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                a.id,
                a.name,
                a.species_type,
                COUNT(ais.id) AS interaction_count
             FROM animals a
             LEFT JOIN interaction_sessions ais ON a.id = ais.animal_id
             WHERE a.cafe_id = :cafe_id
             AND a.current_status = 'active'
             GROUP BY a.id, a.name, a.species_type
             ORDER BY interaction_count DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Distribución de estados de reservas (para gráfico de dona)
     */
    public function getReservationStatusDistribution(int $cafeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                status,
                COUNT(*) AS count
             FROM reservations
             WHERE cafe_id = :cafe_id
             AND reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             AND deleted_at IS NULL
             GROUP BY status"
        );
        $stmt->execute(['cafe_id' => $cafeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listado de reservas en un rango de fechas para el café del manager.
     *
     * Uso en reportes web (paginado) y exportación CSV (sin límite).
     * SEGURIDAD: siempre filtra por cafe_id; nunca devuelve datos de otros cafés.
     *
     * @param int         $cafeId   ID del café del manager (obligatorio)
     * @param string|null $from     Fecha inicio (YYYY-MM-DD); por defecto últimos 30 días
     * @param string|null $to       Fecha fin   (YYYY-MM-DD); por defecto hoy
     * @param int|null    $limit    Máximo de filas (null = sin límite, para export)
     */
    public function getReservationReport(
        int $cafeId,
        ?string $from = null,
        ?string $to = null,
        ?int $limit = 100,
    ): array {
        $from = $from ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $to   ?? date('Y-m-d');

        $sql = "SELECT
                    r.id,
                    r.reservation_date  AS fecha,
                    r.status            AS estado,
                    r.guest_count       AS personas,
                    COALESCE(r.final_amount, 0) AS importe,
                    r.payment_status    AS pago
                FROM reservations r
                WHERE r.cafe_id = :cafe_id
                AND r.reservation_date BETWEEN :from AND :to
                AND r.deleted_at IS NULL
                ORDER BY r.reservation_date DESC, r.id DESC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
