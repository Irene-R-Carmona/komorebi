<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\CircuitBreaker;
use App\Core\Logger;
use App\Core\Result;
use App\Exceptions\CircuitOpenException;
use App\Services\Contracts\WeatherServiceInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Throwable;

/**
 * WeatherService
 *
 * Integración con Open-Meteo API (gratuita, sin autenticación).
 * Proporciona datos meteorológicos con caché Redis.
 *
 * - Caché: 3600 segundos (1 hora)
 * - Fallback: respuesta vacía si API falla
 * - Sin PII, completamente anónimo
 *
 * NOTA: Migrado a Result para consistencia con el resto de servicios
 */
final class WeatherService implements WeatherServiceInterface
{
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_TTL = Cache::TTL_HOUR; // 1 hora
    private const TIMEOUT = 10;

    private ?CacheItemPoolInterface $cache;

    public function __construct(?CacheItemPoolInterface $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Obtiene el clima actual y pronóstico para una ubicación.
     *
     * @param float       $latitude
     * @param float       $longitude
     * @param string|null $timezone  (ej: 'Asia/Tokyo'). Si null, usa UTC.
     * @param boolean     $hourly    Si true, retorna datos horarios. Si false, solo actuales.
     *
     * @return Result Data contiene ['current' => array, 'hourly' => array|null, 'cached' => bool]
     */
    #[Override]
    public function getWeather(
        float $latitude,
        float $longitude,
        ?string $timezone = null,
        bool $hourly = false
    ): Result {
        try {
            if (!$this->validateCoordinates($latitude, $longitude)) {
                return Result::fail('Coordenadas inválidas');
            }

            $cacheKey = $this->buildCacheKey($latitude, $longitude, $hourly);

            // Intentar caché
            $cached = $this->getFromCache($cacheKey);
            if ($cached) {
                return Result::ok($cached);
            }

            // Obtener de API
            $params = $this->buildWeatherParams($latitude, $longitude, $timezone, $hourly);
            $response = $this->fetchApi($params);

            if ($response->error !== null) {
                return $response;
            }

            // Procesar y cachear
            $result = $this->processWeatherResponse($response->data, $hourly);
            $this->saveToCache($cacheKey, $result);

            return Result::ok($result);
        } catch (Throwable $e) {
            return $this->handleWeatherError($e, $latitude, $longitude);
        }
    }

    /**
     * Valida coordenadas geográficas
     */
    private function validateCoordinates(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Construye clave de caché para ubicación.
     * Usa guiones bajos como separadores (PSR-6 prohíbe los dos puntos).
     * Incluye sufijo _h cuando se solicitan datos horarios para evitar colisión con la caché del sidebar.
     */
    private function buildCacheKey(float $latitude, float $longitude, bool $hourly = false): string
    {
        $key = 'weather_' . \round($latitude, 2) . '_' . \round($longitude, 2);

        return $hourly ? $key . '_h' : $key;
    }

    /**
     * Obtiene datos del caché si existen
     */
    private function getFromCache(string $cacheKey): ?array
    {
        if (!$this->cache) {
            return null;
        }

        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            $data = $item->get();
            $data['cached'] = true;

            return $data;
        }

        return null;
    }

    /**
     * Construye parámetros para API de clima
     */
    private function buildWeatherParams(
        float $latitude,
        float $longitude,
        ?string $timezone,
        bool $hourly
    ): array {
        $params = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
        ];

        if ($hourly) {
            $params['hourly']        = 'temperature_2m,precipitation,weather_code';
            $params['forecast_days'] = 16; // Open-Meteo máximo: 16 días
        }

        if ($timezone) {
            $params['timezone'] = $timezone;
        }

        return $params;
    }

    /**
     * Procesa respuesta de la API
     */
    private function processWeatherResponse(array $data, bool $hourly): array
    {
        $result = [
            'current' => $this->extractCurrentWeather($data),
            'hourly' => $hourly ? $this->extractHourlyWeather($data) : null,
            'cached' => false,
        ];

        return $result;
    }

    /**
     * Extrae datos de clima actual
     */
    private function extractCurrentWeather(array $data): ?array
    {
        if (!isset($data['current'])) {
            return null;
        }

        $current = $data['current'];

        return [
            'temp' => $current['temperature_2m'] ?? 0,
            'humidity' => $current['relative_humidity_2m'] ?? 0,
            'is_raining' => isset($current['weather_code'])
                && $this->isRaining((int) $current['weather_code']),
            'wind_speed' => $current['wind_speed_10m'] ?? 0,
            'weather_code' => (int) ($current['weather_code'] ?? 0),
        ];
    }

    /**
     * Extrae datos horarios
     */
    private function extractHourlyWeather(array $data): ?array
    {
        if (!isset($data['hourly'])) {
            return null;
        }

        return $this->formatHourlyData($data['hourly']);
    }

    /**
     * Guarda resultado en caché
     */
    private function saveToCache(string $cacheKey, array $result): void
    {
        if (!$this->cache) {
            return;
        }

        $item = $this->cache->getItem($cacheKey);
        $item->set($result);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }

    /**
     * Maneja errores de clima
     */
    private function handleWeatherError(Throwable $e, float $latitude, float $longitude): Result
    {
        Logger::error('Error al obtener información del clima', [
            'exception' => \get_class($e),
            'message' => $e->getMessage(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'trace' => $e->getTraceAsString(),
        ]);

        return Result::fail('Error al obtener información del clima');
    }

    /**
     * Obtiene el pronóstico para un rango de fechas.
     *
     * @param float       $latitude
     * @param float       $longitude
     * @param string      $startDate Formato: Y-m-d
     * @param string      $endDate   Formato: Y-m-d
     * @param string|null $timezone
     *
     * @return Result Data contiene ['forecast' => array, 'cached' => bool]
     */
    #[Override]
    public function getForecast(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        ?string $timezone = null
    ): Result {
        try {
            // Validaciones
            $validationResult = $this->validateForecastInput($latitude, $longitude, $startDate, $endDate);
            if (!$validationResult->ok) {
                return $validationResult;
            }

            // Intentar caché
            $cacheKey = $this->buildForecastCacheKey($latitude, $longitude, $startDate, $endDate);
            $cached = $this->getFromCache($cacheKey);
            if ($cached) {
                return Result::ok($cached);
            }

            // Obtener de API
            $params = $this->buildForecastParams($latitude, $longitude, $startDate, $endDate, $timezone);
            $response = $this->fetchApi($params);

            if ($response->error !== null) {
                return $response;
            }

            // Procesar y cachear
            $result = $this->processForecastResponse($response->data);
            $this->saveToCache($cacheKey, $result);

            return Result::ok($result);
        } catch (Throwable $e) {
            return $this->handleForecastError($e, $latitude, $longitude, $startDate, $endDate);
        }
    }

    /**
     * Valida parámetros de entrada para pronóstico
     */
    private function validateForecastInput(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate
    ): Result {
        if (!$this->validateCoordinates($latitude, $longitude)) {
            return Result::fail('Coordenadas inválidas');
        }

        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            return Result::fail('Fechas inválidas');
        }

        if ($startDate > $endDate) {
            return Result::fail('La fecha de inicio no puede ser posterior a la fecha final');
        }

        return Result::ok(null);
    }

    /**
     * Construye clave de caché para pronóstico
     */
    private function buildForecastCacheKey(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate
    ): string {
        return 'weather_forecast_'
            . \round($latitude, 2) . '_'
            . \round($longitude, 2) . '_'
            . $startDate . '_'
            . $endDate;
    }

    /**
     * Construye parámetros para API de pronóstico
     */
    private function buildForecastParams(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        ?string $timezone
    ): array {
        $params = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
            'temperature_unit' => 'celsius',
        ];

        if ($timezone) {
            $params['timezone'] = $timezone;
        }

        return $params;
    }

    /**
     * Procesa respuesta de pronóstico
     */
    private function processForecastResponse(array $data): array
    {
        return [
            'forecast' => $this->parseForecastResponse($data),
            'cached' => false,
        ];
    }

    /**
     * Maneja errores de pronóstico
     */
    private function handleForecastError(
        Throwable $e,
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate
    ): Result {
        Logger::error('Error al obtener pronóstico meteorológico', [
            'exception' => \get_class($e),
            'message' => $e->getMessage(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return Result::fail('No se pudo obtener el pronóstico');
    }

    /**
     * Petición HTTP a la API de Open-Meteo.
     *
     * @param array<string, mixed> $params
     *
     * @return Result
     */
    private function fetchApi(array $params): Result
    {
        $url = self::API_URL . '?' . \http_build_query($params);

        try {
            $response = CircuitBreaker::call('weather', function () use ($url): string {
                $ch = \curl_init($url);
                \curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                ]);

                $result = \curl_exec($ch);
                $error = \curl_error($ch);
                $httpCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
                \curl_close($ch);

                if ($error) {
                    throw new RuntimeException("cURL: {$error}");
                }

                if ($httpCode !== 200) {
                    throw new RuntimeException("HTTP {$httpCode}");
                }

                return (string) $result;
            });
        } catch (CircuitOpenException) {
            Logger::warning('[WeatherService] Circuit breaker abierto, servicio meteorológico no disponible');

            return Result::fail('Servicio meteorológico temporalmente no disponible');
        } catch (RuntimeException $e) {
            Logger::warning('Error de conexión con API meteorológica', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return Result::fail('Error de conexión con el servicio meteorológico');
        }

        $data = \json_decode((string) $response, true);

        if (!$data) {
            return Result::fail('Respuesta inválida del servicio meteorológico');
        }

        return Result::ok($data);
    }

    /**
     * Procesa la respuesta del pronóstico.
     *
     * @param array<string, mixed> $data
     * @return array Array de datos del pronóstico
     */
    private function parseForecastResponse(array $data): array
    {
        $daily = $data['daily'] ?? null;

        if (!$daily) {
            return [];
        }

        $dates = $daily['time'] ?? [];
        $maxTemps = $daily['temperature_2m_max'] ?? [];
        $minTemps = $daily['temperature_2m_min'] ?? [];
        $precips = $daily['precipitation_sum'] ?? [];

        $forecast = [];
        foreach ($dates as $index => $date) {
            $forecast[] = [
                'date' => $date,
                'max_temp' => (float) ($maxTemps[$index] ?? 0),
                'min_temp' => (float) ($minTemps[$index] ?? 0),
                'precip' => (float) ($precips[$index] ?? 0),
            ];
        }

        return $forecast;
    }

    /**
     * Formatea los datos horarios
     */
    private function formatHourlyData(array $hourly): array
    {
        $times = $hourly['time'] ?? [];
        $temps = $hourly['temperature_2m'] ?? [];
        $precips = $hourly['precipitation'] ?? [];
        $codes = $hourly['weather_code'] ?? [];

        $result = [];
        foreach ($times as $index => $time) {
            $result[] = [
                'time' => $time,
                'temp' => (float) ($temps[$index] ?? 0),
                'precip' => (float) ($precips[$index] ?? 0),
                'weather_code' => (int) ($codes[$index] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Determina si un código de clima indica lluvia
     */
    private function isRaining(int $weatherCode): bool
    {
        // Códigos WMO que indican lluvia
        $rainCodes = [51, 53, 55, 61, 63, 65, 80, 81, 82];

        return \in_array($weatherCode, $rainCodes, true);
    }

    /**
     * Valida si una fecha está en formato Y-m-d.
     */
    private function isValidDate(string $date): bool
    {
        return (bool) \preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
