<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Http\Controllers\Api\AbstractApiController;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * API V1 - Holidays Controller
 *
 * Gestiona festivos nacionales de España via date.nager.at.
 * Caché Redis 24h por año. Devuelve holiday_name en inglés
 * para que el JS lo mapee en getHolidayDescription().
 */
final class HolidayController extends AbstractApiController
{
    private const API_BASE   = 'https://date.nager.at/api/v3';
    private const COUNTRY    = 'ES'; // Festivos nacionales de España
    private const CACHE_TTL  = 86400; // 24 horas
    private const TIMEOUT    = 10;   // segundos

    private ?CacheItemPoolInterface $cache;

    public function __construct(ResponseFactory $response, ?CacheItemPoolInterface $cache = null)
    {
        parent::__construct($response);
        $this->cache = $cache;
    }

    /**
     * GET /api/v1/holidays
     * Próximos festivos nacionales de España (desde hoy en adelante).
     */
    public function getHolidays(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $year     = (int) \date('Y');
            $holidays = $this->fetchHolidays($year);

            // Incluir año siguiente si estamos en noviembre o diciembre
            if ((int) \date('n') >= 11) {
                $holidays = \array_merge($holidays, $this->fetchHolidays($year + 1));
            }

            $today    = \date('Y-m-d');
            $upcoming = \array_values(\array_filter($holidays, static fn(array $h) => $h['date'] >= $today));

            $data = ['holidays' => $upcoming, 'count' => \count($upcoming)];
            $etag = $this->makeEtag($data);
            $cc   = 'public, max-age=3600';

            if ($request->getHeaderLine('If-None-Match') === $etag) {
                return $this->notModified($etag, $cc);
            }

            return $this->success($data, 200, ['Cache-Control' => $cc, 'ETag' => $etag]);
        } catch (Throwable $e) {
            Logger::error('[HolidayController] Error en getHolidays', ['message' => $e->getMessage()]);

            return $this->success(['holidays' => [], 'count' => 0]);
        }
    }

    /**
     * GET /api/v1/holidays/{date}
     * Verifica si una fecha concreta es festivo nacional en España.
     * Devuelve holiday_name (inglés) para que getHolidayDescription() del JS lo mapee.
     */
    public function checkHoliday(ServerRequestInterface $request): ResponseInterface
    {
        $date = (string) ($request->getAttribute('date') ?? '');

        if ($date === '') {
            return $this->unprocessable('Parámetro "date" requerido (formato: YYYY-MM-DD)', 'missing_parameter');
        }

        if (\strtotime($date) === false) {
            return $this->unprocessable('Formato de fecha inválido. Use YYYY-MM-DD', 'invalid_date_format');
        }

        try {
            $year     = (int) \substr($date, 0, 4);
            $holidays = $this->fetchHolidays($year);

            $matched = null;
            foreach ($holidays as $holiday) {
                if ($holiday['date'] === $date) {
                    $matched = $holiday;
                    break;
                }
            }

            $isHoliday = $matched !== null;

            return $this->success([
                'date'                       => $date,
                'is_holiday'                 => $isHoliday,
                'holiday_name'               => $isHoliday ? ($matched['name'] ?? null) : null,
                'available_for_reservations' => !$isHoliday,
            ]);
        } catch (Throwable $e) {
            Logger::error('[HolidayController] Error en checkHoliday', [
                'date'    => $date,
                'message' => $e->getMessage(),
            ]);

            // Degradación elegante: la fecha no bloquea reservas si falla la API
            return $this->success([
                'date'                       => $date,
                'is_holiday'                 => false,
                'holiday_name'               => null,
                'available_for_reservations' => true,
            ]);
        }
    }

    /**
     * Obtiene festivos nacionales de España del año dado desde date.nager.at, con caché Redis 24h.
     * `name` = nombre en inglés (para el mapeo en JS), `localName` = nombre en español.
     *
     * @return array<int, array{date: string, name: string, localName: string}>
     */
    private function fetchHolidays(int $year): array
    {
        $cacheKey = 'holidays_' . self::COUNTRY . "_{$year}";

        if ($this->cache !== null) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<int, array{date: string, name: string, localName: string}> $cached */
                $cached = $item->get();

                return $cached;
            }
        }

        $url = self::API_BASE . '/PublicHolidays/' . $year . '/' . self::COUNTRY;
        $ch  = \curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL para date.nager.at');
        }

        \curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT        => self::TIMEOUT,
            \CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $result   = \curl_exec($ch);
        $curlErr  = \curl_error($ch);
        $httpCode = (int) \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($curlErr !== '' || $httpCode !== 200 || !\is_string($result)) {
            throw new RuntimeException('date.nager.at/' . self::COUNTRY . " error ({$httpCode}): {$curlErr}");
        }

        /** @var mixed $raw */
        $raw = \json_decode($result, true);

        if (!\is_array($raw)) {
            throw new RuntimeException('Respuesta JSON inválida de date.nager.at');
        }

        /** @var array<int, array{date: string, name: string, localName: string, global: bool}> $raw */
        $globalOnly = \array_filter($raw, static fn(array $h): bool => $h['global'] === true);
        $holidays = \array_values(\array_map(
            static fn(array $item): array => [
                'date'      => $item['date'],
                'name'      => $item['name'],
                'localName' => $item['localName'],
            ],
            $globalOnly
        ));

        if ($this->cache !== null) {
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($holidays);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
        }

        return $holidays;
    }
}
