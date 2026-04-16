<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Controllers\Api\V1;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\ManagerController;
use App\Services\Manager\DashboardService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Api\V1\ManagerController
 *
 * API endpoints para dashboard del manager (polling Alpine.js).
 */
final class ManagerControllerTest extends TestCase
{
    private ManagerController $controller;

    private DashboardService $dashboardService;

    private ResponseFactory $responseFactory;

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        // Crear DashboardService con PDO mockeado
        $db = $this->createStub(PDO::class);
        $this->dashboardService = new DashboardService($db);

        $this->responseFactory = new ResponseFactory();
        $this->request = $this->createStub(ServerRequestInterface::class);

        $this->controller = new ManagerController($this->responseFactory, $this->dashboardService);
    }

    protected function tearDown(): void
    {
        unset($this->controller, $this->dashboardService, $this->request);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ManagerController::class, $this->controller);
    }

    public function testStatsReturns403WhenNoCafeAssigned(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        unset($_SESSION['user_cafe_id']);

        $response = $this->controller->stats($this->request);

        $this->assertSame(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertSame(403, $data['status']);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testAllEndpointsRequireCafeId(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        unset($_SESSION['user_cafe_id']);

        $endpoints = [
            'stats',
            'weeklyRevenue',
            'topAnimals',
            'reservationStatus',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->controller->$endpoint($this->request);
            $this->assertSame(403, $response->getStatusCode(), "Endpoint $endpoint should return 403 without cafe_id");
        }
    }
}
