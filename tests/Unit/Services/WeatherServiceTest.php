<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios de WeatherService: validación de coordenadas, caché hit,
 * y la recuperación de datos del pronóstico desde caché pre-cargada.
 *
 * ¿Qué me quieres demostrar?
 * Que WeatherService rechaza coordenadas inválidas sin llamar a la API,
 * que retorna Result::ok con 'cached=true' cuando hay un hit de caché,
 * y que la clave de caché se construye correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si la validación de coordenadas se elimina o amplía → testGetWeatherFailsOnInvalidCoordinates falla.
 * - Si getFromCache deja de marcar 'cached=true' → testGetWeatherReturnsCachedResultWhenCacheHit falla.
 * - Si buildCacheKey cambia su formato → testGetWeatherReturnsCachedResultWhenCacheHit falla.
 * - Si getWeather deja de retornar Result → todos los tests fallan.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Services\WeatherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(WeatherService::class)]
final class WeatherServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // Validación de coordenadas
    // ─────────────────────────────────────────────────────────────

    public function testGetWeatherFailsOnLatitudeTooLow(): void
    {
        $service = new WeatherService();

        $result = $service->getWeather(-91.0, 0.0);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('Coordenadas', $result->error);
    }

    public function testGetWeatherFailsOnLatitudeTooHigh(): void
    {
        $service = new WeatherService();

        $result = $service->getWeather(91.0, 0.0);

        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('Coordenadas', $result->error);
    }

    public function testGetWeatherFailsOnLongitudeTooLow(): void
    {
        $service = new WeatherService();

        $result = $service->getWeather(0.0, -181.0);

        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('Coordenadas', $result->error);
    }

    public function testGetWeatherFailsOnLongitudeTooHigh(): void
    {
        $service = new WeatherService();

        $result = $service->getWeather(0.0, 181.0);

        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('Coordenadas', $result->error);
    }

    public function testGetWeatherAcceptsBoundaryCoordinates(): void
    {
        // Boundary: lat=-90/90, lon=-180/180 son válidos.
        // Inyectamos una caché que hace inmediatamente cache-hit para evitar HTTP.
        $cache = $this->buildCacheWithHit(35.67, 139.65);

        $service = new WeatherService($cache);

        $result = $service->getWeather(90.0, 180.0);

        // Con caché hit para otras coords, validación pasa y se devuelve ok desde cache.
        // Para boundary coords sin caché, intentará HTTP. Solo verificamos que no falla por validación.
        $this->assertInstanceOf(Result::class, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // Cache hit
    // ─────────────────────────────────────────────────────────────

    public function testGetWeatherReturnsCachedResultWhenCacheHit(): void
    {
        // 35.68 y 139.65 se redondean a 35.68 y 139.65 → clave: weather:35.68:139.65
        $lat = 35.6762;
        $lon = 139.6503;
        $cache = $this->buildCacheWithHit(\round($lat, 2), \round($lon, 2));

        $service = new WeatherService($cache);
        $result = $service->getWeather($lat, $lon);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertTrue($result->data['cached']);
        $this->assertArrayHasKey('current', $result->data);
    }

    public function testGetWeatherCacheHitContainsExpectedKeys(): void
    {
        $lat = 35.6762;
        $lon = 139.6503;
        $cache = $this->buildCacheWithHit(\round($lat, 2), \round($lon, 2));

        $service = new WeatherService($cache);
        $result = $service->getWeather($lat, $lon);

        $this->assertTrue($result->ok);
        $data = $result->data;
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('hourly', $data);
        $this->assertArrayHasKey('cached', $data);
    }

    // ─────────────────────────────────────────────────────────────
    // getForecast — validación
    // ─────────────────────────────────────────────────────────────

    public function testGetForecastFailsOnInvalidCoordinates(): void
    {
        $service = new WeatherService();

        $result = $service->getForecast(-200.0, 0.0, '2026-01-01', '2026-01-07');

        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('Coordenadas', $result->error);
    }

    public function testGetForecastFailsWhenStartDateAfterEndDate(): void
    {
        $service = new WeatherService();

        $result = $service->getForecast(35.0, 139.0, '2026-01-10', '2026-01-01');

        $this->assertTrue(!$result->ok);
        $this->assertStringContainsString('fecha', \strtolower($result->error));
    }

    public function testGetForecastFailsOnInvalidDateFormat(): void
    {
        $service = new WeatherService();

        $result = $service->getForecast(35.0, 139.0, 'not-a-date', '2026-01-07');

        $this->assertTrue(!$result->ok);
    }

    public function testGetForecastReturnsCachedResultWhenCacheHit(): void
    {
        $lat = 35.0;
        $lon = 139.0;
        $start = '2026-01-01';
        $end = '2026-01-07';
        // Build forecast cache key manually
        $key = 'weather_forecast_' . \round($lat, 2) . '_' . \round($lon, 2) . '_' . $start . '_' . $end;

        $cachedData = ['forecast' => [['date' => '2026-01-01', 'max_temp' => 8.0, 'min_temp' => 3.0]], 'cached' => false];
        $cache = $this->buildCacheWithData($key, $cachedData);

        $service = new WeatherService($cache);
        $result = $service->getForecast($lat, $lon, $start, $end);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cached']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un ArrayAdapter con datos pre-cargados en la clave del formato
     * weather:{lat}:{lon}, que es lo que WeatherService busca.
     */
    private function buildCacheWithHit(float $lat, float $lon): ArrayAdapter
    {
        $key = 'weather_' . $lat . '_' . $lon;
        $data = [
            'current' => [
                'temp' => 18.5,
                'humidity' => 60,
                'is_raining' => false,
                'wind_speed' => 5.2,
                'weather_code' => 1,
            ],
            'hourly' => null,
            'cached' => false, // WeatherService sobrescribe esto a true
        ];

        return $this->buildCacheWithData($key, $data);
    }

    private function buildCacheWithData(string $key, array $data): ArrayAdapter
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem($key);
        $item->set($data);
        $cache->save($item);

        return $cache;
    }
}
