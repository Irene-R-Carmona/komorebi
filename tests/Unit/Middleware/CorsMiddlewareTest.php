<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de CorsMiddleware PSR-15: manejo de preflight OPTIONS y peticiones reales.
 *
 * ¿Qué me quieres demostrar?
 * Que el middleware añade los headers CORS correctos, bloquea orígenes no permitidos,
 * cortocircuita las peticiones OPTIONS sin llamar al handler, y rechaza configuración
 * inválida (credenciales + wildcard).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la lógica de preflight, el manejo de Access-Control-Allow-Origin,
 * la validación de credenciales+wildcard, o la propagación a peticiones reales.
 */

namespace Tests\Unit\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\CorsMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory         = new Psr17Factory();
        $this->responseFactory = new ResponseFactory();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeRequest(string $method, string $origin = '', array $headers = []): ServerRequestInterface
    {
        $request = new ServerRequest($method, 'http://example.com/api/v1/holidays');
        if ($origin !== '') {
            $request = $request->withHeader('Origin', $origin);
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        return new class($status, $this->factory) implements RequestHandlerInterface {
            public int $callCount = 0;

            public function __construct(private readonly int $status, private readonly Psr17Factory $factory) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                return $this->factory->createResponse($this->status);
            }
        };
    }

    // -------------------------------------------------------------------------
    // Test 1 — Sin Origin header: pasar al handler sin CORS headers
    // -------------------------------------------------------------------------

    public function testPassesThroughRequestWithoutOriginHeader(): void
    {
        $mw      = new CorsMiddleware($this->responseFactory, ['https://app.example.com']);
        $handler = $this->makeHandler();
        $request = $this->makeRequest('GET'); // sin Origin

        $response = $mw->process($request, $handler);

        $this->assertSame(1, $handler->callCount, 'Debe llamar al handler');
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'), 'No debe añadir headers CORS sin Origin');
    }

    // -------------------------------------------------------------------------
    // Test 2 — Preflight OPTIONS con origen permitido → 204 + headers correctos
    // -------------------------------------------------------------------------

    public function testPreflightReturns204WithCorsHeaders(): void
    {
        $origin = 'https://app.example.com';
        $mw     = new CorsMiddleware($this->responseFactory, [$origin]);
        $request = $this->makeRequest('OPTIONS', $origin, [
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response = $mw->process($request, $this->makeHandler());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Max-Age'));
        $this->assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    // -------------------------------------------------------------------------
    // Test 3 — Preflight OPTIONS NO llama al handler
    // -------------------------------------------------------------------------

    public function testPreflightDoesNotCallHandlerForOptions(): void
    {
        $origin  = 'https://app.example.com';
        $mw      = new CorsMiddleware($this->responseFactory, [$origin]);
        $handler = $this->makeHandler();
        $request = $this->makeRequest('OPTIONS', $origin);

        $mw->process($request, $handler);

        $this->assertSame(0, $handler->callCount, 'El handler NO debe ser llamado en OPTIONS preflight');
    }

    // -------------------------------------------------------------------------
    // Test 4 — Preflight con origen NO permitido → 403
    // -------------------------------------------------------------------------

    public function testPreflightReturns403ForDisallowedOrigin(): void
    {
        $mw     = new CorsMiddleware($this->responseFactory, ['https://allowed.example.com']);
        $request = $this->makeRequest('OPTIONS', 'https://evil.com');

        $response = $mw->process($request, $this->makeHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // Test 5 — Petición real con origen permitido → añade Access-Control-Allow-Origin
    // -------------------------------------------------------------------------

    public function testActualRequestAddsAllowOriginHeader(): void
    {
        $origin  = 'https://app.example.com';
        $mw      = new CorsMiddleware($this->responseFactory, [$origin]);
        $handler = $this->makeHandler(200);
        $request = $this->makeRequest('GET', $origin);

        $response = $mw->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame(1, $handler->callCount, 'Debe llamar al handler en petición real');
    }

    // -------------------------------------------------------------------------
    // Test 6 — Wildcard: cualquier origen → Access-Control-Allow-Origin: *
    // -------------------------------------------------------------------------

    public function testWildcardOriginAcceptsAnyOrigin(): void
    {
        $mw      = new CorsMiddleware($this->responseFactory, ['*']);
        $request = $this->makeRequest('OPTIONS', 'https://cualquiera.com');

        $response = $mw->process($request, $this->makeHandler());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // Test 7 — credentials=true añade Access-Control-Allow-Credentials
    // -------------------------------------------------------------------------

    public function testCredentialsModeAddsHeader(): void
    {
        $origin  = 'https://app.example.com';
        $mw      = new CorsMiddleware($this->responseFactory, [$origin], credentials: true);
        $request = $this->makeRequest('GET', $origin);

        $response = $mw->process($request, $this->makeHandler());

        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    // -------------------------------------------------------------------------
    // Test 8 — credentials=true + wildcard → InvalidArgumentException
    // -------------------------------------------------------------------------

    public function testCredentialsPlusWildcardThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CorsMiddleware($this->responseFactory, ['*'], credentials: true);
    }
}
