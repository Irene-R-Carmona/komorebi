<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface StatisticsRepositoryInterface
{
    /** @return array{users: int, cafes: int, reservations: int, reviews: int, pending_reviews: int} */
    public function getSystemCounts(): array;

    /** @return array{current_week: int, previous_week: int} */
    public function getWeeklyUserCounts(): array;

    /** @return array{current_week: int, previous_week: int} */
    public function getWeeklyReservationCounts(): array;

    /** @return array<string, mixed> */
    public function getMonthlyStats(int $month, int $year): array;

    /** @return array<int, array<string, mixed>> */
    public function getCafePerformanceStats(string $dateFrom, string $dateTo, int $limit = 10): array;

    /** @return array<int, array<string, mixed>> */
    public function getReservationTrendStats(string $dateFrom, string $dateTo): array;

    /** @return array<int, array<string, mixed>> */
    public function getReservationsByCafeType(string $dateFrom, string $dateTo): array;

    /** @return array<int, array<string, mixed>> */
    public function getUserDistributionByRole(): array;

    /** @return array<int, array<string, mixed>> */
    public function getTopCafes(string $dateFrom, string $dateTo, int $limit = 10): array;

    /**
     * Estadísticas agregadas de cafés (total, activos, con reservas, categorías, tipos animal).
     *
     * @return array<string, int>|false
     */
    public function getCafeStats(): array|false;

    /**
     * Resumen de reservas y reseñas entre dos fechas para informes admin.
     *
     * @return array{total_reservations: int, total_guests: int, avg_rating: float, active_users: int, avg_guests_per_reservation: float}
     */
    public function getReportsSummary(string $dateFrom, string $dateTo): array;

    /**
     * Contadores de entidades para el DataViewer admin.
     *
     * @return array<string, int>
     */
    public function getDataViewerStats(): array;

    /**
     * Muestras de datos para el DataViewer admin.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getDataViewerSamples(): array;

    /** @return array<int, array<string, mixed>> */
    public function getRecentReservations(int $limit = 10): array;

    /** @return array<int, array<string, mixed>> */
    public function getReservationsWithDetails(int $limit = 100): array;

    /** @return array<int, array<string, mixed>> */
    public function getProductsWithCategories(): array;

    /** @return array<int, array<string, mixed>> */
    public function getRecentActivity(int $limit = 10): array;

    /** @return array{labels: array<int, string>, values: array<int, int>} */
    public function getReservationsChartData(): array;
}
