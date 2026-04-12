<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface AdminActivityServiceInterface
{
    public function getRecentReservations(int $limit = 10): array;

    public function getUsersWithRoles(): array;

    public function getProductsWithCategories(): array;

    public function getReservationsWithDetails(int $limit = 100): array;

    public function getRecentActivity(int $limit = 10): array;

    /**
     * @return array{database: string, cache: string, email: string}
     */
    public function getSystemStatus(): array;

    /**
     * @return array{labels: array, values: array}
     */
    public function getReservationsChartData(): array;
}
