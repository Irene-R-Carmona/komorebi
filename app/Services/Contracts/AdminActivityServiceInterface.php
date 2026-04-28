<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AdminActivityServiceInterface
{
    public function getRecentReservations(int $limit = 10): Result;

    public function getUsersWithRoles(): Result;

    public function getProductsWithCategories(): Result;

    public function getReservationsWithDetails(int $limit = 100): Result;

    public function getRecentActivity(int $limit = 10): Result;

    public function getSystemStatus(): Result;

    public function getReservationsChartData(): Result;
}
