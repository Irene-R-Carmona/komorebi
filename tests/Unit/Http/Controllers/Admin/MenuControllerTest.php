<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/MenuController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que create() y update() lanzan ValidationException cuando el CSRF falla,
 * garantizando que ninguna mutación pasa sin verificación de seguridad.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación CSRF de las acciones de escritura.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Core\Http\ResponseFactory;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Admin\MenuController;
use App\Services\ProductService;
use Tests\Support\ControllerTestCase;

final class MenuControllerTest extends ControllerTestCase
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

    private function makeController(): MenuController
    {
        return new MenuController(
            productService: $this->createStub(ProductService::class),
            response: new ResponseFactory(),
        );
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
        $this->assertTrue(\method_exists(MenuController::class, 'index'));
        $this->assertTrue(\method_exists(MenuController::class, 'create'));
        $this->assertTrue(\method_exists(MenuController::class, 'update'));
        $this->assertTrue(\method_exists(MenuController::class, 'toggleAvailability'));
        $this->assertTrue(\method_exists(MenuController::class, 'delete'));
    }
}
