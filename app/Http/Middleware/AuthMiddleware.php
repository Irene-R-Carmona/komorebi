<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Database;
use App\Core\Flash;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware PSR-15 para autenticación.
 *
 * Verifica que exista una sesión activa y usuario válido.
 * Si falla, redirige a /login.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private ResponseFactory $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    #[\Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        Session::start();

        $userId = Session::get('user_id');

        if (empty($userId)) {
            Flash::set('error', 'Debes iniciar sesión para acceder a esta página.');

            return $this->response->redirect('/login', 302);
        }

        // Verificar que el usuario existe y está activo.
        // Evitar consulta a BD si el usuario ya está en sesión (cache ligero).
        $sessionUser = Session::get('user');
        if (!empty($sessionUser) && isset($sessionUser['id']) && (int) $sessionUser['id'] === (int) $userId) {
            $user = $sessionUser;
        } else {
            $user = $this->fetchUserFromDb((int) $userId);
            if ($user !== null) {
                // Guardar representación ligera en sesión para evitar reconsultas
                Session::set('user', $user);
            }
        }

        if (!$user || !$user['is_active']) {
            Session::destroy();

            Flash::set('error', 'Tu cuenta ha sido desactivada.');

            return $this->response->redirect('/login', 302);
        }

        // Cargar roles en sesión si no están
        $this->loadUserRolesInSession((int) $userId);

        // Guardar usuario en atributos de request para acceso en controllers
        $request = $request->withAttribute('user_id', (int) $userId);
        $request = $request->withAttribute('user', $user);

        return $handler->handle($request);
    }

    private function fetchUserFromDb(int $userId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, email, is_active FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (Throwable $e) {
            Logger::error('[AuthMiddleware] Error fetching user', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function loadUserRolesInSession(int $userId): void
    {
        if (!empty(Session::get('user_roles'))) {
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT r.code FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?');
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            Session::set('user_roles', $roles);
        } catch (Throwable $e) {
            Logger::error('[AuthMiddleware] Error loading roles', ['user_id' => $userId, 'error' => $e->getMessage()]);
            Session::set('user_roles', ['user']);
        }
    }
}
