<?php

/**
 * ¿Qué pruebas aquí?
 * Los 8 renderers concretos de excepciones: supports(), priority() y render() en modo API.
 * ¿Qué me quieres demostrar?
 * Que cada renderer soporta la excepción correcta, rechaza otras, y retorna el HTTP status correcto.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si supports() devuelve true para excepciones incorrectas, si el status HTTP cambia,
 * o si el Content-Type no es JSON en modo API.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\ExceptionRenderers;

use App\Core\Http\ResponseFactory;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\DatabaseException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Http\ExceptionRenderers\AuthenticationExceptionRenderer;
use App\Http\ExceptionRenderers\AuthorizationExceptionRenderer;
use App\Http\ExceptionRenderers\BusinessRuleExceptionRenderer;
use App\Http\ExceptionRenderers\DatabaseExceptionRenderer;
use App\Http\ExceptionRenderers\FallbackExceptionRenderer;
use App\Http\ExceptionRenderers\NotFoundExceptionRenderer;
use App\Http\ExceptionRenderers\RateLimitExceptionRenderer;
use App\Http\ExceptionRenderers\ValidationExceptionRenderer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ExceptionRenderersTest extends TestCase
{
    private ResponseFactory     $responseFactory;
    private Psr17Factory        $psr17;
    private ServerRequestInterface $apiRequest;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->psr17           = new Psr17Factory();
        $this->apiRequest      = $this->psr17
            ->createServerRequest('GET', '/')
            ->withHeader('Accept', 'application/json');
    }

    // -----------------------------------------------------------------------
    // ValidationExceptionRenderer
    // -----------------------------------------------------------------------

    public function testValidationRendererSupportsValidationException(): void
    {
        $r = new ValidationExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new ValidationException()));
    }

    public function testValidationRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new ValidationExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testValidationRendererReturns422(): void
    {
        $r   = new ValidationExceptionRenderer($this->responseFactory);
        $res = $r->render(new ValidationException('Datos inválidos', ['name' => 'requerido']), $this->apiRequest);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function testValidationRendererReturnsJsonContentType(): void
    {
        $r   = new ValidationExceptionRenderer($this->responseFactory);
        $res = $r->render(new ValidationException(), $this->apiRequest);
        $this->assertStringContainsString('application/json', $res->getHeaderLine('Content-Type'));
    }

    // -----------------------------------------------------------------------
    // NotFoundExceptionRenderer
    // -----------------------------------------------------------------------

    public function testNotFoundRendererSupportsNotFoundException(): void
    {
        $r = new NotFoundExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new NotFoundException()));
    }

    public function testNotFoundRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new NotFoundExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testNotFoundRendererReturns404(): void
    {
        $r   = new NotFoundExceptionRenderer($this->responseFactory);
        $res = $r->render(new NotFoundException('No encontrado'), $this->apiRequest);
        $this->assertSame(404, $res->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // AuthenticationExceptionRenderer
    // -----------------------------------------------------------------------

    public function testAuthenticationRendererSupportsAuthenticationException(): void
    {
        $r = new AuthenticationExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new AuthenticationException()));
    }

    public function testAuthenticationRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new AuthenticationExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testAuthenticationRendererReturns401(): void
    {
        $r   = new AuthenticationExceptionRenderer($this->responseFactory);
        $res = $r->render(new AuthenticationException(), $this->apiRequest);
        $this->assertSame(401, $res->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // AuthorizationExceptionRenderer
    // -----------------------------------------------------------------------

    public function testAuthorizationRendererSupportsAuthorizationException(): void
    {
        $r = new AuthorizationExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new AuthorizationException()));
    }

    public function testAuthorizationRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new AuthorizationExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testAuthorizationRendererReturns403(): void
    {
        $r   = new AuthorizationExceptionRenderer($this->responseFactory);
        $res = $r->render(new AuthorizationException(), $this->apiRequest);
        $this->assertSame(403, $res->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // BusinessRuleExceptionRenderer
    // -----------------------------------------------------------------------

    public function testBusinessRuleRendererSupportsBusinessRuleException(): void
    {
        $r = new BusinessRuleExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new BusinessRuleException('regla')));
    }

    public function testBusinessRuleRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new BusinessRuleExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testBusinessRuleRendererReturnsHttpCode(): void
    {
        $r   = new BusinessRuleExceptionRenderer($this->responseFactory);
        $res = $r->render(new BusinessRuleException('regla rota'), $this->apiRequest);
        $this->assertSame(400, $res->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // RateLimitExceptionRenderer
    // -----------------------------------------------------------------------

    public function testRateLimitRendererSupportsRateLimitException(): void
    {
        $r = new RateLimitExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new RateLimitException()));
    }

    public function testRateLimitRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new RateLimitExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testRateLimitRendererReturns429(): void
    {
        $r   = new RateLimitExceptionRenderer($this->responseFactory);
        $res = $r->render(new RateLimitException('Demasiadas peticiones', 60), $this->apiRequest);
        $this->assertSame(429, $res->getStatusCode());
    }

    public function testRateLimitRendererSetsRetryAfterHeader(): void
    {
        $r   = new RateLimitExceptionRenderer($this->responseFactory);
        $res = $r->render(new RateLimitException('Demasiadas peticiones', 120), $this->apiRequest);
        $this->assertSame('120', $res->getHeaderLine('Retry-After'));
    }

    // -----------------------------------------------------------------------
    // DatabaseExceptionRenderer
    // -----------------------------------------------------------------------

    public function testDatabaseRendererSupportsDatabaseException(): void
    {
        $r = new DatabaseExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new DatabaseException('error db')));
    }

    public function testDatabaseRendererDoesNotSupportOtherExceptions(): void
    {
        $r = new DatabaseExceptionRenderer($this->responseFactory);
        $this->assertFalse($r->supports(new \RuntimeException()));
    }

    public function testDatabaseRendererReturns500(): void
    {
        $r   = new DatabaseExceptionRenderer($this->responseFactory);
        $res = $r->render(new DatabaseException('fallo db'), $this->apiRequest);
        $this->assertSame(500, $res->getStatusCode());
    }

    // -----------------------------------------------------------------------
    // FallbackExceptionRenderer
    // -----------------------------------------------------------------------

    public function testFallbackRendererSupportsAnyThrowable(): void
    {
        $r = new FallbackExceptionRenderer($this->responseFactory);
        $this->assertTrue($r->supports(new \RuntimeException()));
        $this->assertTrue($r->supports(new \LogicException()));
        $this->assertTrue($r->supports(new \Error()));
        $this->assertTrue($r->supports(new ValidationException()));
    }

    public function testFallbackRendererReturns500(): void
    {
        $r   = new FallbackExceptionRenderer($this->responseFactory);
        $res = $r->render(new \RuntimeException('boom'), $this->apiRequest);
        $this->assertSame(500, $res->getStatusCode());
    }

    public function testFallbackRendererHasLowestPriority(): void
    {
        $fallback   = new FallbackExceptionRenderer($this->responseFactory);
        $validation = new ValidationExceptionRenderer($this->responseFactory);

        $this->assertLessThan($validation->priority(), $fallback->priority());
    }
}
