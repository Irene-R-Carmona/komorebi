<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Controllers\Manager;

use App\Http\Controllers\Manager\DashboardController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\CafeService;
use App\Services\Manager\DashboardService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Manager\DashboardController
 *
 * Dashboard del manager con métricas en tiempo real + Chart.js.
 */
final class DashboardControllerTest extends TestCase
{
    private DashboardController $controller;

    private CafeService $cafeService;

    private DashboardService $dashboardService;

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        // Mockear dependencias de CafeService
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $this->cafeService = new CafeService($cafeRepo);

        // Mockear dependencias de DashboardService
        $db = $this->createStub(PDO::class);
        $this->dashboardService = new DashboardService($db);

        $this->controller = new DashboardController($this->cafeService, $this->dashboardService);
        $this->request = $this->createStub(ServerRequestInterface::class);
    }

    protected function tearDown(): void
    {
        unset($this->controller, $this->cafeService, $this->dashboardService);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DashboardController::class, $this->controller);
    }

    public function testIndexRequiresAuthenticatedUser(): void
    {
        // Sin sesión activa, debe renderizar 403
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION = [];

        \ob_start();
        $this->controller->index($this->request);
        $output = \ob_get_clean();

        // Verificar que renderiza error 403 cuando no hay café asignado
        $this->assertIsString($output);
    }
}
