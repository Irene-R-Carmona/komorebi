<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica la estructura básica de Admin/DashboardController.
 *
 * ¿Qué me quieres demostrar?
 * Que el controlador puede instanciarse con su dependencia opcional
 * y que expone el método index esperado.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina el método index o se cambia el constructor para no aceptar null.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\DashboardController;
use Tests\Support\ControllerTestCase;

final class DashboardControllerTest extends ControllerTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(\class_exists(DashboardController::class));
    }

    public function test_index_method_exists(): void
    {
        $this->assertTrue(\method_exists(DashboardController::class, 'index'));
    }

    public function test_can_be_instantiated_with_no_args(): void
    {
        $controller = new DashboardController();
        $this->assertInstanceOf(DashboardController::class, $controller);
    }
}
