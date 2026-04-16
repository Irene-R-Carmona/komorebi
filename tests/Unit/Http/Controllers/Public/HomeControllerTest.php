<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Public/HomeController puede cargarse correctamente.
 *
 * ¿Qué me quieres demostrar?
 * Que la clase existe, tiene el método index() y puede ser instanciada
 * sin errores de autoloading (el constructor no accede a la BD).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el método index() o se rompe el namespace/autoload.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Public;

use App\Http\Controllers\Public\HomeController;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(HomeController::class));
    }

    public function test_index_method_exists(): void
    {
        $this->assertTrue(\method_exists(HomeController::class, 'index'));
    }

    public function test_can_be_instantiated(): void
    {
        $controller = new HomeController();
        $this->assertInstanceOf(HomeController::class, $controller);
    }
}
