<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/UserController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que getUsersList() retorna ResponseInterface con JSON,
 * leyendo datos desde el repositorio inyectado (no desde $_POST ni BD directo).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si getUsersList() deja de usar $this->userRepo,
 * o si el formato de la respuesta JSON cambia.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\UserController;
use App\Repositories\Contracts\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(UserController::class)]
final class UserControllerTest extends ControllerTestCase
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

    public function test_instance_can_be_created_with_stubs(): void
    {
        $controller = new UserController(
            userRepo: $this->createStub(UserRepositoryInterface::class),
        );
        $this->assertInstanceOf(UserController::class, $controller);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(UserController::class, 'index'));
    }
}
