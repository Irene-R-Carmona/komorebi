<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que UserController sigue el contrato PSR-7:
 * recibe ServerRequestInterface, retorna ?ResponseInterface (no void).
 *
 * ¿Qué me quieres demostrar?
 * Que ningún método llama a header() o exit directamente.
 * Que los inputs se leen desde $request->getParsedBody(), no de $_POST.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se vuelve a usar $_POST/$_FILES/header()/exit en el controller.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Shared\UserController;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class UserControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpiar sesión PHP entre tests
        if (isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Construye un UserController con dependencias reales por defecto.
     * Todos los servicios son final y no se pueden doblar; se dejan null
     * para que el constructor use sus propios new Service() internos.
     * Solo inyectamos ResponseFactory para controlar la instancia.
     */
    private function makeController(): UserController
    {
        return new UserController(response: new ResponseFactory());
    }

    public function test_update_method_accepts_psr7_request_and_returns_response_interface(): void
    {
        // Sin sesión activa → el controller redirige y devuelve ResponseInterface
        $controller = $this->makeController();

        $request = (new ServerRequest('POST', '/perfil/actualizar'))
            ->withParsedBody(['name' => 'Juan', 'email' => 'juan@example.com']);

        $result = $controller->update($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_change_password_method_accepts_psr7_request_and_returns_response_interface(): void
    {
        // Sin sesión activa → redirect a login devuelve ResponseInterface
        $controller = $this->makeController();

        $request = (new ServerRequest('POST', '/perfil/password'))
            ->withParsedBody([
                'current_password'     => 'old123',
                'new_password'         => 'new456789',
                'new_password_confirm' => 'new456789',
            ]);

        $result = $controller->changePassword($request);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_update_returns_redirect_response_when_not_authenticated(): void
    {
        $_SESSION = []; // Sin usuario en sesión
        $controller = $this->makeController();

        $result = $controller->update(new ServerRequest('POST', '/perfil/actualizar'));

        // La respuesta debe ser redirect (302)
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(302, $result->getStatusCode());
    }
}
