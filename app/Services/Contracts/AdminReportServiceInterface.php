<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AdminReportServiceInterface
{
    public function getReportsSummary(string $dateFrom, string $dateTo): Result;
}
