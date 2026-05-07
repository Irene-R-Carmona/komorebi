<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Database;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Result;
use App\Core\Session;
use App\Services\Contracts\ApiTokenServiceInterface;
use Override;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware PSR-15 de autenticación para rutas de API.
 *
 * Devuelve siempre JSON — nunca redirige ni usa Flash.
 * Usar en rutas bajo /api/.
 */
final class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactory $response,
        private readonly ?ApiTokenServiceInterface $tokenService = null,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // ── Bearer token path ────────────────────────────────────────────────
        $authHeader = $request->getHeaderLine('Authorization');
        if (\str_starts_with($authHeader, 'Bearer ')) {
            $plain = \trim(\substr($authHeader, 7));
            $result = $this->tokenService?->validate($plain);

            if ($result === null) {
                Logger::warning('[ApiAuth] Bearer token rejected — service unavailable', [
                    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                    'path' => $request->getRequestTarget(),
                ]);

                return $this->response->problem(Result::fail('Token inválido o expirado.', 'invalid_token'), 401);
            }
            if (!$result->ok) {
                $detail = $result->error ?? 'Token inválido o expirado.';
                $code = $result->code ?? 'invalid_token';

                Logger::warning('[ApiAuth] Bearer token rejected', [
                    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                    'path' => $request->getRequestTarget(),
                    'token_prefix' => \substr(\hash('sha256', $plain), 0, 8),
                ]);

                return $this->response->problem(Result::fail($detail, $code), 401);
            }

            /** @var array{user_id: int, user: array<string,mixed>, user_roles: array<string>, token_id: int} $data */
            $data = $result->data;
            $request = $request->withAttribute('user_id', $data['user_id'])
                ->withAttribute('user', $data['user'])
                ->withAttribute('user_roles', $data['user_roles'])
                ->withAttribute('auth_method', 'bearer');

            return $handler->handle($request);
        }
        // ── Session path (sin cambios) ────────────────────────────────────────
        Session::start();

        $userId = Session::get('user_id');

        if (empty($userId)) {
            Logger::warning('[ApiAuth] Unauthenticated request', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'path' => $request->getRequestTarget(),
            ]);

            return $this->response->json([
                'ok' => false,
                'error' => 'No autenticado.',
                'code' => 'unauthenticated',
            ], 401);
        }

        $sessionUser = Session::get('user');
        if (!empty($sessionUser) && isset($sessionUser['id']) && (int) $sessionUser['id'] === (int) $userId) {
            $user = $sessionUser;
        } else {
            $user = $this->fetchUserFromDb((int) $userId);
            if ($user !== null) {
                Session::set('user', $user);
            }
        }

        if (!$user || !$user['is_active']) {
            Logger::warning('[ApiAuth] Inactive account attempt', [
                'user_id' => $userId,
            ]);
            Session::destroy();

            return $this->response->json([
                'ok' => false,
                'error' => 'Cuenta desactivada.',
                'code' => 'account_disabled',
            ], 401);
        }

        $this->loadUserRolesInSession((int) $userId);

        $request = $request
            ->withAttribute('user_id', (int) $userId)
            ->withAttribute('user', $user)
            ->withAttribute('user_roles', Session::get('user_roles') ?? ['user'])
            ->withAttribute('auth_method', 'session');

        return $handler->handle($request);
    }

    private function fetchUserFromDb(int $userId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT id, name, email, is_active, cafe_id FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (Throwable $e) {
            Logger::error('[ApiAuthMiddleware] Error fetching user', ['user_id' => $userId, 'error' => $e->getMessage()]);

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
            Logger::error('[ApiAuthMiddleware] Error loading roles', ['user_id' => $userId, 'error' => $e->getMessage()]);
            Session::set('user_roles', ['user']);
        }
    }
}
