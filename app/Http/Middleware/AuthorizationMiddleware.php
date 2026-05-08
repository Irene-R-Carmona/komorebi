<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Http\ResponseFactory;
use App\Core\Logger;
use App\Core\Session;
use JsonException;
use Override;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware PSR-15 para control de permisos granulares (RBAC).
 *
 * Verifica que el usuario autenticado tenga un permiso específico.
 * Soporta caché de permisos en Redis para mejor performance.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    private const int CACHE_TTL = Cache::TTL_HOUR;

    private ResponseFactory $response;
    private string $permission;

    /**
     * @param string          $permission Permiso requerido (ej: 'cafe.edit', 'review.moderate')
     */
    public function __construct(ResponseFactory $response, string $permission)
    {
        $this->response = $response;
        $this->permission = $permission;
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        Session::start();

        $userId = Session::userId();

        if (!$userId) {
            return $this->unauthorizedResponse($request, 'No autenticado');
        }

        // Admin siempre tiene acceso total
        $userRoles = Session::get('user_roles', []);
        if (\in_array('admin', $userRoles, true)) {
            return $handler->handle($request);
        }

        // Verificar permiso con caché
        if (!$this->userHasPermission($userId, $this->permission)) {
            Logger::warning('[AuthorizationMiddleware] Permiso denegado', [
                'user_id' => $userId,
                'permission' => $this->permission,
                'roles' => $userRoles,
            ]);

            return $this->unauthorizedResponse($request, 'Permiso insuficiente');
        }

        return $handler->handle($request);
    }

    /**
     * Verifica si el usuario tiene el permiso solicitado.
     * Usa caché Redis (TTL 1h) para evitar queries repetitivas.
     */
    private function userHasPermission(int $userId, string $permission): bool
    {
        $cacheKey = "user_permissions:$userId";

        try {
            $cachedPermissions = Cache::get($cacheKey);

            if (\is_array($cachedPermissions)) {
                return \in_array($permission, $cachedPermissions, true);
            }

            // No está en caché, consultar BD
            $permissions = $this->fetchUserPermissionsFromDb($userId);

            Cache::set($cacheKey, $permissions, self::CACHE_TTL);

            return \in_array($permission, $permissions, true);
        } catch (Throwable $e) {
            Logger::error('[AuthorizationMiddleware] Error verificando permisos', [
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage(),
            ]);

            // En caso de error, denegar acceso por seguridad
            return false;
        }
    }

    /**
     * Obtiene todos los permisos del usuario desde BD.
     *
     * @return array<string> Lista de códigos de permisos
     */
    private function fetchUserPermissionsFromDb(int $userId): array
    {
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare(
                'SELECT DISTINCT p.code
                 FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                 WHERE ur.user_id = :user_id'
            );

            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            Logger::error('[AuthorizationMiddleware] Error consultando permisos', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Genera respuesta de error según tipo de request (API o Web).
     */
    private function unauthorizedResponse(ServerRequestInterface $request, string $reason): ResponseInterface
    {
        if ($this->isApiRequest($request)) {
            try {
                return $this->response->json([
                    'error' => 'No tienes permisos para realizar esta acción.',
                    'required_permission' => $this->permission,
                ], 403);
            } catch (JsonException) {
                return $this->response->createResponse(403);
            }
        }

        // Para requests web, redirigir con mensaje
        return $this->response->redirect('/unauthorized?reason=' . \urlencode($reason), 302);
    }

    /**
     * Detecta si la request es API/AJAX.
     */
    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        if (\str_contains($accept, 'application/json')) {
            return true;
        }

        $xrw = $request->getHeaderLine('X-Requested-With');
        if (\strtolower($xrw) === 'xmlhttprequest') {
            return true;
        }

        $path = $request->getUri()->getPath();
        if (\str_starts_with($path, '/api/')) {
            return true;
        }

        return false;
    }
}
