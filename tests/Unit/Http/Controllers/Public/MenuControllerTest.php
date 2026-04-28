<?php

/**
 * ¿Qué pruebas aquí?
 * Contrato básico de Public/MenuController: instanciación sin DI container.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con dependencias inyectadas vía constructor.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la firma del constructor o se añade una dependencia no nullable.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\MenuController;
use App\Services\Contracts\CartServiceInterface;
use App\Services\Contracts\MenuServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuController::class)]
final class MenuControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(MenuController::class));
    }

    public function test_expected_methods_exist(): void
    {
        $this->assertTrue(\method_exists(MenuController::class, 'index'));
    }

    public function test_can_be_instantiated(): void
    {
        $controller = new MenuController(
            menuService: $this->createStub(MenuServiceInterface::class),
            cartService: $this->createStub(CartServiceInterface::class),
        );

        $this->assertInstanceOf(MenuController::class, $controller);
    }
}
