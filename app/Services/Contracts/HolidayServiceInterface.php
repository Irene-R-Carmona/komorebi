<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface HolidayServiceInterface
{
    public function getHolidaysByYear(int $year): Result;

    public function getHolidaysByRange(int $startYear, int $endYear): Result;

    public function isHoliday(string $date): Result;

    public function getUpcomingHolidays(int $limit = 5): Result;
}
