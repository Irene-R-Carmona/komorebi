<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Result;
use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\Contracts\AdminReportServiceInterface;
use Override;
use PDOException;

final class AdminReportService implements AdminReportServiceInterface
{
    private StatisticsRepositoryInterface $statsRepo;

    public function __construct(?StatisticsRepositoryInterface $statsRepo = null)
    {
        $this->statsRepo = $statsRepo ?? Container::make(StatisticsRepositoryInterface::class);
    }

    #[Override]
    public function getReportsSummary(string $dateFrom, string $dateTo): Result
    {
        try {
            return Result::ok($this->statsRepo->getReportsSummary($dateFrom, $dateTo));
        } catch (PDOException $e) {
            return Result::fail($e->getMessage(), 'db_error');
        }
    }
}
