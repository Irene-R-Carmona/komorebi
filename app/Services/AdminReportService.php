<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\Contracts\AdminReportServiceInterface;
use PDO;

final class AdminReportService implements AdminReportServiceInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    #[\Override]
    public function getReportsSummary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $totalReservations = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('SELECT COALESCE(SUM(guest_count), 0) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to');
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $totalGuests = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE status = 'approved' AND created_at BETWEEN :date_from AND :date_to");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $avgRating = \round((float) $stmt->fetchColumn(), 2);

        $stmt = $this->db->prepare('SELECT COUNT(DISTINCT user_id) FROM reservations WHERE reservation_date BETWEEN :date_from AND :date_to AND user_id IS NOT NULL');
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
}
