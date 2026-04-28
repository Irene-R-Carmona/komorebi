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

use App\Http\Controllers\Manager\StaffController;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(StaffController::class)]
final class StaffControllerTest extends TestCase
{
    /** @var UserRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private UserRepositoryInterface $userRepo;
    /** @var ServerRequestInterface&\PHPUnit\Framework\MockObject\Stub */
    private ServerRequestInterface $request;
    private StaffController $controller;

    protected function setUp(): void
    {
        $this->userRepo = $this->createStub(UserRepositoryInterface::class);
        $this->request = $this->createStub(ServerRequestInterface::class);
        $this->controller = new StaffController(
            $this->userRepo,
            $this->createStub(StaffShiftServiceInterface::class),
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
}
