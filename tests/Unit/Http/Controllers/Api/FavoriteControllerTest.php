<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica el contrato PSR-7 de Api/FavoriteController.
 *
 * ¿Qué me quieres demostrar?
 * Que toggle() devuelve 401 cuando el usuario no está autenticado,
 * y que list() devuelve 401 cuando no hay sesión.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la comprobación de sesión antes de operar con favoritos.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Core\Http\ResponseFactory;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(FavoriteController::class)]
final class FavoriteControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(): FavoriteController
    {
        return new FavoriteController(
            new ResponseFactory(),
            $this->createStub(FavoriteRepositoryInterface::class),
        );
    }

    public function test_toggle_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->toggle(
            new ServerRequest('POST', '/api/favorites/toggle')
        );

        $this->assertSame(401, $result->getStatusCode());
        $body = \json_decode((string) $result->getBody(), true);
        $this->assertSame(401, $body['status']);
    }

    public function test_list_returns_401_when_not_authenticated(): void
    {
        $result = $this->makeController()->list(
            new ServerRequest('GET', '/api/favorites')
        );

        $this->assertSame(401, $result->getStatusCode());
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(FavoriteController::class, 'toggle'));
        $this->assertTrue(\method_exists(FavoriteController::class, 'list'));
    }
}
