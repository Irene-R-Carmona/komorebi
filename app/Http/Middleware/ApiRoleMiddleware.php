<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 de control de roles para rutas de API.
 *
 * Devuelve siempre JSON 403 — nunca redirige ni usa Flash.
 * Usar en rutas bajo /api/.
 */
final class ApiRoleMiddleware implements MiddlewareInterface
{
    /** @var array<string> */
    private array $allowedRoles;

    /**
     * @param array<string>|string $allowedRoles
     */
    public function __construct(
        private readonly ResponseFactory $response,
        array|string $allowedRoles,
    ) {
        $this->allowedRoles = \is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    }

    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        Session::start();

        /** @var array<string> $userRoles */
        $userRoles = $request->getAttribute('user_roles', Session::get('user_roles', []));

        if (\in_array('admin', $userRoles, true)) {
            return $handler->handle($request);
        }

        $hasRole = \count(\array_intersect($userRoles, $this->allowedRoles)) > 0;

        if (!$hasRole) {
            Logger::warning('[ApiRoleMiddleware] Acceso denegado', [
                'required_roles' => $this->allowedRoles,
                'user_roles' => $userRoles,
            ]);

            return $this->response->json([
                'ok' => false,
                'error' => 'No tienes permisos para acceder a este recurso.',
                'code' => 'forbidden',
            ], 403);
        }

        return $handler->handle($request);
    }
}
