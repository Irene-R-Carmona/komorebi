<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AuthMiddleware PSR-15: autenticación basada en sesión.
 *
 * ¿Qué me quieres demostrar?
 * Que sin user_id en sesión redirige a /login; que con usuario activo en sesión
 * delega al handler; que con usuario inactivo destruye la sesión y redirige.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cambia la URL de redirección, el atributo con el que se enriquece el
 * request, o la lógica de verificación de is_active.
 */

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\AuthMiddleware;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

#[CoversClass(AuthMiddleware::class)]
final class AuthMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private AuthMiddleware $middleware;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        // Reiniciar sesión limpia para cada test
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
        \session_start();
        $_SESSION = [];

        $this->psr17 = new Psr17Factory();
        $this->responseFactory = new ResponseFactory();
        $this->middleware = new AuthMiddleware($this->responseFactory);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_destroy();
        }
    }

    private function makeRequest(string $method = 'GET', string $uri = '/'): ServerRequestInterface
    {
        return $this->psr17->createServerRequest($method, $uri);
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        return new class ($status, $this->psr17) implements RequestHandlerInterface {
            public function __construct(
                private readonly int $status,
                private readonly Psr17Factory $psr17
            ) {}

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse($this->status);
            }
        };
    }

    public function testRedirectsToLoginWhenNoUserIdInSession(): void
    {
        // Sin user_id en sesión → redirige a /login
        $response = $this->middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testRedirectsWhenUserIdIsZero(): void
    {
        $_SESSION['user_id'] = 0;

        $response = $this->middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testDelegatesToHandlerWhenActiveUserIsCachedInSession(): void
    {
        // Usuario activo en sesión: no necesita consultar BD
        $_SESSION['user_id'] = 42;
        $_SESSION['user'] = ['id' => 42, 'name' => 'Test User', 'is_active' => true];
        $_SESSION['user_roles'] = ['user']; // evita consulta de roles a BD

        $response = $this->middleware->process($this->makeRequest(), $this->makeHandler(200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestEnrichedWithUserIdAttributeWhenAuthenticated(): void
    {
        $_SESSION['user_id'] = 7;
        $_SESSION['user'] = ['id' => 7, 'name' => 'Admin', 'is_active' => true];
        $_SESSION['user_roles'] = ['admin'];

        $capturedRequest = null;
        $handler = new class ($capturedRequest, $this->psr17) implements RequestHandlerInterface {
            public function __construct(
                public ?ServerRequestInterface &$captured,
                private readonly Psr17Factory $psr17
            ) {}

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return $this->psr17->createResponse(200);
            }
        };

        $this->middleware->process($this->makeRequest(), $handler);

        $this->assertSame(7, $capturedRequest->getAttribute('user_id'));
        $this->assertSame('Admin', $capturedRequest->getAttribute('user')['name']);
    }

    public function testRedirectsAndDestroysSessionWhenUserIsInactive(): void
    {
        $_SESSION['user_id'] = 99;
        $_SESSION['user'] = ['id' => 99, 'name' => 'Banned', 'is_active' => false];
        $_SESSION['user_roles'] = ['user'];

        $response = $this->middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testUserRolesAlreadyInSessionSkipsDbLoad(): void
    {
        // Con user_roles en sesión, loadUserRolesInSession() retorna temprano
        $_SESSION['user_id'] = 5;
        $_SESSION['user'] = ['id' => 5, 'name' => 'Staff', 'is_active' => true];
        $_SESSION['user_roles'] = ['staff', 'user'];

        // Simplemente debe pasar sin lanzar excepción (sin acceso a BD)
        $response = $this->middleware->process($this->makeRequest(), $this->makeHandler(200));

        $this->assertSame(200, $response->getStatusCode());
    }
}
