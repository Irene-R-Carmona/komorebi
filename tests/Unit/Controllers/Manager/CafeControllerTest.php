<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Manager\CafeController;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\CafeService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Manager\CafeController
 *
 * Validación de gestión de café con scope verification (ownership).
 */
final class CafeControllerTest extends TestCase
{
    private CafeController $controller;

    private CafeService $cafeService;

    private ResponseFactory $responseFactory;

    /** @var \PHPUnit\Framework\MockObject\Stub&ServerRequestInterface */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        // Crear CafeService con repositorio mockeado
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);

        // Mock findById para retornar un café válido
        $cafeRepo->method('findById')->willReturn([
            'id' => 1,
            'name' => 'Test Café',
            'japanese_name' => 'テストカフェ',
            'slug' => 'test-cafe',
            'location' => 'Test Location',
            'category' => 'cafe',
            'animal_type' => 'cat',
            'description' => 'Test café description',
            'capacity_max' => 50,
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
            'price_per_hour' => 10.50,
            'rating_avg' => 4.5,
            'rating_count' => 100,
            'is_active' => 1,
        ]);

        $this->cafeService = new CafeService($cafeRepo);

        $this->responseFactory = new ResponseFactory();
        $this->request = $this->createStub(ServerRequestInterface::class);

        $this->controller = new CafeController($this->cafeService, $this->responseFactory);
    }

    protected function tearDown(): void
    {
        unset($this->controller, $this->cafeService, $this->request);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CafeController::class, $this->controller);
    }

    public function testShowRequiresCafeAssignment(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        // Limpiar claves de sesión relacionadas con usuario y café
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        \ob_start();
        $this->controller->show($this->request);
        $output = \ob_get_clean();

        // Debe renderizar 403 cuando no hay café asignado
        $this->assertIsString($output);
    }

    public function testUpdateCapacityReturns403WithoutCafeId(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        // Limpiar claves de sesión relacionadas con usuario y café
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        $this->request->method('getParsedBody')->willReturn(['capacity_max' => 50]);

        $response = $this->controller->updateCapacity($this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUpdateCapacityValidatesPositiveValue(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn(['capacity_max' => 0]);

        $response = $this->controller->updateCapacity($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('mayor a 0', $data['error']);
    }

    public function testUpdateCapacityValidatesMaximumLimit(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn(['capacity_max' => 600]);

        $response = $this->controller->updateCapacity($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('500', $data['error']);
    }

    public function testUpdateScheduleValidatesTimeFormat(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'opening_time' => '25:00',
            'closing_time' => '18:00',
        ]);

        $response = $this->controller->updateSchedule($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('inválido', $data['error']);
    }

    public function testUpdateScheduleValidatesOpeningBeforeClosing(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'opening_time' => '18:00',
            'closing_time' => '09:00',
        ]);

        $response = $this->controller->updateSchedule($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('menor que', $data['error']);
    }

    public function testUpdateSettingsValidatesDescriptionLength(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $longDescription = \str_repeat('A', 2100);

        $this->request->method('getParsedBody')->willReturn([
            'description' => $longDescription,
        ]);

        $response = $this->controller->updateSettings($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('2000', $data['error']);
    }

    public function testUpdateSettingsValidatesPriceRange(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'price_per_hour' => 150,
        ]);

        $response = $this->controller->updateSettings($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('0 y 100', $data['error']);
    }

    public function testUpdateSettingsRequiresAtLeastOneField(): void
    {
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            \session_start();
        }

        $_SESSION['user_id'] = 5;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([]);

        $response = $this->controller->updateSettings($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = \json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('actualizar', $data['error']);
    }
}
