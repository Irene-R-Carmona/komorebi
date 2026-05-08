<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Services\Contracts\RateLimitingServiceInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Servicio de Rate Limiting basado en Cache (Redis con TTL nativo).
 *
 * Usa PSR-6 CacheItemPoolInterface para almacenar contadores con TTL,
 * eliminando la necesidad de limpieza manual de registros expirados.
 */
final class RateLimitingService implements RateLimitingServiceInterface
{
    private CacheItemPoolInterface $cache;

    private const CONFIG = [
        'login' => ['max_attempts' => 5, 'lockout_minutes' => 15],
        'password_reset' => ['max_attempts' => 3, 'lockout_minutes' => 30],
        'email_verification' => ['max_attempts' => 5, 'lockout_minutes' => 10],
        'registration' => ['max_attempts' => 3, 'lockout_minutes' => 60],
        'api_public' => ['max_attempts' => 120, 'lockout_minutes' => 1],
    ];

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Obtener configuración de una acción.
     *
     * @return array{max_attempts: int, lockout_minutes: int}
     */
    private function getConfig(string $action): array
    {
        return self::CONFIG[$action] ?? ['max_attempts' => 5, 'lockout_minutes' => 15];
    }

    /**
     * Genera la clave de caché para un intento de rate limit.
     */
    private function cacheKey(string $action, string $identifier): string
    {
        return 'rate_limit_' . \hash('sha256', $action . ':' . $identifier);
    }

    /**
     * Registrar intento de una acción.
     * Devuelve true si el intento fue registrado correctamente.
     */
    #[Override]
    public function recordAttempt(string $action, string $identifier, ?string $ipAddress = null): bool
    {
        $config = $this->getConfig($action);
        $key = $this->cacheKey($action, $identifier);

        $item = $this->cache->getItem($key);

        /** @var array{attempts: int, locked_until: int|null} $data */
        $data = $item->isHit()
            ? $item->get()
            : ['attempts' => 0, 'locked_until' => null];

        $data['attempts'] += 1;

        if ($data['attempts'] >= $config['max_attempts']) {
            $data['locked_until'] = \time() + ($config['lockout_minutes'] * 60);
            $ttl = $config['lockout_minutes'] * 60;
        } else {
            $ttl = Cache::TTL_DAY; // 24h ventana de seguimiento
        }

        $item->set($data);
        $item->expiresAfter($ttl);

        return $this->cache->save($item);
    }

    /**
     * Verificar si un identificador está bloqueado.
     *
     * @return array{blocked: bool, minutes_remaining?: int}
     */
    #[Override]
    public function isBlocked(string $action, string $identifier): array
    {
        $key = $this->cacheKey($action, $identifier);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return ['blocked' => false];
        }

        /** @var array{attempts: int, locked_until: int|null} $data */
        $data = $item->get();

        if (empty($data['locked_until']) || $data['locked_until'] <= \time()) {
            return ['blocked' => false];
        }

        $minutesRemaining = (int) \ceil(($data['locked_until'] - \time()) / 60);

        return ['blocked' => true, 'minutes_remaining' => \max(1, $minutesRemaining)];
    }

    /**
     * Obtener número de intentos recientes.
     */
    public function getRecentAttempts(string $action, string $identifier): int
    {
        $key = $this->cacheKey($action, $identifier);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return 0;
        }

        /** @var array{attempts: int, locked_until: int|null} $data */
        $data = $item->get();

        return $data['attempts'] ?? 0;
    }

    /**
     * Limpiar intentos fallidos (después de login exitoso, etc).
     */
    #[Override]
    public function clearAttempts(string $action, string $identifier): void
    {
        $key = $this->cacheKey($action, $identifier);

        $this->cache->deleteItem($key);
    }
}
