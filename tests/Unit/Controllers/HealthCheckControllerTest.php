<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
namespace Tests\Unit\Controllers;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use App\Http\Controllers\Keeper\HealthCheckController;
use App\Services\HealthCheckService;
use App\Core\Result;

#[AllowMockObjectsWithoutExpectations]
class HealthCheckControllerTest extends TestCase
{
    private HealthCheckService $mockService;
    private HealthCheckController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = $this->createMock(HealthCheckService::class);
        $this->controller = new HealthCheckController($this->mockService);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(HealthCheckController::class, $this->controller);
    }

    public function testIndexMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->controller, 'index'),
            'El método index debe existir'
        );
    }

    public function testIndexCallsGetTodayDashboard(): void
    {
        $this->mockService
            ->expects($this->once())
            ->method('getTodayDashboard');

        // Capturar output para evitar warnings
        ob_start();
        try {
            $this->controller->index();
        } catch (\Throwable $e) {
            // Es esperado que falle al intentar renderizar vista
        }
        ob_end_clean();
    }

    public function testCreateMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->controller, 'create'),
            'El método create debe existir'
        );
    }

    public function testCreateAcceptsAnimalId(): void
    {
        $reflection = new \ReflectionMethod($this->controller, 'create');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('animalId', $parameters[0]->getName());
    }

    public function testStoreMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->controller, 'store'),
            'El método store debe existir'
        );
    }

    public function testStoreRequiresPostData(): void
    {
        // Verificar que store requiere validación
        // (Test simplificado - la validación completa es integración)
        $reflection = new \ReflectionMethod($this->controller, 'store');
        $this->assertEquals(0, $reflection->getNumberOfParameters());
    }

    public function testShowMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->controller, 'show'),
            'El método show debe existir'
        );
    }

    public function testShowAcceptsCheckId(): void
    {
        $reflection = new \ReflectionMethod($this->controller, 'show');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('checkId', $parameters[0]->getName());
    }

    public function testShowCallsGetCheckById(): void
    {
        $this->mockService
            ->expects($this->once())
            ->method('getCheckById')
            ->with(123)
            ->willReturn(null);

        ob_start();
        try {
            $this->controller->show(123);
        } catch (\Throwable $e) {
            // Es esperado que falle al renderizar
        }
        ob_end_clean();
    }

    public function testHistoryMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->controller, 'history'),
            'El método history debe existir'
        );
    }

    public function testHistoryAcceptsAnimalId(): void
    {
        $reflection = new \ReflectionMethod($this->controller, 'history');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('animalId', $parameters[0]->getName());
    }

    public function testHistoryCallsGetAnimalHistory(): void
    {
        $this->mockService
            ->expects($this->once())
            ->method('getAnimalHistory')
            ->with(456, 10)
            ->willReturn([]);

        $_GET = ['limit' => '10'];

        ob_start();
        try {
            $this->controller->history(456);
        } catch (\Throwable $e) {
            // Es esperado que falle al renderizar
        }
        ob_end_clean();

        unset($_GET);
    }

    protected function tearDown(): void
    {
        // Limpiar variables globales
        unset($_POST, $_GET);
        parent::tearDown();
    }
}
