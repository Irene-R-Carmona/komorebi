<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Result;
use App\Services\Contracts\HolidayServiceInterface;
use Throwable;

/**
 * HolidayService
 *
 * Integración con Nager.Date API (gratuita, sin autenticación).
 * Proporciona días festivos de Japón mediante API externa.
 *
 * - Caché: 86400 segundos (24 horas)
 * - API: https://date.nager.at (festivos públicos mundiales)
 * - Sin PII, completamente anónimo
 *
 * NOTA: Migrado a Result para consistencia con el resto de servicios
 */
final class HolidayService implements HolidayServiceInterface
{
    private const API_URL = 'https://date.nager.at/api/v3/publicholidays';
    private const COUNTRY_CODE = 'JP';
    private const CACHE_TTL = 86400; // 24 horas
    private const TIMEOUT = 10;

    private ?CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * Obtiene los festivos de un año para Japón desde API externa
     *
     * @param integer $year
     * @return Result Data contiene ['holidays' => array, 'cached' => bool]
     */
    #[\Override]
    public function getHolidaysByYear(int $year): Result
    {
        try {
            // Validar año
            $currentYear = (int) \date('Y');
            if ($year < $currentYear - 1 || $year > $currentYear + 5) {
                return Result::fail('Año fuera de rango permitido (máximo 5 años en el futuro)');
            }

            // Clave de caché
            $cacheKey = 'holidays:' . self::COUNTRY_CODE . ':' . $year;

            // Intentar obtener del caché (PSR-6)
            if ($this->cache) {
                $item = $this->cache->getItem($cacheKey);
                if ($item->isHit()) {
                    $data = $item->get();
                    $data['cached'] = true;

                    return Result::ok($data);
                }
            }

            // Realizar petición a la API externa
            $response = $this->fetchApi($year);

            if ($response->isFail()) {
                return $response;
            }

            // Procesar respuesta
            $holidays = $this->parseHolidayResponse($response->data);

            $result = [
                'holidays' => $holidays,
                'cached' => false,
            ];

            // Guardar en caché (24 horas) PSR-6
            if ($this->cache) {
                $item = $this->cache->getItem($cacheKey);
                $item->set($result);
                $item->expiresAfter(self::CACHE_TTL);
                $this->cache->save($item);
            }

            return Result::ok($result);
        } catch (Throwable $e) {
            Logger::error('[HolidayService] Error: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail('No se pudo obtener festivos');
        }
    }

    /**
     * Obtiene los festivos para un rango de años
     *
     * @param integer $startYear
     * @param integer $endYear
     * @return Result Data contiene ['holidays' => array]
     */
    #[\Override]
    public function getHolidaysByRange(int $startYear, int $endYear): Result
    {
        try {
            if ($startYear > $endYear) {
                return Result::fail('Rango de años inválido');
            }

            // Limitar a máximo 5 años
            if (($endYear - $startYear) > 5) {
                return Result::fail('El rango no puede exceder 5 años');
            }

            $allHolidays = [];

            for ($year = $startYear; $year <= $endYear; $year++) {
                $response = $this->getHolidaysByYear($year);

                if ($response->ok && !empty($response->data['holidays'])) {
                    $allHolidays = \array_merge($allHolidays, $response->data['holidays']);
                }
            }

            if (empty($allHolidays)) {
                return Result::fail('No se obtuvieron festivos para el rango especificado');
            }

            // Ordenar por fecha
            \usort($allHolidays, static fn($a, $b) => \strcmp($a['date'], $b['date']));

            return Result::ok(['holidays' => $allHolidays]);
        } catch (Throwable $e) {
            Logger::error('[HolidayService] Error en rango: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail('Error al obtener festivos para el rango');
        }
    }

    /**
     * Verifica si una fecha específica es festivo en Japón
     *
     * @param string $date Formato: Y-m-d
     * @return Result Data contiene ['is_holiday' => bool, 'holiday' => array|null]
     */
    #[\Override]
    public function isHoliday(string $date): Result
    {
        try {
            // Validar formato de fecha
            if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return Result::fail('Formato de fecha inválido. Use Y-m-d');
            }

            $year = (int) \substr($date, 0, 4);
            $response = $this->getHolidaysByYear($year);

            if ($response->isFail()) {
                return $response;
            }

            $holidays = $response->data['holidays'];

            // Buscar si la fecha es festivo
            foreach ($holidays as $holiday) {
                if ($holiday['date'] === $date) {
                    return Result::ok([
                        'is_holiday' => true,
                        'holiday' => $holiday,
                    ]);
                }
            }

            return Result::ok([
                'is_holiday' => false,
                'holiday' => null,
            ]);
        } catch (Throwable $e) {
            Logger::error('[HolidayService] Error verificando festivo: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail('Error al verificar si es festivo');
        }
    }

    /**
     * Obtiene próximos festivos desde hoy
     *
     * @param integer $limit Número máximo de festivos a retornar
     * @return Result Data contiene ['holidays' => array]
     */
    #[\Override]
    public function getUpcomingHolidays(int $limit = 5): Result
    {
        try {
            $today = \date('Y-m-d');
            $currentYear = (int) \date('Y');
            $nextYear = $currentYear + 1;

            // Obtener festivos del año actual y siguiente
            $currentYearResult = $this->getHolidaysByYear($currentYear);
            $nextYearResult = $this->getHolidaysByYear($nextYear);

            $allHolidays = [];

            if ($currentYearResult->ok) {
                $allHolidays = \array_merge($allHolidays, $currentYearResult->data['holidays']);
            }

            if ($nextYearResult->ok) {
                $allHolidays = \array_merge($allHolidays, $nextYearResult->data['holidays']);
            }

            // Filtrar solo festivos futuros
            $upcomingHolidays = \array_filter($allHolidays, static fn($h) => $h['date'] >= $today);

            // Ordenar por fecha
            \usort($upcomingHolidays, static fn($a, $b) => \strcmp($a['date'], $b['date']));

            // Limitar resultados
            $upcomingHolidays = \array_slice($upcomingHolidays, 0, $limit);

            return Result::ok(['holidays' => $upcomingHolidays]);
        } catch (Throwable $e) {
            Logger::error('[HolidayService] Error obteniendo próximos festivos: ' . $e->getMessage(), ['exception' => $e->getMessage()]);

            return Result::fail('Error al obtener próximos festivos');
        }
    }

    /**
     * Petición HTTP a la API externa de Nager.Date
     *
     * @param integer $year
     * @return Result Data contiene el array de festivos de la API
     */
    private function fetchApi(int $year): Result
    {
        $url = self::API_URL . '/' . $year . '/' . self::COUNTRY_CODE;

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

        if ($error) {
            Logger::error("HolidayService API error: $error", ['error' => $error]);

            return Result::fail('Error de conexión con el servicio de festivos');
        }

        if ($httpCode !== 200) {
            Logger::error("HolidayService API HTTP $httpCode", ['http_code' => $httpCode]);

            return Result::fail('Servicio de festivos no disponible');
        }

        $data = \json_decode((string) $response, true);

        if (!\is_array($data)) {
            return Result::fail('Respuesta inválida del servicio de festivos');
        }

        return Result::ok($data);
    }

    /**
     * Procesa la respuesta de la API y la normaliza
     *
     * @param array $apiData
     * @return array Array normalizado de festivos
     */
    private function parseHolidayResponse(array $apiData): array
    {
        $holidays = [];

        foreach ($apiData as $item) {
            $holidays[] = [
                'date' => $item['date'] ?? '',
                'name' => $item['name'] ?? $item['localName'] ?? 'Festivo',
                'local_name' => $item['localName'] ?? '',
                'type' => $item['types'][0] ?? 'Public',
                'is_global' => $item['global'] ?? true,
                'counties' => $item['counties'] ?? null,
            ];
        }

        return $holidays;
    }
}
