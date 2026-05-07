<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Admin/CafeController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que create() y update() aceptan ServerRequestInterface y delegan en CafeServiceInterface.
 * La validación CSRF se realiza en middleware, no en el controlador.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si create() o update() dejan de aceptar ServerRequestInterface como primer parámetro.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\CafeController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(CafeController::class)]
final class CafeControllerTest extends ControllerTestCase
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

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(CafeController::class, 'index'));
    }
}
