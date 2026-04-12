<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Redis;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Throwable;

/**
 * CacheService con soporte PSR-6.
 *
 * Implementación moderna del cache pool siguiendo estándares PSR-6.
 * Usa Redis con fallback a filesystem.
 */
final class CacheService implements CacheItemPoolInterface
{
    private CacheItemPoolInterface $pool;

    public function __construct()
    {
        $this->pool = $this->createPool();
    }

    /**
     * Crea el pool de caché apropiado.
     */
    private function createPool(): CacheItemPoolInterface
    {
        try {
            if (\extension_loaded('redis')) {
                $redis = new \Redis();
                $host = Env::get('REDIS_HOST', 'cache');
                $port = (int) Env::get('REDIS_PORT', '6379');
                $password = Env::get('REDIS_PASSWORD');
                $timeout = (int) Env::get('REDIS_TIMEOUT', '3');

                if ($redis->connect($host, $port, $timeout)) {
                    // Autenticar si hay contraseña configurada
                    if ($password && $password !== '') {
                        $redis->auth($password);
                    }
                    return new RedisAdapter($redis, 'komorebi', 3600);
                }
            }
        } catch (Throwable $e) {
            Logger::error('[CacheService] Redis no disponible: ' . $e->getMessage(), ['exception' => $e->getMessage()]);
        }

        return new FilesystemAdapter('komorebi', 3600, __DIR__ . '/../../storage/cache');
    }

    #[\Override]
    public function getItem(string $key): CacheItemInterface
    {
        return $this->pool->getItem($key);
    }

    #[\Override]
    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    #[\Override]
    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    #[\Override]
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    #[\Override]
    public function deleteItem(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    #[\Override]
    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    #[\Override]
    public function save(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    #[\Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    #[\Override]
    public function commit(): bool
    {
        return $this->pool->commit();
    }

    /**
     * Helper: obtiene o calcula un valor cacheado.
     *
     * @template T
     * @param string        $key
     * @param callable(): T $callback
     * @param integer       $ttl
     * @return T
     */
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $item = $this->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $callback();
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->save($item);

        return $value;
    }
}
