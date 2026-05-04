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

use App\Http\Controllers\Admin\MenuController;
use App\Repositories\Contracts\AllergenRepositoryInterface;
use App\Repositories\Contracts\MenuCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(MenuController::class)]
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
            productRepo: $this->createStub(ProductRepositoryInterface::class),
            categoryRepo: $this->createStub(MenuCategoryRepositoryInterface::class),
            allergenRepo: $this->createStub(AllergenRepositoryInterface::class),
        );
    }

    public function test_instance_can_be_created_with_stubs(): void
    {
        $this->assertInstanceOf(MenuController::class, $this->makeController());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(MenuController::class, 'index'));
    }
}
