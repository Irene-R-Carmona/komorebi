<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\Contracts\AdminReportServiceInterface;
use Override;

final class AdminReportService implements AdminReportServiceInterface
{
    private StatisticsRepositoryInterface $statsRepo;

    public function __construct(?StatisticsRepositoryInterface $statsRepo = null)
    {
        $this->statsRepo = $statsRepo ?? Container::make(StatisticsRepositoryInterface::class);
    }

    #[Override]
    public function getReportsSummary(string $dateFrom, string $dateTo): array
    {
        return $this->statsRepo->getReportsSummary($dateFrom, $dateTo);
    }
}
