<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

/**
 * Gestión centralizada de sesiones.
 *
 * Características:
 * - Inicio seguro con cookies HttpOnly/Secure
 * - Métodos específicos para usuario autenticado
 * - Helpers genéricos get/set/has/remove
 */
final class Session
{
    // ─────────────────────────────────────────────────────────────
    // Inicio y destrucción
    // ─────────────────────────────────────────────────────────────

    /**
     * Inicia la sesión si no está activa.
     * Configura cookies seguras automáticamente.
     */
    public static function start(): void
    {
        // Asegurar que la superglobal $_SESSION existe para evitar notices
        // que en el contexto de tests se convierten en excepciones.
        if (!\array_key_exists('_SESSION', $GLOBALS) || !\is_array($GLOBALS['_SESSION'])) {
            $GLOBALS['_SESSION'] = [];
        }

        if (\session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (\headers_sent()) {
            // La superglobal ya fue inicializada al inicio de start()
        } else {
            // Configurar Redis como session handler si SESSION_DRIVER=redis
            if (Env::get('SESSION_DRIVER', 'file') === 'redis') {
                $redisHost = Env::get('REDIS_HOST', '127.0.0.1');
                $redisPort = Env::int('REDIS_PORT', 6379);
                $redisPass = Env::get('REDIS_PASSWORD', '');
                $sessionTtl = Env::int('SESSION_LIFETIME', 7200);
                $savePath = "tcp://{$redisHost}:{$redisPort}?database=1&lifetime={$sessionTtl}";
                if ($redisPass !== '') {
                    $savePath .= '&auth=' . \urlencode($redisPass);
                }
                \ini_set('session.save_handler', 'redis');
                \ini_set('session.save_path', $savePath);
            }
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

            \session_set_cookie_params([
                'lifetime' => 0,                              // Hasta cerrar navegador
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,                         // Detectado dinámicamente (soporta X-Forwarded-Proto)
                'httponly' => true,                           // No accesible desde JS
                'samesite' => 'Lax',                          // Protección CSRF adicional
            ]);

            \session_start();
        }
    }

    /**
     * Inicia la sesión en modo solo-lectura (read_and_close).
     *
     * Libera el lock de sesión inmediatamente tras leerla.
     * Usar en rutas API donde múltiples requests concurrentes leen sesión
     * sin necesidad de escribir (evita race conditions / deadlocks).
     * No permite escribir en sesión después de llamar a este método.
     */
    public static function startReadOnly(): void
    {
        if (!\array_key_exists('_SESSION', $GLOBALS) || !\is_array($GLOBALS['_SESSION'])) {
            $GLOBALS['_SESSION'] = [];
        }

        if (\session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (\headers_sent()) {
            return;
        }

        // Configurar Redis si aplica (igual que start())
        if (Env::get('SESSION_DRIVER', 'file') === 'redis') {
            $redisHost = Env::get('REDIS_HOST', '127.0.0.1');
            $redisPort = Env::int('REDIS_PORT', 6379);
            $redisPass = Env::get('REDIS_PASSWORD', '');
            $sessionTtl = Env::int('SESSION_LIFETIME', 7200);
            $savePath = "tcp://{$redisHost}:{$redisPort}?database=1&lifetime={$sessionTtl}";
            if ($redisPass !== '') {
                $savePath .= '&auth=' . \urlencode($redisPass);
            }
            \ini_set('session.save_handler', 'redis');
            \ini_set('session.save_path', $savePath);
        }

        // read_and_close: lee la sesión y libera el lock inmediatamente.
        // Permite requests concurrentes sin bloquear la sesión.
        \session_start(['read_and_close' => true]);
    }

    /**
     * SIEMPRE llamar después de login (previene session fixation).
     */
    public static function regenerate(): void
    {
        self::start();
        \session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (\ini_get('session.use_cookies')) {
            $params = \session_get_cookie_params();
            $samesite = (string) $params['samesite'];
            if (!\in_array($samesite, ['Lax', 'lax', 'None', 'none', 'Strict', 'strict'], true)) {
                $samesite = 'Lax';
            }

            $cookieOptions = [
                'expires' => \time() - 42000,
                'path' => (string) $params['path'],
                'domain' => (string) $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $samesite,
            ];

            \setcookie((string) \session_name(), '', $cookieOptions);
        }

        \session_destroy();
    }

    // ─────────────────────────────────────────────────────────────
    // Autenticación - Métodos específicos de usuario
    // ─────────────────────────────────────────────────────────────

    public static function isAuthenticated(): bool
    {
        self::start();

        return !empty($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        self::start();

        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): string
    {
        self::start();

        // Preferir valor 'user_role' si existe, sino derivar del primer elemento de 'user_roles'
        $role = $_SESSION['user_role'] ?? null;
        if ($role) {
            return (string) $role;
        }

        $roles = $_SESSION['user_roles'] ?? [];
        if (!empty($roles) && \is_array($roles)) {
            return (string) ($roles[0] ?? 'guest');
        }

        return 'guest';
    }

    public static function userName(): string
    {
        self::start();

        return (string) ($_SESSION['user_name'] ?? '');
    }

    public static function userEmail(): string
    {
        self::start();

        return (string) ($_SESSION['user_email'] ?? '');
    }

    public static function userCafeId(): ?int
    {
        self::start();
        $cafeId = $_SESSION['user_cafe_id'] ?? null;

        return $cafeId !== null ? (int) $cafeId : null;
    }

    /**
     * Obtiene todos los datos del usuario actual.
     *
     * @return array{id: int|null, name: string, email: string, role: string, cafe_id: int|null}
     */
    public static function user(): array
    {
        self::start();

        return [
            'id' => self::userId(),
            'name' => self::userName(),
            'email' => self::userEmail(),
            'role' => self::role(),
            'cafe_id' => self::userCafeId(),
        ];
    }

    /**
     * Establece la sesión del usuario tras login/registro.
     * Regenera el ID de sesión automáticamente (seguridad).
     *
     * @param array{id: int, name?: string, email?: string, role?: string|array<string>, cafe_id?: int|null} $user
     */
    public static function setUser(array $user): void
    {
        self::regenerate(); // Previene session fixation

        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['user_name'] = (string) ($user['name'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');

        // Manejar roles (RBAC puro) - puede ser array de códigos
        if (isset($user['role']) && \is_array($user['role'])) {
            $mapped = \array_map(static function ($r) {
                $aliases = [
                    'techou' => 'manager',
                    'encargado' => 'supervisor',
                ];
                $r = (string) $r;

                return $aliases[$r] ?? $r;
            }, $user['role']);

            // Eliminar duplicados y reindexar
            $_SESSION['user_roles'] = \array_values(\array_unique($mapped));
        } else {
            $_SESSION['user_roles'] = [];
        }

        // Mantener compatibilidad con código legacy que usa 'user_role' (string)
        $primaryRole = 'user';
        if (!empty($_SESSION['user_roles'])) {
            $primaryRole = (string) ($_SESSION['user_roles'][0] ?? 'user');
        } elseif (!empty($user['role'])) {
            $primaryRole = (string) $user['role'];
        }
        $_SESSION['user_role'] = $primaryRole;

        $_SESSION['user_cafe_id'] = $user['cafe_id'] ?? null;

        // Nota: Evitar cerrar y reabrir la sesión aquí (puede causar race conditions)
        Logger::info('[Session::setUser] Datos de sesión establecidos para user_id: ' . (string) $_SESSION['user_id']);
    }

    /**
     * Carga y cachea los permisos del usuario en sesión.
     *
     * IMPORTANTE: Llamar después de setUser() en login.
     *
     * @param integer $userId ID del usuario a cargar permisos
     */
    public static function cacheUserPermissions(int $userId): void
    {
        self::start();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'SELECT DISTINCT p.code FROM permissions p
                 INNER JOIN role_permissions rp ON p.id = rp.permission_id
                 INNER JOIN roles r ON rp.role_id = r.id
                 INNER JOIN user_roles ur ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id'
            );
            $stmt->execute(['user_id' => $userId]);

            $permissions = [];
            foreach ($stmt->fetchAll() as $row) {
                $permissions[$row['code']] = true;
            }

            $_SESSION['user_permissions'] = $permissions;
            $_SESSION['permissions_cached_at'] = \time();
        } catch (Exception $e) {
            Logger::error('[Session] Error cacheando permisos', ['exception' => $e->getMessage()]);
            $_SESSION['user_permissions'] = [];
        }
    }

    public static function invalidatePermissionsCache(): void
    {
        self::start();
        unset($_SESSION['user_permissions'], $_SESSION['permissions_cached_at']);
    }

    /**
     * Obtiene el caché de permisos del usuario.
     *
     * @return array<string, bool> Array donde key=permiso, value=true si tiene permiso
     */
    public static function getPermissionsCache(): array
    {
        self::start();

        return $_SESSION['user_permissions'] ?? [];
    }

    /**
     * @param string $permission Código del permiso (ej: 'cafe.kitchen.view')
     * @return bool|null true si tiene, false si no tiene, null si no está en caché
     */
    public static function hasPermissionCached(string $permission): ?bool
    {
        self::start();

        $cache = $_SESSION['user_permissions'] ?? null;

        if ($cache === null) {
            return null; // No está cacheado
        }

        return isset($cache[$permission]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers genéricos (para cualquier dato en sesión)
    // ─────────────────────────────────────────────────────────────

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Obtiene y elimina un valor (útil para datos one-time).
     *
     * @param string $key     Clave a obtener y eliminar
     * @param mixed  $default Valor por defecto si no existe
     * @return mixed Valor obtenido o `$default`
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::remove($key);

        return $value;
    }

    /**
     * Obtiene todos los datos de sesión (debug).
     *
     * @psalm-return array<string, mixed>
     */
    public static function all(): array
    {
        self::start();

        return $_SESSION;
    }
}
