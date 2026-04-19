<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Http\ResponseFactory;
use App\Core\Session;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware PSR-15 para rutas de invitados (guest).
 *
 * Redirige usuarios autenticados a su página principal.
 * Solo permite acceso a usuarios NO logueados.
 */
final class GuestMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    private string $redirectTo;

    private const array ROLE_HOMES = [
        'admin' => '/admin/dashboard',
        'manager' => '/manager/dashboard',
        'supervisor' => '/supervisor/dashboard',
        'reception' => '/ops/reception',
        'kitchen' => '/ops/kitchen',
        'keeper' => '/keeper/dashboard',
        'user' => '/profile',
    ];

    public function __construct(ResponseFactory $response, string $redirectTo = '/')
    {
        $this->response = $response;
        $this->redirectTo = $redirectTo;
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        Session::start();
        $userId = Session::userId();

        if ($userId && $userId > 0) {
            $roles = Session::get('user_roles', []);
            $primaryRole = $roles[0] ?? 'user';
            $home = self::ROLE_HOMES[$primaryRole] ?? $this->redirectTo;

            return $this->response->redirect($home, 302);
        }

        return $handler->handle($request);
    }
}
