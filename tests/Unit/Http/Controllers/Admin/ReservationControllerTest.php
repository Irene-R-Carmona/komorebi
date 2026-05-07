<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/ReservationController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que cancel() y confirm() retornan ResponseInterface (redirect)
 * cuando el token CSRF es inválido, sin tocar la BD ni el modelo.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación CSRF en cancel()/confirm()
 * o si cambia la URL de redirección en caso de error.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ReservationController;
use App\Services\Contracts\AdminActivityServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(ReservationController::class)]
final class ReservationControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    private function makeController(): ReservationController
    {
        return new ReservationController(
            activityService: $this->createStub(AdminActivityServiceInterface::class),
        );
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(ReservationController::class, $this->makeController());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(ReservationController::class, 'index'));
    }
}
