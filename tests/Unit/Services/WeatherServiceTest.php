<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? WeatherService: validación de coordenadas, caché, pronóstico y circuit breaker.
 * ¿Qué me quieres demostrar? Que getWeather y getForecast retornan resultados correctos en cada rama.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina validación, caché o manejo de errores.
 */

namespace Tests\Unit\Services;

use App\Core\CircuitBreaker;
use App\Services\WeatherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(WeatherService::class)]
final class WeatherServiceTest extends TestCase
{
    private WeatherService $service;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->service = new WeatherService($this->cache);
    }

    protected function tearDown(): void
    {
        CircuitBreaker::reset('weather');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getWeather — validaciones de coordenadas
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetWeatherFailsWithInvalidLatitude(): void
    {
        $result = $this->service->getWeather(200.0, 0.0);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválidas', $result->error);
    }

    public function testGetWeatherFailsWithInvalidLongitude(): void
    {
        $result = $this->service->getWeather(0.0, 300.0);

        $this->assertFalse($result->ok);
    }

    public function testGetWeatherFailsWithLatitudeTooLow(): void
    {
        $result = $this->service->getWeather(-91.0, 0.0);

        $this->assertFalse($result->ok);
    }

    public function testGetWeatherFailsWithLongitudeTooLow(): void
    {
        $result = $this->service->getWeather(0.0, -181.0);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getWeather — caché hit
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetWeatherReturnsCachedData(): void
    {
        $cachedData = [
            'current' => ['temp' => 22.5, 'humidity' => 65, 'is_raining' => false, 'wind_speed' => 12.0, 'weather_code' => 1],
            'hourly' => null,
            'cached' => false,
        ];

        $cacheKey = 'weather_35.68_139.69';
        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedData);
        $this->cache->save($item);

        $result = $this->service->getWeather(35.68, 139.69);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cached']);
        $this->assertSame(22.5, $result->data['current']['temp']);
    }

    public function testGetWeatherReturnsCachedDataWithRoundedCoordinates(): void
    {
        $cachedData = ['current' => ['temp' => 18.0], 'hourly' => null, 'cached' => false];
        $cacheKey = 'weather_35.69_139.69'; // round(35.685, 2) = 35.69
        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedData);
        $this->cache->save($item);

        $result = $this->service->getWeather(35.685, 139.685);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cached']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getWeather — sin caché, circuit breaker abierto
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetWeatherFailsWhenCircuitBreakerIsOpen(): void
    {
        // Force circuit abierto para simular servicio no disponible
        CircuitBreaker::forceOpenAt('weather', \time() - 1);

        $result = $this->service->getWeather(35.68, 139.69);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('temporalmente no disponible', $result->error);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getWeather — sin caché, sin circuit breaker: error de red
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetWeatherWithoutCacheNeverThrows(): void
    {
        // Sin caché → se intenta la llamada a la API; nunca debe lanzar excepción
        $serviceNoCache = new WeatherService(null);

        $result = $serviceNoCache->getWeather(35.68, 139.69);

        // Independientemente del resultado (red disponible o no), devuelve Result
        $this->assertIsBool($result->ok);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getForecast — validaciones
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetForecastFailsWithInvalidCoordinates(): void
    {
        $result = $this->service->getForecast(200.0, 0.0, '2025-01-01', '2025-01-07');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválidas', $result->error);
    }

    public function testGetForecastFailsWithInvalidStartDate(): void
    {
        $result = $this->service->getForecast(35.68, 139.69, '2025/01/01', '2025-01-07');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Fechas inválidas', $result->error);
    }

    public function testGetForecastFailsWithInvalidEndDate(): void
    {
        $result = $this->service->getForecast(35.68, 139.69, '2025-01-01', '01/07/2025');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Fechas inválidas', $result->error);
    }

    public function testGetForecastFailsWhenStartDateAfterEndDate(): void
    {
        $result = $this->service->getForecast(35.68, 139.69, '2025-01-10', '2025-01-01');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inicio', $result->error);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getForecast — caché hit
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetForecastReturnsCachedData(): void
    {
        $cachedData = ['forecast' => [['date' => '2025-01-01', 'max_temp' => 20.0, 'min_temp' => 10.0, 'precip' => 0.0]], 'cached' => false];
        $cacheKey = 'weather_forecast_35.68_139.69_2025-01-01_2025-01-07';
        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedData);
        $this->cache->save($item);

        $result = $this->service->getForecast(35.68, 139.69, '2025-01-01', '2025-01-07');

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cached']);
    }

    public function testGetForecastReturnsCachedDataWithTimezone(): void
    {
        $cachedData = ['forecast' => [], 'cached' => false];
        $cacheKey = 'weather_forecast_35.68_139.69_2025-06-01_2025-06-07';
        $item = $this->cache->getItem($cacheKey);
        $item->set($cachedData);
        $this->cache->save($item);

        $result = $this->service->getForecast(35.68, 139.69, '2025-06-01', '2025-06-07', 'Asia/Tokyo');

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cached']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getForecast — circuit breaker abierto
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetForecastFailsWhenCircuitBreakerIsOpen(): void
    {
        CircuitBreaker::forceOpenAt('weather', \time() - 1);

        $result = $this->service->getForecast(35.68, 139.69, '2025-01-01', '2025-01-07');

        $this->assertFalse($result->ok);
    }
}
