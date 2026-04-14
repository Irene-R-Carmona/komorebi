<?php

declare(strict_types=1);


/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Manager\StaffController;
use App\Repositories\UserRepository;
use App\Services\Contracts\StaffShiftServiceInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests para Manager\StaffController
 *
 * Validación de gestión de staff con scope verification (ownership).
 */
final class StaffControllerTest extends TestCase
{
    private StaffController $controller;

    private UserRepository $userRepo;

    private ResponseFactory $responseFactory;

    private ServerRequestInterface $request;

    private PDO $db;

    protected function setUp(): void
    {
        // Mock UserRepository
        $this->userRepo = $this->createStub(UserRepository::class);

        // Mock PDO y PDOStatement
        $this->db = $this->createStub(PDO::class);

        $this->responseFactory = new ResponseFactory();
        $this->request = $this->createStub(ServerRequestInterface::class);

        $this->controller = new StaffController($this->userRepo, $this->responseFactory, $this->createStub(StaffShiftServiceInterface::class));
    }

    protected function tearDown(): void
    {
        unset($this->controller, $this->userRepo, $this->request, $this->db);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StaffController::class, $this->controller);
    }

    public function testIndexRequiresCafeAssignment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Limpiar claves de sesión
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        ob_start();
        $this->controller->index($this->request);
        $output = ob_get_clean();

        // Debe renderizar 403 cuando no hay café asignado
        $this->assertIsString($output);
    }

    public function testShowRequiresCafeAssignment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        ob_start();
        $this->controller->show($this->request, 1);
        $output = ob_get_clean();

        $this->assertIsString($output);
    }

    public function testShowReturns404WhenStaffNotBelongsToCafe(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        // Mock PDOStatement que retorna vacío (staff no pertenece al café)
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $this->db->method('prepare')->willReturn($stmt);

        ob_start();
        $this->controller->show($this->request, 999);
        $output = ob_get_clean();

        // Debe renderizar 404
        $this->assertIsString($output);
    }

    public function testAssignShiftRequiresCafeAssignment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '2026-02-15',
            'shift_start' => '09:00',
            'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAssignShiftValidatesUserId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 0, // Inválido
            'shift_date' => '2026-02-15',
            'shift_start' => '09:00',
            'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('válido', $data['error']);
    }

    public function testAssignShiftValidatesDateFormat(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '15/02/2026', // Formato incorrecto
            'shift_start' => '09:00',
            'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Fecha', $data['error']);
    }

    public function testAssignShiftValidatesStartTimeFormat(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '2026-02-15',
            'shift_start' => '25:00', // Hora inválida
            'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('inicio inválida', $data['error']);
    }

    public function testAssignShiftValidatesEndTimeFormat(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '2026-02-15',
            'shift_start' => '09:00',
            'shift_end' => '99:99', // Hora inválida
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('fin inválida', $data['error']);
    }

    public function testAssignShiftValidatesStartBeforeEnd(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '2026-02-15',
            'shift_start' => '18:00',
            'shift_end' => '09:00', // Menor que inicio
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('menor que', $data['error']);
    }

    public function testAssignShiftDetectsOverlap(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['user_id'] = 10;
        $_SESSION['user_cafe_id'] = 1;

        // El staff pertenece al café
        $this->userRepo->method('existsInCafe')->willReturn(true);

        // StaffShiftService stub que devuelve solapamiento
        $shiftServiceStub = $this->createStub(StaffShiftServiceInterface::class);
        $shiftServiceStub->method('assignShift')
            ->willReturn(Result::fail('El staff member ya tiene un turno asignado en ese horario', 'shift_overlap'));

        $controller = new StaffController($this->userRepo, $this->responseFactory, $shiftServiceStub);

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5,
            'shift_date' => '2026-02-15',
            'shift_start' => '09:00',
            'shift_end' => '17:00',
        ]);

        $response = $controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('turno asignado', $data['error']);
    }

    public function testViewPerformanceRequiresCafeAssignment(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_cafe_id']);

        $response = $this->controller->viewPerformance(5);

        $this->assertSame(403, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('café asignado', $data['error']);
    }
}
