<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\ManagerController delega a DashboardService y respeta Session.
 *
 * ¿Qué me quieres demostrar?
 * Que stats() retorna 403 sin café asignado, y 200 con los datos del servicio inyectado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guard de cafe_id en stats() o cambia el formato de respuesta.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\ManagerController;
use App\Services\Contracts\DashboardServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ManagerController::class)]
final class ManagerControllerTest extends ControllerTestCase
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

    private function makeController(): ManagerController
    {
        $service = $this->createStub(DashboardServiceInterface::class);
        $service->method('getDashboardMetrics')->willReturn(['reservations_today' => 5, 'revenue_today' => 0.0]);
        $service->method('getWeeklyRevenue')->willReturn([]);

        return new ManagerController(new ResponseFactory(), $service);
    }

    public function test_stats_returns_403_when_no_cafe_assigned(): void
    {
        // Sin $_SESSION['user_cafe_id']
        $response = $this->makeController()->stats($this->makeGetRequest('/api/v1/manager/stats'));

        $this->assertSame(403, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertSame(403, $body['status']);
    }

    public function test_stats_returns_200_with_metrics_when_cafe_assigned(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 42);

        $response = $this->makeController()->stats($this->makeGetRequest('/api/v1/manager/stats'));

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('timestamp', $body['data']);
        $this->assertArrayHasKey('reservations_today', $body['data']);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ManagerController::class, 'stats'));
        $this->assertTrue(\method_exists(ManagerController::class, 'weeklyRevenue'));
    }
}
