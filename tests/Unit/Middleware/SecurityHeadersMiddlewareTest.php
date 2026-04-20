<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Middleware;

use App\Middleware\SecurityHeadersMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tests para SecurityHeadersMiddleware
 *
 * Verifica:
 * - Generación de nonces CSP únicos
 * - Aplicación correcta de headers de seguridad
 * - CSP con nonce dinámico
 * - Exposición del nonce en $GLOBALS['cspNonce']
 */
#[CoversClass(SecurityHeadersMiddleware::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    private SecurityHeadersMiddleware $middleware;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->middleware = new SecurityHeadersMiddleware();
        $this->factory = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        // Limpiar nonce global
        unset($GLOBALS['cspNonce']);
    }

    public function testMiddlewareGeneratesUniqueNonce(): void
    {
        // Crear múltiples instancias y verificar nonces únicos
        $middleware1 = new SecurityHeadersMiddleware();
        $nonce1 = $GLOBALS['cspNonce'] ?? '';

        unset($GLOBALS['cspNonce']);

        $middleware2 = new SecurityHeadersMiddleware();
        $nonce2 = $GLOBALS['cspNonce'] ?? '';

        $this->assertNotEmpty($nonce1);
        $this->assertNotEmpty($nonce2);
        $this->assertNotEquals($nonce1, $nonce2, 'Los nonces deben ser únicos por request');
    }

    public function testNonceIsBase64Encoded(): void
    {
        $nonce = $GLOBALS['cspNonce'] ?? '';

        $this->assertNotEmpty($nonce);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce, 'Nonce debe ser base64 válido');

        // Verificar que se puede decodificar
        $decoded = \base64_decode($nonce, true);
        $this->assertNotFalse($decoded, 'Nonce debe ser base64 válido decodificable');
    }

    public function testMiddlewareAddsContentSecurityPolicyHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('Content-Security-Policy'));

        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('script-src', $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp, 'Alpine.js requiere unsafe-eval');
    }

    public function testContentSecurityPolicyIncludesNonce(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $csp = $response->getHeaderLine('Content-Security-Policy');
        $nonce = $GLOBALS['cspNonce'] ?? '';

        $this->assertNotEmpty($nonce);
        $this->assertStringContainsString("'nonce-{$nonce}'", $csp, 'CSP debe incluir el nonce dinámico');
    }

    public function testMiddlewareAddsStrictTransportSecurityHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('Strict-Transport-Security'));

        $hsts = $response->getHeaderLine('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
        $this->assertStringContainsString('preload', $hsts);
    }

    public function testMiddlewareAddsXFrameOptionsHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('X-Frame-Options'));
        $this->assertEquals('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testMiddlewareAddsXContentTypeOptionsHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testMiddlewareAddsXXSSProtectionHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('X-XSS-Protection'));
        $this->assertEquals('1; mode=block', $response->getHeaderLine('X-XSS-Protection'));
    }

    public function testMiddlewareAddsReferrerPolicyHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->getHeaderLine('Referrer-Policy'));
    }

    public function testMiddlewareAddsPermissionsPolicyHeader(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('Permissions-Policy'));

        $policy = $response->getHeaderLine('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }

    public function testMiddlewareRemovesServerHeader(): void
    {
        $request = $this->createMockRequest();

        // Handler que retorna response con header Server
        $handler = $this->createMock(RequestHandlerInterface::class);
        $responseWithServer = $this->factory->createResponse(200)
            ->withHeader('Server', 'Apache/2.4.51');
        $handler->method('handle')->willReturn($responseWithServer);

        $response = $this->middleware->process($request, $handler);

        $this->assertFalse($response->hasHeader('Server'), 'Header Server debe ser removido por seguridad');
    }

    public function testMiddlewareAddsCacheControlHeaders(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($response->hasHeader('Cache-Control'));
        $this->assertTrue($response->hasHeader('Pragma'));
        $this->assertTrue($response->hasHeader('Expires'));

        $cacheControl = $response->getHeaderLine('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function testGetNonceReturnsCurrentNonce(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $expectedNonce = $GLOBALS['cspNonce'] ?? '';

        $actualNonce = SecurityHeadersMiddleware::getNonce();

        $this->assertEquals($expectedNonce, $actualNonce);
    }

    public function testGetNonceReturnsEmptyStringWhenNotSet(): void
    {
        unset($GLOBALS['cspNonce']);

        $nonce = SecurityHeadersMiddleware::getNonce();

        $this->assertSame('', $nonce);
    }

    public function testContentSecurityPolicyIncludesAllRequiredDirectives(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $csp = $response->getHeaderLine('Content-Security-Policy');

        // Verificar directivas críticas
        $requiredDirectives = [
            "default-src 'self'",
            'script-src',
            'style-src',
            'font-src',
            'img-src',
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ];

        foreach ($requiredDirectives as $directive) {
            $this->assertStringContainsString(
                $directive,
                $csp,
                "CSP debe contener directiva: {$directive}"
            );
        }
    }

    public function testContentSecurityPolicyAllowsBootstrapCDN(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $csp = $response->getHeaderLine('Content-Security-Policy');

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp, 'Debe permitir Bootstrap CDN');
    }

    public function testContentSecurityPolicyAllowsGoogleFonts(): void
    {
        $request = $this->createMockRequest();
        $handler = $this->createMockHandler();

        $response = $this->middleware->process($request, $handler);

        $csp = $response->getHeaderLine('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createMockRequest(): ServerRequestInterface
    {
        return new ServerRequest('GET', '/');
    }

    private function createMockHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response(200));

        return $handler;
    }
}
