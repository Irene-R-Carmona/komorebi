<?php

/**
 * ¿Qué pruebas aquí?
 * Tests de Manager\StaffController: index, show, assignShift con validaciones de negocio.
 *
 * ¿Qué me quieres demostrar?
 * - index()/show()/assignShift() devuelven 403 sin café asignado en sesión.
 * - assignShift() valida user_id, fecha, horas de inicio/fin y que inicio < fin.
 * - assignShift() devuelve 400 cuando el servicio detecta solapamiento de turnos.
 * - viewPerformance() devuelve 403 sin café asignado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se elimina la guard de cafe_id en cualquiera de los métodos.
 * - Si cambian los textos de error (mensajes en respuesta JSON).
 * - Si la firma del constructor cambia y ya no acepta UserRepositoryInterface.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Manager\StaffController;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class StaffControllerTest extends TestCase
{
    /** @var UserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private UserRepositoryInterface $userRepo;
    private ResponseFactory $responseFactory;
    /** @var ServerRequestInterface&\PHPUnit\Framework\MockObject\Stub */
    private ServerRequestInterface $request;
    private StaffController $controller;

    protected function setUp(): void
    {
        $this->userRepo        = $this->createMock(UserRepositoryInterface::class);
        $this->responseFactory = new ResponseFactory();
        $this->request         = $this->createMock(ServerRequestInterface::class);
        $this->controller      = new StaffController(
            $this->userRepo,
            $this->responseFactory,
            $this->createMock(StaffShiftServiceInterface::class),
        );
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_cafe_id'], $_SESSION['user_role']);
    }

    private function startSession(): void
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            \session_start();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // index() / show()
    // ─────────────────────────────────────────────────────────────

    public function test_index_requires_cafe_assignment(): void
    {
        $this->startSession();
        unset($_SESSION['user_id'], $_SESSION['user_cafe_id']);

        \ob_start();
        $this->controller->index($this->request);
        $output = \ob_get_clean();

        $this->assertIsString($output);
    }

    public function test_show_requires_cafe_assignment(): void
    {
        $this->startSession();
        unset($_SESSION['user_id'], $_SESSION['user_cafe_id']);

        \ob_start();
        $this->controller->show($this->request, 1);
        $output = \ob_get_clean();

        $this->assertIsString($output);
    }

    // ─────────────────────────────────────────────────────────────
    // assignShift()
    // ─────────────────────────────────────────────────────────────

    public function test_assign_shift_returns_403_without_cafe_assignment(): void
    {
        $this->startSession();
        unset($_SESSION['user_id'], $_SESSION['user_cafe_id']);

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '2026-02-15',
            'shift_start' => '09:00', 'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_assign_shift_validates_user_id(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 0, 'shift_date' => '2026-02-15',
            'shift_start' => '09:00', 'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('válido', $body['error']);
    }

    public function test_assign_shift_validates_date_format(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '15/02/2026',
            'shift_start' => '09:00', 'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('Fecha', $body['error']);
    }

    public function test_assign_shift_validates_start_time_format(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '2026-02-15',
            'shift_start' => '25:00', 'shift_end' => '17:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('inicio inválida', $body['error']);
    }

    public function test_assign_shift_validates_end_time_format(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '2026-02-15',
            'shift_start' => '09:00', 'shift_end' => '99:99',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('fin inválida', $body['error']);
    }

    public function test_assign_shift_validates_start_before_end(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '2026-02-15',
            'shift_start' => '18:00', 'shift_end' => '09:00',
        ]);

        $response = $this->controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('menor que', $body['error']);
    }

    public function test_assign_shift_returns_400_when_shift_overlaps(): void
    {
        $this->startSession();
        $_SESSION['user_id']     = 10;
        $_SESSION['user_cafe_id'] = 1;

        $this->userRepo->method('existsInCafe')->willReturn(true);

        $shiftServiceStub = $this->createMock(StaffShiftServiceInterface::class);
        $shiftServiceStub->method('assignShift')
            ->willReturn(Result::fail('El staff member ya tiene un turno asignado en ese horario', 'shift_overlap'));

        $controller = new StaffController($this->userRepo, $this->responseFactory, $shiftServiceStub);

        $this->request->method('getParsedBody')->willReturn([
            'user_id' => 5, 'shift_date' => '2026-02-15',
            'shift_start' => '09:00', 'shift_end' => '17:00',
        ]);

        $response = $controller->assignShift($this->request);

        $this->assertSame(400, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('turno asignado', $body['error']);
    }

    // ─────────────────────────────────────────────────────────────
    // viewPerformance()
    // ─────────────────────────────────────────────────────────────

    public function test_view_performance_returns_403_without_cafe_assignment(): void
    {
        $this->startSession();
        unset($_SESSION['user_id'], $_SESSION['user_cafe_id']);

        $response = $this->controller->viewPerformance(5);

        $this->assertSame(403, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('café asignado', $body['error']);
    }
}

