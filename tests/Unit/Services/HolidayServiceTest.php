<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * HolidayService: validaciones de año fuera de rango, rango inválido y
 * formato de fecha incorrecto en isHoliday — todas retornan sin llamar a la API.
 *
 * ¿Qué me quieres demostrar?
 * Que las guardas de validación devuelven Result::fail de forma inmediata,
 * sin necesitar caché ni conexión de red, protegiendo la API externa.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se amplía o reduce el rango de años permitido, si se cambia el límite
 * de 5 años en getHolidaysByRange, o si se relaja la validación de formato de fecha.
 */

namespace Tests\Unit\Services;

use App\Services\HolidayService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HolidayService::class)]
final class HolidayServiceTest extends TestCase
{
    private HolidayService $service;

    protected function setUp(): void
    {
        // Sin caché: null → no se usa caché en estas pruebas de validación
        $this->service = new HolidayService(null);
    }

    // ──────────────────────────────────────────────
    // getHolidaysByYear — validación de año
    // ──────────────────────────────────────────────

    public function testGetHolidaysByYearConAnioMuyAntiguoRetornaFail(): void
    {
        $result = $this->service->getHolidaysByYear(1900);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rango', $result->error);
    }

    public function testGetHolidaysByYearConAnioDemasiadoFuturoRetornaFail(): void
    {
        $añoFuturoExcesivo = (int) \date('Y') + 10;

        $result = $this->service->getHolidaysByYear($añoFuturoExcesivo);

        $this->assertFalse($result->ok);
    }

    // ──────────────────────────────────────────────
    // getHolidaysByRange — validaciones de rango
    // ──────────────────────────────────────────────

    public function testGetHolidaysByRangeConStartMayorQueEndRetornaFail(): void
    {
        $result = $this->service->getHolidaysByRange(2027, 2025);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('inválido', $result->error);
    }

    public function testGetHolidaysByRangeConRangoMayorDe5AnosRetornaFail(): void
    {
        $currentYear = (int) \date('Y');

        $result = $this->service->getHolidaysByRange($currentYear, $currentYear + 6);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('5', $result->error);
    }

    // ──────────────────────────────────────────────
    // isHoliday — validación de formato de fecha
    // ──────────────────────────────────────────────

    public function testIsHolidayConFormatoInvalidoRetornaFail(): void
    {
        $result = $this->service->isHoliday('enero-2025');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Formato', $result->error);
    }

    public function testIsHolidayConFechaFormatoSlashRetornaFail(): void
    {
        $result = $this->service->isHoliday('2025/01/01');

        $this->assertFalse($result->ok);
    }

    public function testIsHolidayConFechaSoloAnoRetornaFail(): void
    {
        $result = $this->service->isHoliday('2025');

        $this->assertFalse($result->ok);
    }
}
