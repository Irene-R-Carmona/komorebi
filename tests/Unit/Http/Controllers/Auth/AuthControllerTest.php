<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que AuthController cumple el contrato PSR-7.
 *
 * ¿Qué me quieres demostrar?
 * Que showLogin() retorna ResponseInterface (redirect) cuando el usuario ya está autenticado,
 * y que processLogin() retorna null cuando la validación del formulario falla.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la redirección al inicio cuando el usuario ya está logueado,
 * o si processLogin() deja de retornar null en errores de validación.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Auth\AuthController;
use App\Services\Contracts\AuthServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ControllerTestCase;

final class AuthControllerTest extends ControllerTestCase
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

    private function makeController(): AuthController
    {
        return new AuthController(
            authService: $this->createStub(AuthServiceInterface::class),
            response: new ResponseFactory(),
        );
    }

    // ─── showLogin ────────────────────────────────────────────────

    public function test_show_login_redirects_when_already_authenticated(): void
    {
        $_SESSION['user_id'] = 1;

        $result = $this->makeController()->showLogin($this->makeGetRequest('/login'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/');
    }

    // ─── showRegister ─────────────────────────────────────────────

    public function test_show_register_redirects_when_already_authenticated(): void
    {
        $_SESSION['user_id'] = 1;

        $result = $this->makeController()->showRegister($this->makeGetRequest('/registro'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertResponseIsRedirect($result, '/');
    }

    // ─── processLogin ─────────────────────────────────────────────

    public function test_process_login_returns_null_when_body_is_empty(): void
    {
        \ob_start();
        $result = $this->makeController()->processLogin(
            $this->makePostRequest('/login', [])
        );
        \ob_end_clean();

        $this->assertNull($result);
    }

    public function test_process_login_returns_null_when_email_format_invalid(): void
    {
        \ob_start();
        $result = $this->makeController()->processLogin(
            $this->makePostRequest('/login', ['email' => 'not-an-email', 'password' => 'pass'])
        );
        \ob_end_clean();

        $this->assertNull($result);
    }

    // ─── processRegister ─────────────────────────────────────────

    public function test_process_register_returns_null_when_body_is_empty(): void
    {
        \ob_start();
        $result = $this->makeController()->processRegister(
            $this->makePostRequest('/registro', [])
        );
        \ob_end_clean();

        $this->assertNull($result);
    }
}
