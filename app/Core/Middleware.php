<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Clase de middlewares para autenticación, autorización y seguridad HTTP.
 *
 * Proporciona middlewares reutilizables que se usan en las definiciones de rutas
 * para validar sesiones, roles, permisos y aplicar cabeceras de seguridad.
 */
final class Middleware
{
    // ─────────────────────────────────────────────────────────────
    // Constantes de roles
    // ─────────────────────────────────────────────────────────────

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_RECEPTION = 'reception';
    public const ROLE_KITCHEN = 'kitchen';
    public const ROLE_KEEPER = 'keeper';
    public const ROLE_USER = 'user';

    /**
     * Roles con acceso al backoffice.
     */
    public const array BACKOFFICE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_MANAGER,
        self::ROLE_SUPERVISOR,
        self::ROLE_RECEPTION,
        self::ROLE_KITCHEN,
        self::ROLE_KEEPER,
    ];

    /**
     * Rutas home por rol.
     */
    private const array ROLE_HOMES = [
        self::ROLE_ADMIN => '/admin/dashboard',
        self::ROLE_MANAGER => '/manager/dashboard',
        self::ROLE_SUPERVISOR => '/supervisor/dashboard',
        self::ROLE_RECEPTION => '/ops/reception',
        self::ROLE_KITCHEN => '/ops/kitchen',
        self::ROLE_KEEPER => '/keeper/dashboard',
        self::ROLE_USER => '/profile',
    ];

    // ─────────────────────────────────────────────────────────────
    // Middlewares públicos
    // ─────────────────────────────────────────────────────────────

    /**
     * Requiere autenticación.
     *
     * Valida que exista una sesión activa; si no, redirige a `/login`.
     */
    public static function auth(): void
    {
        Session::start();

        $userId = Session::get('user_id');

        if (empty($userId)) {
            Flash::set('error', 'Debes iniciar sesión para acceder a esta página.');
            if (!\headers_sent()) {
                \header('Location: /login');
            } else {
                Logger::error('[Middleware::auth] headers already sent; cannot redirect to /login', ['user_id' => $userId]);
            }
            if (PHP_SAPI === 'cli') {
                throw new \App\Exceptions\MiddlewareException('No autenticado', 'auth', 'not_authenticated');
            }
            exit;
        }

        // Solo verificar estado en BD si los roles aún no están cacheados en sesión.
        // Esto evita una consulta redundante por petición cuando ya se verificó antes
        // (e.g., en tests de unidad que pre-populan la sesión, o tras primera auth).
        if (!Session::has('user_roles')) {
            $user = self::fetchUserFromDb((int) $userId);

            // Solo forzar logout si el usuario existe y está marcado como inactivo.
            if (is_array($user) && !($user['is_active'] ?? true)) {
                self::forceLogout('account_locked');
            }

            self::loadUserRolesInSession((int) $userId);
        }
    }

    /**
     * Requiere que el usuario tenga uno de los roles especificados.
     *
     * @param string|array $allowedRoles Rol o array de roles permitidos
     */
    public static function role(string|array $allowedRoles): void
    {
        self::auth(); // Primero verificar autenticación
        $allowedRoles = \is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        $userRoles = (array) Session::get('user_roles', []);

        // Admin siempre tiene acceso (soportar roles como `super_admin`)
        foreach ($userRoles as $r) {
            $rStr = (string) $r;
            if ($rStr === self::ROLE_ADMIN || str_ends_with($rStr, '_admin')) {
                return;
            }
        }

        // Verificar si usuario tiene alguno de los roles permitidos
        $allowedRoles = array_map('strval', $allowedRoles);
        $hasRole = \count(\array_intersect($userRoles, $allowedRoles)) > 0;

        if (!$hasRole) {
            Logger::error('[Middleware::role] Acceso denegado', ['required' => $allowedRoles, 'actual' => $userRoles]);
            self::handleUnauthorized();
        }
    }

    /**
     * Verifica que usuario tenga permiso específico.
     *
     * @param string $permission Clave del permiso (ej: 'admin.users.view')
     */
    public static function can(string $permission): void
    {
        self::auth();

        $userRoles = (array) Session::get('user_roles', []);

        // Admin siempre tiene acceso completo (soportar roles como `super_admin`)
        foreach ($userRoles as $r) {
            $rStr = (string) $r;
            if ($rStr === self::ROLE_ADMIN || str_ends_with($rStr, '_admin')) {
                return;
            }
        }

        if (!self::userHasPermission($permission)) {
            Logger::error('[Middleware::can] Acceso denegado', ['permission' => $permission, 'roles' => $userRoles]);
            self::handleUnauthorized();
        }
    }

    /**
     * Solo permite acceso a usuarios NO autenticados.
     *
     * Si el usuario ya está autenticado, redirige a su página principal según rol.
     */
    public static function guest(string $redirectTo = '/'): void
    {
        try {
            // Verificar si hay usuario logueado (usar helpers de Session)
            Session::start();
            $userId = Session::userId();

            if ($userId && $userId > 0) {
                $roles = Session::get('user_roles', []);
                $primaryRole = $roles[0] ?? self::ROLE_USER;
                $home = self::ROLE_HOMES[$primaryRole] ?? $redirectTo;
                self::redirect(self::sanitizePath($home));
            }
        } catch (Throwable $e) {
            // Log del error pero no fallar
            Logger::error('[Middleware::guest] Error', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Verifica CSRF en peticiones POST/PUT/DELETE.
     */
    public static function csrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (\in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            Csrf::verify();
        }
    }

    /**
     * Requiere que la petición sea AJAX/JSON.
     */
    public static function api(): void
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        $isApi = \str_contains($accept, 'application/json')
            || \str_contains($contentType, 'application/json')
            || \strtolower($xRequested) === 'xmlhttprequest';

        if (!$isApi) {
            self::abortJson(400, 'Se requiere petición API (Accept: application/json)');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers de autorización para controllers/views
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica si usuario actual tiene permiso (sin abortar).
     *
     * @param string $permission Código del permiso a comprobar
     * @return boolean True si el usuario tiene el permiso
     */
    public static function hasPermission(string $permission): bool
    {
        Session::start();

        return self::userHasPermission($permission);
    }

    /**
     * Verifica si usuario tiene alguno de los roles.
     *
     * @param string ...$roles Lista de roles a comprobar
     * @return boolean True si el usuario posee alguno de los roles
     */
    public static function hasRole(string ...$roles): bool
    {
        Session::start();
        $userRoles = (array) Session::get('user_roles', []);

        return \count(\array_intersect($userRoles, $roles)) > 0;
    }

    /**
     * Verifica si usuario puede acceder a backoffice.
     */
    public static function canAccessBackoffice(): bool
    {
        return self::hasRole(...self::BACKOFFICE_ROLES);
    }

    /**
     * Obtiene roles del usuario actual.
     *
     * @return array<string>
     */
    public static function getUserRoles(): array
    {
        Session::start();
        $roles = Session::get('user_roles', []);
        return is_array($roles) ? $roles : [];
    }

    /**
     * Obtiene primer rol (rol primario).
     */
    public static function getPrimaryRole(): ?string
    {
        $roles = self::getUserRoles();

        return $roles[0] ?? null;
    }

    /**
     * Obtiene ID del usuario autenticado.
     */
    public static function userId(): ?int
    {
        Session::start();
        $id = Session::get('user_id');

        return $id ? (int) $id : null;
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene usuario de DB para verificar estado.
     *
     * @return array{is_active: bool}|null
     */
    private static function fetchUserFromDb(int $userId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        return [
            'is_active' => (bool) $user['is_active'],
        ];
    }

    /**
     * Carga roles y permisos del usuario en sesión (con caché).
     *
     * Usa la caché de sesión si está disponible; en caso contrario realiza
     * las consultas necesarias y almacena el resultado en sesión.
     */
    private static function loadUserRolesInSession(int $userId): void
    {
        // Guard 1: Si roles ya están en sesión, no recargar
        if (Session::has('user_roles') && Session::has('user_permissions')) {
            return;
        }

        $db = Database::getConnection();

        // Cargar roles
        if (!Session::has('user_roles')) {
            $stmt = $db->prepare(
                'SELECT DISTINCT r.code FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = :user_id
                 ORDER BY r.code'
            );
            $stmt->execute(['user_id' => $userId]);
            $roles = \array_column($stmt->fetchAll(), 'code');
            Session::set('user_roles', $roles);
        }

        // Cargar permisos (O usar caché si ya está disponible)
        if (!Session::has('user_permissions')) {
            $stmt = $db->prepare(
                'SELECT DISTINCT p.code FROM user_roles ur
                 JOIN role_permissions rp ON ur.role_id = rp.role_id
                 JOIN permissions p ON rp.permission_id = p.id
                 WHERE ur.user_id = :user_id'
            );
            $stmt->execute(['user_id' => $userId]);

            $permissions = [];
            foreach ($stmt->fetchAll() as $row) {
                $permissions[$row['code']] = true; // Formato de caché: code => true
            }

            Session::set('user_permissions', $permissions);
        }
    }

    /**
     * Verifica si usuario actual tiene permiso.
     *
     * Primero consulta la caché de permisos en sesión; si está vacía realiza
     * una comprobación segura contra la base de datos.
     *
     * @param string $permission Código del permiso a comprobar
     * @return boolean True si el usuario tiene el permiso
     */
    private static function userHasPermission(string $permission): bool
    {
        $permissionsCache = Session::getPermissionsCache();

        // Si está en caché, usar resultado cacheado
        if (isset($permissionsCache[$permission])) {
            return true;
        }

        // Si caché está poblado pero permiso NO está, seguro no tiene
        if (!empty($permissionsCache)) {
            return false;
        }

        // Si la clave user_permissions existe en sesión (aunque vacía), el usuario
        // no tiene permisos extra — no hacer fallback a BD.
        if (Session::has('user_permissions')) {
            return false;
        }

        // Caché no inicializada: fallback a BD (seguro)
        $userId = Session::userId();
        if (!$userId) {
            return false;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM permissions p
             INNER JOIN role_permissions rp ON p.id = rp.permission_id
             INNER JOIN roles r ON rp.role_id = r.id
             INNER JOIN user_roles ur ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND p.code = :code LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'code' => $permission]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Fuerza logout.
     */
    private static function forceLogout(string $reason): never
    {
        Session::destroy();
        if (PHP_SAPI === 'cli') {
            throw new \App\Exceptions\MiddlewareException('Forzado logout: ' . $reason, 'auth', $reason);
        }
        self::redirect('/login?error=' . \urlencode($reason));
    }

    /**
     * Maneja acceso no autorizado.
     */
    private static function handleUnauthorized(): never
    {
        // En entorno CLI (tests) lanzar excepción para que los tests puedan capturarla
        if (PHP_SAPI === 'cli') {
            throw new \App\Exceptions\MiddlewareException('Acceso denegado por middleware', 'authorization', 'access_denied');
        }

        $roles = Session::get('user_roles', []);
        $primaryRole = $roles[0] ?? self::ROLE_USER;

        if (isset(self::ROLE_HOMES[$primaryRole])) {
            Flash::warning('No tienes acceso a esa sección.');
            self::redirect(self::ROLE_HOMES[$primaryRole]);
        }

        self::abort(403);
    }

    /**
     * Sanitiza path para prevenir open redirect.
     */
    private static function sanitizePath(string $path): string
    {
        $path = \trim($path);

        if ($path === '' || $path[0] !== '/') {
            return '/';
        }

        if (\preg_match('/[\r\n]/', $path)) {
            return '/';
        }

        if (\str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }

    /**
     * Redirección segura.
     */
    private static function redirect(string $path): never
    {
        if (!\headers_sent()) {
            \header('Location: ' . $path);
        } else {
            Logger::error('[Middleware::redirect] headers already sent', ['path' => $path]);
        }
        exit;
    }

    /**
     * Aborta con código HTTP.
     */
    private static function abort(int $code): never
    {
        if (!\headers_sent()) {
            @\http_response_code($code);
        } else {
            Logger::error('[Middleware::abort] headers already sent; skipping http_response_code', ['code' => $code]);
        }
        $errorPage = match ($code) {
            403 => '/error/403',
            404 => '/error/404',
            419 => '/error/419',
            default => '/error/500',
        };
        if (!\headers_sent()) {
            \header("Location: $errorPage");
        } else {
            Logger::error('[Middleware::abort] headers already sent; cannot redirect', ['error_page' => $errorPage]);
        }
        exit;
    }

    /**
     * Aborta con respuesta JSON.
     */
    private static function abortJson(int $code, string $message): never
    {
        if (!\headers_sent()) {
            @\http_response_code($code);
            \header('Content-Type: application/json; charset=UTF-8');
        } else {
            Logger::error('[Middleware::abortJson] headers already sent; skipping header()', ['code' => $code, 'message' => $message]);
        }
        echo \json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Aplica cabeceras de seguridad HTTP (CSP, HSTS y similares).
     *
     * Genera una política CSP diferente según el flag `$strict`:
     * - modo estricto (producción): restringe `script-src` a 'self'.
     * - modo desarrollo: permite fuentes externas y valores de depuración.
     *
     * @param boolean $strict Modo estricto (producción)
     */
    public static function securityHeaders(bool $strict = false): void
    {
        // Content Security Policy
        if ($strict) {
            // Producción: CSP estricto
            // Producción: CSP estricto — solo recursos locales para scripts
            $csp = \implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "img-src 'self' data: https:",
                "font-src 'self' https://fonts.gstatic.com",
                "connect-src 'self' https://api.openai.com https://api.open-meteo.com https://date.nager.at",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
        } else {
            // Desarrollo: CSP relajado para facilitar debugging
            $csp = \implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
                "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
                "img-src 'self' data: https: blob:",
                "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
                "connect-src 'self' https://api.openai.com https://api.open-meteo.com https://date.nager.at https://cdn.jsdelivr.net",
                "frame-ancestors 'none'",
            ]);
        }

        if (!\headers_sent()) {
            \header("Content-Security-Policy: $csp");

            // X-Content-Type-Options: Previene MIME sniffing
            \header('X-Content-Type-Options: nosniff');

            // X-Frame-Options: Previene clickjacking
            \header('X-Frame-Options: DENY');

            // X-XSS-Protection: Protección XSS en navegadores antiguos
            \header('X-XSS-Protection: 1; mode=block');

            // Referrer-Policy: Controla información de referrer
            \header('Referrer-Policy: strict-origin-when-cross-origin');

            // Permissions-Policy: Deshabilita APIs no necesarias
            \header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

            // HSTS: Forzar HTTPS en producción (solo si está habilitado)
            if ($strict && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                \header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
            }

            // X-Powered-By: Ocultar información del servidor
            \header_remove('X-Powered-By');
        } else {
            Logger::error('[Middleware::securityHeaders] headers already sent; skipping security headers', ['strict' => $strict]);
        }
    }

    /**
     * Middleware para aplicar headers de seguridad en todas las rutas
     * Uso: $router->get('/path', 'Controller@method', ['security']);
     */
    public static function security(): void
    {
        $isProduction = Env::get('APP_ENV', 'development') === 'production';
        self::securityHeaders($isProduction);
    }
}
