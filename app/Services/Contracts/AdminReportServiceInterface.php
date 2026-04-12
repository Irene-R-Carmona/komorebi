<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface AdminReportServiceInterface
{
    public function getReportsSummary(string $dateFrom, string $dateTo): array;
}
