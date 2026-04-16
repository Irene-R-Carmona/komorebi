<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Core\Logger;
use App\Core\Session;
use App\Core\View;
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
            'suggestedLink' => $this->getSuggestedLink($path),
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
            'suggestedLink' => $this->getSuggestedLink('/'),
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

        View::render('errors/419', [
            'titulo' => '419 - Sesión expirada',
            'suggestion' => 'Por favor, recarga la página e intenta de nuevo.',
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
            'suggestedLink' => $this->getSuggestedLink('/'),
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

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el path de la petición actual.
     *
     * @return false|string
     */
    private function getRequestPath(): string|false
    {
        return \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    /**
     * Sugiere un enlace de retorno basado en el contexto.
     *
     * @return array{href: string, label: string}
     */
    private function getSuggestedLink(string $path): array
    {
        // Si está autenticado y en backoffice, sugerir su dashboard
        if (Session::isAuthenticated()) {
            $role = Session::role();

            // Si la URL es del backoffice, sugerir dashboard del rol
            if ($this->isBackofficePath($path)) {
                return match ($role) {
                    'admin' => ['href' => '/admin/dashboard', 'label' => 'Volver al Dashboard'],
                    'manager' => ['href' => '/manager/dashboard', 'label' => 'Volver al Dashboard'],
                    'keeper' => ['href' => '/keeper/dashboard', 'label' => 'Volver a Bienestar'],
                    'staff' => ['href' => '/ops/reception', 'label' => 'Volver a Operaciones'],
                    default => ['href' => '/', 'label' => 'Volver al inicio'],
                };
            }
        }

        // Por defecto, ir al inicio
        return ['href' => '/', 'label' => 'Volver al inicio'];
    }

    /**
     * Verifica si un path pertenece al backoffice.
     */
    private function isBackofficePath(string $path): bool
    {
        $backofficePrefix = ['/admin', '/manager', '/ops', '/keeper'];

        return \array_any($backofficePrefix, static fn ($prefix) => \str_starts_with($path, $prefix));
    }
}
