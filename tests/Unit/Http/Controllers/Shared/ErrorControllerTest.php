<?php

/**
 * ¿Qué pruebas aquí?
 * Los métodos redirect(), rateLimited() y serviceUnavailable() de ErrorController.
 *
 * ¿Qué me quieres demostrar?
 * Que redirect() valida que la URL destino sea same-origin (empieza con '/').
 * Que redirect() clampea el delay entre 1 y 30 segundos.
 * Que URLs externas o con protocolo relativo ('//') se reemplazan por '/'.
 * Que rateLimited() y serviceUnavailable() renderizan sin lanzar excepciones.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la validación de same-origin en redirect().
 * Si el clamping de delay se cambia fuera del rango 1-30.
 * Si se eliminan los métodos rateLimited() o serviceUnavailable().
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Shared;

use App\Http\Controllers\Shared\ErrorController;
use App\Services\Contracts\NavigationServiceInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorController::class)]
final class ErrorControllerTest extends TestCase
{
    private function makeController(): ErrorController
    {
        return new ErrorController(
            nav: $this->createStub(NavigationServiceInterface::class),
        );
    }

    private function makeRequest(string $path, array $query = []): ServerRequest
    {
        $request = new ServerRequest('GET', $path);
        if (!empty($query)) {
            $request = $request->withQueryParams($query);
        }

        return $request;
    }

    public function testRedirectWithValidSameOriginUrl(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/redirect', ['to' => '/dashboard', 'delay' => '3']);

        \ob_start();
        $controller->redirect($request);
        $output = \ob_get_clean();

        self::assertStringContainsString('/dashboard', (string) $output);
        self::assertStringContainsString('countdown-duration:3s', (string) $output);
    }

    public function testRedirectWithExternalUrlFallsBackToRoot(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/redirect', ['to' => 'https://evil.example.com', 'delay' => '5']);

        \ob_start();
        $controller->redirect($request);
        $output = \ob_get_clean();

        // Destination must be '/' — external URL rejected
        self::assertStringNotContainsString('evil.example.com', (string) $output);
    }

    public function testRedirectWithProtocolRelativeUrlFallsBackToRoot(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/redirect', ['to' => '//evil.example.com', 'delay' => '5']);

        \ob_start();
        $controller->redirect($request);
        $output = \ob_get_clean();

        self::assertStringNotContainsString('evil.example.com', (string) $output);
    }

    public function testRedirectWithNoParamFallsBackToRoot(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/redirect', []);

        \ob_start();
        $controller->redirect($request);
        $output = (string) \ob_get_clean();

        // Should render without throwing, destination defaults to /
        self::assertNotEmpty($output);
    }

    public function testRedirectDelayIsClamped(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/redirect', ['to' => '/home', 'delay' => '999']);

        \ob_start();
        $controller->redirect($request);
        $output = (string) \ob_get_clean();

        // Delay must be clamped to 30
        self::assertStringContainsString('countdown-duration:30s', $output);
        self::assertStringNotContainsString('countdown-duration:999s', $output);
    }

    public function testRateLimitedRendersCorrectView(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/error/429');

        \ob_start();
        $controller->rateLimited($request);
        $output = (string) \ob_get_clean();

        self::assertStringContainsString('429', $output);
        self::assertStringContainsString('Demasiadas solicitudes', $output);
    }

    public function testServiceUnavailableRendersCorrectView(): void
    {
        $controller = $this->makeController();
        $request = $this->makeRequest('/error/503');

        \ob_start();
        $controller->serviceUnavailable($request);
        $output = (string) \ob_get_clean();

        self::assertStringContainsString('503', $output);
        self::assertStringContainsString('Servicio no disponible', $output);
    }
}
