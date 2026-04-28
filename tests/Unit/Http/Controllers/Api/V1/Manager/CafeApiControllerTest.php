<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\Manager\CafeApiController delega a CafeServiceInterface y valida inputs.
 *
 * ¿Qué me quieres demostrar?
 * Que updateCapacity(), updateSchedule() y updateSettings() devuelven 403 sin café asignado,
 * 400 con parámetros inválidos, y 200 cuando la validación pasa.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guard de cafe_id, o cambia la validación de rango de capacity_max / precio.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\Manager\CafeApiController;
use App\Services\Contracts\CafeServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(CafeApiController::class)]
final class CafeApiControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): CafeApiController
    {
        $service = $this->createStub(CafeServiceInterface::class);
        $service->method('update')->willReturn(Result::ok());

        return new CafeApiController(new ResponseFactory(), $service);
    }

    // — updateCapacity —

    public function test_updateCapacity_returns_403_without_cafe(): void
    {
        $request  = $this->makePostRequest('/api/v1/manager/cafe/capacity', ['capacity_max' => 50]);
        $response = $this->makeController()->updateCapacity($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_updateCapacity_returns_400_when_zero(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/capacity', ['capacity_max' => 0]);
        $response = $this->makeController()->updateCapacity($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateCapacity_returns_400_when_over_limit(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/capacity', ['capacity_max' => 501]);
        $response = $this->makeController()->updateCapacity($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateCapacity_returns_200_with_valid_input(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/capacity', ['capacity_max' => 100]);
        $response = $this->makeController()->updateCapacity($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    // — updateSchedule —

    public function test_updateSchedule_returns_403_without_cafe(): void
    {
        $request  = $this->makePostRequest('/api/v1/manager/cafe/schedule', [
            'opening_time' => '09:00',
            'closing_time' => '21:00',
        ]);
        $response = $this->makeController()->updateSchedule($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_updateSchedule_returns_400_with_invalid_time_format(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/schedule', [
            'opening_time' => 'not-a-time',
            'closing_time' => '21:00',
        ]);
        $response = $this->makeController()->updateSchedule($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateSchedule_returns_400_when_open_after_close(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/schedule', [
            'opening_time' => '22:00',
            'closing_time' => '09:00',
        ]);
        $response = $this->makeController()->updateSchedule($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateSchedule_returns_200_with_valid_times(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/schedule', [
            'opening_time' => '09:00',
            'closing_time' => '21:00',
        ]);
        $response = $this->makeController()->updateSchedule($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    // — updateSettings —

    public function test_updateSettings_returns_403_without_cafe(): void
    {
        $request  = $this->makePostRequest('/api/v1/manager/cafe/settings', ['price_per_hour' => 10]);
        $response = $this->makeController()->updateSettings($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_updateSettings_returns_400_when_price_out_of_range(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/settings', ['price_per_hour' => 999]);
        $response = $this->makeController()->updateSettings($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateSettings_returns_400_when_no_fields(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/settings', []);
        $response = $this->makeController()->updateSettings($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_updateSettings_returns_200_with_valid_price(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 1);
        $request  = $this->makePostRequest('/api/v1/manager/cafe/settings', ['price_per_hour' => 15]);
        $response = $this->makeController()->updateSettings($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(CafeApiController::class, 'updateCapacity'));
        $this->assertTrue(\method_exists(CafeApiController::class, 'updateSchedule'));
        $this->assertTrue(\method_exists(CafeApiController::class, 'updateSettings'));
    }
}
