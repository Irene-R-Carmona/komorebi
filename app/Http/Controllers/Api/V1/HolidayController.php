<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\AbstractApiController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API V1 - Holidays Controller
 *
 * Gestiona días festivos y disponibilidad de reservas
 */
final class HolidayController extends AbstractApiController
{
    private const HOLIDAY_DATES = [
        ['date' => '2026-01-01', 'name' => 'Año Nuevo'],
        ['date' => '2026-01-06', 'name' => 'Epifanía del Señor'],
        ['date' => '2026-04-03', 'name' => 'Viernes Santo'],
        ['date' => '2026-05-01', 'name' => 'Día del Trabajo'],
        ['date' => '2026-08-15', 'name' => 'Asunción de la Virgen'],
        ['date' => '2026-10-12', 'name' => 'Fiesta Nacional de España'],
        ['date' => '2026-11-01', 'name' => 'Todos los Santos'],
        ['date' => '2026-12-06', 'name' => 'Día de la Constitución'],
        ['date' => '2026-12-08', 'name' => 'Inmaculada Concepción'],
        ['date' => '2026-12-25', 'name' => 'Navidad'],
    ];

    public function __construct(ResponseFactory $response)
    {
        parent::__construct($response);
    }

    /**
     * GET /api/v1/holidays
     * Obtener lista de días festivos
     */
    public function getHolidays(ServerRequestInterface $request): ResponseInterface
    {
        $data = [
            'holidays' => self::HOLIDAY_DATES,
            'count' => \count(self::HOLIDAY_DATES),
        ];
        $etag = $this->makeEtag($data);
        $cc = 'public, max-age=86400';

        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $this->notModified($etag, $cc);
        }

        return $this->success($data, 200, [
            'Cache-Control' => $cc,
            'ETag' => $etag,
        ]);
    }

    /**
     * GET /api/v1/holidays/{date}
     * Verificar si una fecha es festivo
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

        $holidayDates = \array_column(self::HOLIDAY_DATES, 'date');
        $isHoliday = \in_array($date, $holidayDates, true);

        return $this->success([
            'date' => $date,
            'is_holiday' => $isHoliday,
            'available_for_reservations' => !$isHoliday,
        ]);
    }
}
