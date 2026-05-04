<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Container;
use App\Core\Logger;
use App\Core\Raw;
use App\Core\Session;
use App\Core\View;
use App\Services\Contracts\NavigationServiceInterface;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controlador de Errores HTTP
 *
 * Renderiza páginas de error con layout específico.
 */
final class ErrorController
{
    private NavigationServiceInterface $nav;

    public function __construct(?NavigationServiceInterface $nav = null)
    {
        $this->nav = $nav ?? Container::make(NavigationServiceInterface::class);
    }

    /**
     * 400 - Bad Request
     */
    public function badRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(400);
        }

        View::render('errors/400', [
            'titulo' => '400 - Solicitud incorrecta',
            'message' => '',
        ], [], 'errors');

        return null;
    }

    /**
     * 401 - Unauthorized
     */
    public function unauthorized(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(401);
        }

        View::render('errors/401', [
            'titulo' => '401 - No autenticado',
        ], [], 'errors');

        return null;
    }

    /**
     * 404 - Not Found
     */
    public function notFound(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(404);
        }

        $path = $this->getRequestPath();

        View::render('errors/404', [
            'titulo' => '404 - Página no encontrada',
            'requestedPath' => $path,
            'suggestedLink' => $this->nav->suggestedLink($path, Session::isAuthenticated(), Session::role()),
        ], [], 'errors');

        return null;
    }

    /**
     * 403 - Forbidden
     */
    public function forbidden(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(403);
        }

        View::render('errors/403', [
            'titulo' => '403 - Acceso denegado',
            'suggestedLink' => $this->nav->suggestedLink('/', Session::isAuthenticated(), Session::role()),
        ], [], 'errors');

        return null;
    }

    /**
     * 419 - Page Expired (CSRF)
     */
    public function pageExpired(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(419);
        }

        $refererPath = \parse_url(
            $request->getServerParams()['HTTP_REFERER'] ?? '/',
            PHP_URL_PATH
        ) ?: '/';

        View::render('errors/419', [
            'titulo' => '419 - Sesión expirada',
            'refererPath' => $refererPath,
        ], [], 'errors');

        return null;
    }

    /**
     * 429 - Too Many Requests
     */
    public function rateLimited(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(429);
        }

        $params = $request->getQueryParams();
        $retryAfter = isset($params['retry_after']) ? (int) $params['retry_after'] : null;
        $message = isset($params['message']) ? (string) $params['message'] : null;

        View::render('errors/429', [
            'titulo' => '429 - Demasiadas solicitudes',
            'retryAfter' => $retryAfter,
            'message' => $message,
        ], [], 'errors');

        return null;
    }

    /**
     * 503 - Service Unavailable
     */
    public function serviceUnavailable(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!\headers_sent()) {
            @\http_response_code(503);
        }

        $params = $request->getQueryParams();
        $message = isset($params['message']) ? (string) $params['message'] : null;
        $service = isset($params['service']) ? (string) $params['service'] : null;

        View::render('errors/503', [
            'titulo' => '503 - Servicio no disponible',
            'message' => $message,
            'service' => $service,
        ], [], 'errors');

        return null;
    }

    /**
     * Intersticial de redirección con countdown.
     *
     * Sólo acepta destinos same-origin: empieza con '/' y no con '//'.
     */
    public function redirect(ServerRequestInterface $request): ?ResponseInterface
    {
        $params = $request->getQueryParams();
        $to = (string) ($params['to'] ?? '/');
        $delay = (int) ($params['delay'] ?? 5);

        // Validar same-origin — rechazar URLs externas y protocol-relative
        if (!\str_starts_with($to, '/') || \str_starts_with($to, '//')) {
            $to = '/';
        }

        // Clamp delay 1–30 s
        $delay = \max(1, \min(30, $delay));

        $message = isset($params['message']) && $params['message'] !== ''
            ? (string) $params['message']
            : null;
        $cancelUrl = isset($params['cancel']) && \str_starts_with((string) $params['cancel'], '/')
            ? (string) $params['cancel']
            : '/';

        // meta refresh como Raw para que el layout lo inyecte en <head>
        $metaTo = \htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
        $extraHead = new Raw('<meta http-equiv="refresh" content="' . $delay . ';url=' . $metaTo . '">');

        View::render('redirect', [
            'titulo' => 'Redirigiendo…',
            'destination' => $to,
            'countdown' => $delay,
            'message' => $message,
            'cancelUrl' => $cancelUrl,
            'extraHead' => $extraHead,
        ], [], 'errors');

        return null;
    }

    /**
     * 500 - Internal Server Error
     */
    public function serverError(ServerRequestInterface $request): ?ResponseInterface
    {
        $exception = new Exception();
        Logger::error('[ErrorController] serverError() CALLED - Stack trace: ' . $exception->getTraceAsString(), ['trace' => $exception->getTraceAsString()]);

        if (!\headers_sent()) {
            @\http_response_code(500);
        }

        View::render('errors/500', [
            'titulo' => '500 - Error interno',
            'suggestedLink' => $this->nav->suggestedLink('/', Session::isAuthenticated(), Session::role()),
        ], [], 'errors');

        return null;
    }

    /**
     * Método genérico para cualquier código de error.
     */
    public function show(ServerRequestInterface $request, int $code): ?ResponseInterface
    {
        match ($code) {
            403 => $this->forbidden($request),
            404 => $this->notFound($request),
            419 => $this->pageExpired($request),
            default => $this->serverError($request),
        };

        return null;
    }

    private function getRequestPath(): string|false
    {
        return \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }
}
