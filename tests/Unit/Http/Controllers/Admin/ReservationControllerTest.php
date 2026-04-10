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

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Admin\ReservationController;
use Tests\Support\ControllerTestCase;
use Psr\Http\Message\ResponseInterface;

final class ReservationControllerTest extends ControllerTestCase
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

    private function makeController(): ReservationController
    {
        return new ReservationController(response: new ResponseFactory());
    }

    public function test_cancel_redirects_when_csrf_is_invalid(): void
    {
        $_SESSION['_csrf_token'] = '';

        $result = $this->makeController()->cancel(0);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/reservations');
    }

    public function test_confirm_redirects_when_csrf_is_invalid(): void
    {
        $_SESSION['_csrf_token'] = '';

        $result = $this->makeController()->confirm(0);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/admin/reservations');
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(method_exists(ReservationController::class, 'index'));
        $this->assertTrue(method_exists(ReservationController::class, 'cancel'));
        $this->assertTrue(method_exists(ReservationController::class, 'confirm'));
    }
}
