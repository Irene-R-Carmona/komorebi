<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 para control de roles en rutas web.
 *
 * Si el usuario no tiene el rol requerido, redirige con Flash de error.
 * Para rutas de API usar ApiRoleMiddleware.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    /** @var array<string> */
    private array $allowedRoles;

    /**
     * @param array<string>|string $allowedRoles
     */
    public function __construct(ResponseFactory $response, array|string $allowedRoles)
    {
        $this->response = $response;
        $this->allowedRoles = \is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    }

    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        Session::start();

        $userRoles = Session::get('user_roles', []);

        // Admin siempre tiene acceso
        if (\in_array('admin', $userRoles, true)) {
            return $handler->handle($request);
        }

        // Verificar si usuario tiene alguno de los roles permitidos
        $hasRole = \count(\array_intersect($userRoles, $this->allowedRoles)) > 0;

        if (!$hasRole) {
            Logger::warning('[RoleMiddleware] Acceso denegado', [
                'required_roles' => $this->allowedRoles,
                'user_roles'     => $userRoles,
            ]);

            Flash::error('No tienes permisos para acceder a este recurso.');

            return $this->response->redirect('/', 302);
        }

        return $handler->handle($request);
    }
}
