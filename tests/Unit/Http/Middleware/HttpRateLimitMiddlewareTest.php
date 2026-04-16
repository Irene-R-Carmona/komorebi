<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Http\Middleware\HttpRateLimitMiddleware;
use App\Services\Contracts\RateLimitingServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ¿Qué pruebas aquí?
 * El middleware HttpRateLimitMiddleware y su interacción con RateLimitingService.
 *
 * ¿Qué me quieres demostrar?
 * Que peticiones no bloqueadas pasan al handler registrando el intento,
 * y peticiones bloqueadas reciben 429 con header Retry-After.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en la integración con RateLimitingService,
 * en el código HTTP retornado, o en el header Retry-After romperá estos tests.
 */
final class HttpRateLimitMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->responseFactory->createResponse(200));
        $this->handler = $handler;
    }

    private function makeRequest(string $ip = '127.0.0.1'): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => $ip]);

        return $request;
    }

    public function testNotBlockedPassesThroughAndRecordsAttempt(): void
    {
        $rateLimiter = $this->createMock(RateLimitingServiceInterface::class);
        $rateLimiter->method('isBlocked')->willReturn(['blocked' => false]);
        $rateLimiter->expects($this->once())->method('recordAttempt');

        $mw = new HttpRateLimitMiddleware($this->responseFactory, $rateLimiter, 'login');
        $response = $mw->process($this->makeRequest(), $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBlockedReturns429(): void
    {
        $rateLimiter = $this->createStub(RateLimitingServiceInterface::class);
        $rateLimiter->method('isBlocked')->willReturn([
            'blocked' => true,
            'minutes_remaining' => 10,
        ]);

        $mw = new HttpRateLimitMiddleware($this->responseFactory, $rateLimiter, 'login');
        $response = $mw->process($this->makeRequest(), $this->handler);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testBlockedResponseIncludesRetryAfterHeader(): void
    {
        $rateLimiter = $this->createStub(RateLimitingServiceInterface::class);
        $rateLimiter->method('isBlocked')->willReturn([
            'blocked' => true,
            'minutes_remaining' => 5,
        ]);

        $mw = new HttpRateLimitMiddleware($this->responseFactory, $rateLimiter, 'login');
        $response = $mw->process($this->makeRequest(), $this->handler);

        $this->assertTrue($response->hasHeader('Retry-After'));
        $this->assertSame('300', $response->getHeaderLine('Retry-After')); // 5 min * 60
    }

    public function testBlockedDoesNotCallHandler(): void
    {
        $rateLimiter = $this->createStub(RateLimitingServiceInterface::class);
        $rateLimiter->method('isBlocked')->willReturn([
            'blocked' => true,
            'minutes_remaining' => 1,
        ]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $mw = new HttpRateLimitMiddleware($this->responseFactory, $rateLimiter, 'login');
        $mw->process($this->makeRequest(), $handler);
    }

    public function testUsesRemoteAddrAsIdentifier(): void
    {
        $rateLimiter = $this->createMock(RateLimitingServiceInterface::class);
        $rateLimiter->expects($this->once())
            ->method('isBlocked')
            ->with('registration', '192.168.1.1')
            ->willReturn(['blocked' => false]);
        $rateLimiter->expects($this->once())
            ->method('recordAttempt')
            ->with('registration', '192.168.1.1');

        $mw = new HttpRateLimitMiddleware($this->responseFactory, $rateLimiter, 'registration');
        $mw->process($this->makeRequest('192.168.1.1'), $this->handler);
    }
}
