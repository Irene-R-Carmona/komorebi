<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/CafeController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que create() lanza ValidationException cuando el CSRF es inválido
 * (defensa de seguridad antes de tocar lógica de negocio).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación CSRF en create()/update()/delete().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Admin\CafeController;
use Tests\Support\ControllerTestCase;

final class CafeControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST    = [];
    }

    private function makeController(): CafeController
    {
        return new CafeController(response: new ResponseFactory());
    }

    public function test_create_throws_validation_exception_when_csrf_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $_SESSION['_csrf_token'] = '';
        $this->makeController()->create();
    }

    public function test_update_throws_validation_exception_when_csrf_is_invalid(): void
    {
        $this->expectException(ValidationException::class);

        $_SESSION['_csrf_token'] = '';
        $this->makeController()->update(1);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(CafeController::class, 'index'));
        $this->assertTrue(method_exists(CafeController::class, 'create'));
        $this->assertTrue(method_exists(CafeController::class, 'update'));
        $this->assertTrue(method_exists(CafeController::class, 'toggleStatus'));
        $this->assertTrue(method_exists(CafeController::class, 'delete'));
    }
}
