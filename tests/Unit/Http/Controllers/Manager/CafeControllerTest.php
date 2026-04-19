<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Manager/CafeController: updateCapacity, updateSchedule, updateSettings.
 *
 * ¿Qué me quieres demostrar?
 * Que los métodos devuelven 403 sin café asignado, 400 con datos inválidos,
 * y que las reglas de negocio (rango de precios, formato de horas, longitud de descripción) se aplican.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de café asignado, de capacidad, de horario o de settings en CafeController.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Manager\CafeController;
use App\Services\Contracts\CafeServiceInterface;
use Nyholm\Psr7\ServerRequest;
use Tests\Support\ControllerTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CafeController::class)]
final class CafeControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): CafeController
    {
        return new CafeController(
            cafeService: $this->createStub(CafeServiceInterface::class),
            response: new ResponseFactory(),
        );
    }

    public function test_update_capacity_returns_403_when_no_cafe_assigned(): void
    {
        $_SESSION['user_id'] = 1;
        // Sin user_cafe_id → cafeId será null

        $result = $this->makeController()->updateCapacity(
            new ServerRequest('POST', '/manager/cafe/capacity')
        );

        $this->assertSame(403, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
    }

    public function test_update_capacity_returns_400_when_capacity_is_zero(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_cafe_id'] = 5;

        $result = $this->makeController()->updateCapacity(
            new ServerRequest('POST', '/manager/cafe/capacity')
                ->withParsedBody(['capacity_max' => 0])
        );

        $this->assertSame(400, $result->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(CafeController::class, 'show'));
        $this->assertTrue(\method_exists(CafeController::class, 'updateCapacity'));
        $this->assertTrue(\method_exists(CafeController::class, 'updateSchedule'));
        $this->assertTrue(\method_exists(CafeController::class, 'updateSettings'));
    }

    // ─────────────────────────────────────────────────────────────
    // updateSchedule()
    // ─────────────────────────────────────────────────────────────

    public function test_update_schedule_returns_400_when_time_format_is_invalid(): void
    {
        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $result = $this->makeController()->updateSchedule(
            new ServerRequest('POST', '/manager/cafe/schedule')
                ->withParsedBody(['opening_time' => '25:00', 'closing_time' => '18:00'])
        );

        $this->assertSame(400, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('inválido', $body['error']);
    }

    public function test_update_schedule_returns_400_when_opening_after_closing(): void
    {
        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $result = $this->makeController()->updateSchedule(
            new ServerRequest('POST', '/manager/cafe/schedule')
                ->withParsedBody(['opening_time' => '18:00', 'closing_time' => '09:00'])
        );

        $this->assertSame(400, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('menor que', $body['error']);
    }

    // ─────────────────────────────────────────────────────────────
    // updateSettings()
    // ─────────────────────────────────────────────────────────────

    public function test_update_settings_returns_400_when_description_too_long(): void
    {
        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $result = $this->makeController()->updateSettings(
            new ServerRequest('POST', '/manager/cafe/settings')
                ->withParsedBody(['description' => \str_repeat('A', 2100)])
        );

        $this->assertSame(400, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('2000', $body['error']);
    }

    public function test_update_settings_returns_400_when_price_out_of_range(): void
    {
        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $result = $this->makeController()->updateSettings(
            new ServerRequest('POST', '/manager/cafe/settings')
                ->withParsedBody(['price_per_hour' => 150])
        );

        $this->assertSame(400, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('0 y 100', $body['error']);
    }

    public function test_update_settings_returns_400_when_no_fields_provided(): void
    {
        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $result = $this->makeController()->updateSettings(
            new ServerRequest('POST', '/manager/cafe/settings')
                ->withParsedBody([])
        );

        $this->assertSame(400, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('actualizar', $body['error']);
    }
}
