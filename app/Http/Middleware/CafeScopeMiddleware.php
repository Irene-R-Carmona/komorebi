<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de validación de ownership sobre café
 *
 * Valida que el usuario (Manager) tenga un café asignado
 * antes de acceder a rutas de gestión de café.
 *
 * Uso: $mw->ownsCafe() en rutas /manager/cafe/*
 */
final class CafeScopeMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Session::user();
        $cafeId = $user['cafe_id'] ?? null;

        // Validar que el usuario tenga un café asignado
        if (!$cafeId) {
            if ($this->isApiRequest($request)) {
                return $this->response->json([
                    'success' => false,
                    'error' => 'No tienes un café asignado. Contacta con un administrador.',
                ], 403);
            }

            // Redirección web
            return $this->response->redirect('/manager/dashboard');
        }

        // Validar que el cafeId de la ruta coincida con el café del usuario en sesión
        $routeCafeId = $request->getAttribute('cafeId');
        if ($routeCafeId !== null && (int) $routeCafeId !== (int) $cafeId) {
            if ($this->isApiRequest($request)) {
                return $this->response->json([
                    'success' => false,
                    'error'   => 'Acceso denegado a este recurso.',
                ], 403);
            }
            return $this->response->redirect('/manager/dashboard');
        }

        // Café asignado válido y scope verificado, continuar con el request
        return $handler->handle($request);
    }

    /**
     * Detecta si es una petición API
     */
    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        if (\str_contains($acceptHeader, 'application/json')) {
            return true;
        }

        $requestedWith = $request->getHeaderLine('X-Requested-With');
        if ($requestedWith === 'XMLHttpRequest') {
            return true;
        }

        $path = $request->getUri()->getPath();
        if (\str_starts_with($path, '/api/')) {
            return true;
        }

        return false;
    }
}
