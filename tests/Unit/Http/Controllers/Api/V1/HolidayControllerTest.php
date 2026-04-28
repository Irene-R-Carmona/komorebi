<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\HolidayController retorna la lista de festivos.
 *
 * ¿Qué me quieres demostrar?
 * Que getHolidays() devuelve 200 con un array de festivos no vacío.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si la estructura de respuesta cambia o la lista queda vacía.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\HolidayController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(HolidayController::class)]
final class HolidayControllerTest extends ControllerTestCase
{
    private function makeController(): HolidayController
    {
        return new HolidayController(new ResponseFactory());
    }

    public function test_get_holidays_returns_200_with_list(): void
    {
        $response = $this->makeController()->getHolidays($this->makeGetRequest('/api/v1/holidays'));

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertGreaterThan(0, $body['data']['count']);
        $this->assertArrayHasKey('holidays', $body['data']);
    }

    public function test_all_holidays_have_date_and_name_fields(): void
    {
        $response = $this->makeController()->getHolidays($this->makeGetRequest('/api/v1/holidays'));
        $body = \json_decode((string) $response->getBody(), true);

        foreach ($body['data']['holidays'] as $holiday) {
            $this->assertArrayHasKey('date', $holiday);
            $this->assertArrayHasKey('name', $holiday);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $holiday['date']);
        }
    }

    public function test_check_holiday_returns_200_for_holiday_date_via_route_attribute(): void
    {
        $request = $this->makeGetRequest('/api/v1/holidays/2026-01-01')
            ->withAttribute('date', '2026-01-01');

        $response = $this->makeController()->checkHoliday($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertTrue($body['data']['is_holiday']);
    }

    public function test_check_holiday_returns_200_for_non_holiday_date_via_route_attribute(): void
    {
        $request = $this->makeGetRequest('/api/v1/holidays/2026-03-15')
            ->withAttribute('date', '2026-03-15');

        $response = $this->makeController()->checkHoliday($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['data']['is_holiday']);
        $this->assertTrue($body['data']['available_for_reservations']);
    }

    public function test_check_holiday_returns_422_when_attribute_missing(): void
    {
        $request = $this->makeGetRequest('/api/v1/holidays/');

        $response = $this->makeController()->checkHoliday($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(HolidayController::class, 'getHolidays'));
        $this->assertTrue(\method_exists(HolidayController::class, 'checkHoliday'));
    }
}
