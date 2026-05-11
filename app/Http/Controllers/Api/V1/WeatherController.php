<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use App\Services\Contracts\WeatherServiceInterface;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * GET /api/v1/weather
 *
 * Devuelve el tiempo meteorológico para las coordenadas del café (España)
 * y, opcionalmente, para el barrio de referencia estética en Tokio.
 */
final class WeatherController extends AbstractApiController
{
    /** @var array<string, array{lat: float, lon: float}> */
    private const THEMED_DISTRICTS = [
        'Shibuya' => ['lat' => 35.6595, 'lon' => 139.7004],
        'Harajuku' => ['lat' => 35.6702, 'lon' => 139.7026],
        'Shinjuku' => ['lat' => 35.6938, 'lon' => 139.7036],
        'Kichijoji' => ['lat' => 35.7014, 'lon' => 139.5878],
        'Yoyogi' => ['lat' => 35.6762, 'lon' => 139.6943],
        'Meguro' => ['lat' => 35.6454, 'lon' => 139.7297],
        'Ueno' => ['lat' => 35.7149, 'lon' => 139.7736],
        'Odaiba' => ['lat' => 35.6293, 'lon' => 139.7752],
        'Setagaya' => ['lat' => 35.6456, 'lon' => 139.6215],
        'Ikebukuro' => ['lat' => 35.7295, 'lon' => 139.7107],
        'Nakano' => ['lat' => 35.7053, 'lon' => 139.6655],
        'Roppongi' => ['lat' => 35.6627, 'lon' => 139.7305],
    ];

    public function __construct(
        ResponseFactory $response,
        private readonly WeatherServiceInterface $weather,
    ) {
        parent::__construct($response);
    }

    public function getWeather(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $lat = \filter_var($params['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = \filter_var($params['lon'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($lat === false || $lon === false) {
            return $this->unprocessable(
                'Los parámetros lat y lon son requeridos y deben ser números válidos',
                'invalid_coordinates'
            );
        }

        $tz = \is_string($params['timezone'] ?? null) && $params['timezone'] !== ''
            ? $params['timezone']
            : 'Europe/Madrid';

        try {
            new DateTimeZone($tz);
        } catch (Throwable) {
            return $this->unprocessable('El parámetro timezone no es válido', 'invalid_timezone');
        }

        $date = null;
        $dateParam = $params['date'] ?? null;
        if (\is_string($dateParam) && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
            $date = $dateParam;
        }

        $localResult = $this->weather->getWeather((float) $lat, (float) $lon, $tz, $date !== null);

        if (!$localResult->ok) {
            return $this->success(['local' => null, 'tokyo_reference' => null]);
        }

        // Construir datos locales con previsión filtrada por fecha si se solicitó
        $localData = $localResult->data;
        $dateForecast = null;
        $forecastUnavailable = false;

        if ($date !== null) {
            $hourlyEntries = $localData['hourly'] ?? null;
            if (\is_array($hourlyEntries)) {
                $dayEntries = \array_values(\array_filter(
                    $hourlyEntries,
                    static fn (array $h): bool => \str_starts_with($h['time'], $date)
                ));
                if (\count($dayEntries) > 0) {
                    $dateForecast = $dayEntries;
                } else {
                    $forecastUnavailable = true;
                }
            } else {
                $forecastUnavailable = true;
            }
        }

        $localData['date_forecast'] = $dateForecast;
        $localData['forecast_unavailable'] = $forecastUnavailable;
        unset($localData['hourly']); // no exponer 16 días completos al cliente

        $tokyoData = null;
        $district = $params['themed_district'] ?? null;

        if (\is_string($district) && isset(self::THEMED_DISTRICTS[$district])) {
            $coords = self::THEMED_DISTRICTS[$district];
            $tokyoResult = $this->weather->getWeather($coords['lat'], $coords['lon'], 'Asia/Tokyo');

            if ($tokyoResult->ok) {
                $tokyoData = $tokyoResult->data;
                $tokyoData['district'] = $district;
            }
        }

        return $this->success([
            'local' => $localData,
            'tokyo_reference' => $tokyoData,
        ]);
    }
}
