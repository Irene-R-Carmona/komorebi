<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface WeatherServiceInterface
{
    public function getWeather(
        float $latitude,
        float $longitude,
        ?string $timezone = null,
        bool $hourly = false
    ): Result;

    public function getForecast(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        ?string $timezone = null
    ): Result;
}
