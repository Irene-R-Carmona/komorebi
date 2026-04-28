<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? HolidayService: validación del rango de año para la API de festivos.
 * ¿Qué me quieres demostrar? Que años fuera del rango (más de 5 años en el futuro o más de 1 en el pasado) retornan Result::fail.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambia el rango de años permitidos en getHolidaysByYear.
 */

namespace Tests\Unit\Services;

use App\Services\HolidayService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HolidayService::class)]
final class HolidayServiceTest extends TestCase
{
    private HolidayService $service;

    protected function setUp(): void
    {
        $this->service = new HolidayService(null);
    }

    public function testGetHolidaysByYearFailsForTooFarFutureYear(): void
    {
        $farFuture = (int) \date('Y') + 10;

        $result = $this->service->getHolidaysByYear($farFuture);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('fuera de rango', $result->error);
    }

    public function testGetHolidaysByYearFailsForTooFarPastYear(): void
    {
        $farPast = (int) \date('Y') - 5;

        $result = $this->service->getHolidaysByYear($farPast);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('fuera de rango', $result->error);
    }

    public function testGetHolidaysByYearReturnsResultForCurrentYear(): void
    {
        $currentYear = (int) \date('Y');

        // With no cache, it will try HTTP. We only validate it doesn't blow up with range error.
        $result = $this->service->getHolidaysByYear($currentYear);

        // May succeed (if network available) or fail (network error), but NOT 'fuera de rango'
        if (!$result->ok) {
            $this->assertStringNotContainsString('fuera de rango', $result->error);
        } else {
            $this->assertArrayHasKey('holidays', $result->data);
        }
    }

    public function testIsHolidayReturnsBoolForDate(): void
    {
        $result = $this->service->isHoliday(\date('Y-m-d'));

        $this->assertInstanceOf(\App\Core\Result::class, $result);
    }

    public function testGetHolidaysByRangeFailsWhenStartAfterEnd(): void
    {
        $result = $this->service->getHolidaysByRange(2025, 2024);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Rango de años inválido', $result->error);
    }

    public function testGetHolidaysByRangeFailsWhenRangeExceedsFiveYears(): void
    {
        $result = $this->service->getHolidaysByRange(2020, 2030);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('5 años', $result->error);
    }

    public function testIsHolidayFailsWithInvalidDateFormat(): void
    {
        $result = $this->service->isHoliday('not-a-date');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Formato de fecha inválido', $result->error);
    }
}
