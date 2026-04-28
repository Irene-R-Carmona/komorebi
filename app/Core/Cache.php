<?php

declare(strict_types=1);

namespace App\Core;

use Redis;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Throwable;

/**
 * Servicio de Cache con Redis
 *
 * Proporciona operaciones de cache usando Redis como backend.
 * Con fallback automático a ArrayAdapter (symfony/cache) si Redis no está disponible.
 * Internamente delega en PSR-16 (Psr16Cache).
 */
final class Cache
{
    private static ?Redis $redis = null;
    private static ?Psr16Cache $pool = null;
    private static ?TagAwareAdapter $tagAdapter = null;
    private static bool $initialized = false;

    /** Número de accesos que encontraron el valor en cache en este ciclo de request. */
    private static int $hits = 0;

    /** Número de accesos que NO encontraron el valor en cache en este ciclo de request. */
    private static int $misses = 0;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $host = Env::get('REDIS_HOST', 'localhost');
        $port = (int) Env::get('REDIS_PORT', '6379');
        $password = Env::get('REDIS_PASSWORD');

        if (\class_exists(Redis::class)) {
            try {
                $r = new Redis();
                if ($r->connect($host, $port, 2.5)) {
                    if ($password && !$r->auth($password)) {
                        Logger::warning('[Cache] Redis auth failed');
                    }
                    self::$redis = $r;
                    self::$tagAdapter = new TagAwareAdapter(new RedisAdapter($r));
                    self::$pool = new Psr16Cache(self::$tagAdapter);

                    return;
                }
            } catch (Throwable $e) {
                Logger::warning('[Cache] Redis unavailable, fallback to ArrayAdapter', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: symfony/cache ArrayAdapter (reemplaza la clase anónima de ~200 líneas)
        self::$tagAdapter = new TagAwareAdapter(new ArrayAdapter(defaultLifetime: 0, storeSerialized: true));
        self::$pool = new Psr16Cache(self::$tagAdapter);
    }

    /**
     * Obtiene la instancia de Redis (o null si no disponible)
     */
    public static function getRedis(): ?Redis
    {
        self::init();

        return self::$redis;
    }

    /**
     * Obtiene un valor del cache
     *
     * @param string $key     Clave del cache
     * @param mixed  $default Valor por defecto si no existe
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();
        if (self::$pool === null) {
            self::$misses++;

            return $default;
        }
        $value = self::$pool->get(self::sanitizeKey($key), $default);
        if ($value !== $default) {
            self::$hits++;
        } else {
            self::$misses++;
        }

        return $value;
    }

    /**
     * Guarda un valor en el cache
     *
     * @param string $key   Clave del cache
     * @param mixed  $value Valor a almacenar
     * @param int    $ttl   Tiempo de vida en segundos (default: 3600)
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        self::init();

        return self::$pool?->set(self::sanitizeKey($key), $value, $ttl) ?? false;
    }

    /**
     * Elimina un valor del cache
     *
     * @param string $key Clave del cache
     */
    public static function delete(string $key): bool
    {
        self::init();

        return self::$pool?->delete(self::sanitizeKey($key)) ?? false;
    }

    /**
     * Verifica si una key existe en el cache
     *
     * @param string $key Clave del cache
     */
    public static function has(string $key): bool
    {
        self::init();

        return self::$pool?->has(self::sanitizeKey($key)) ?? false;
    }

    /**
     * Limpia todo el cache
     */
    public static function flush(): bool
    {
        self::init();

        return self::$pool?->clear() ?? false;
    }

    /**
     * Elimina múltiples keys que coincidan con un patrón (requiere Redis con SCAN)
     *
     * @param string $pattern Patrón de búsqueda (ej: "products:*")
     * @return int Número de keys eliminadas, o 0 si Redis no está disponible
     */
    public static function deletePattern(string $pattern): int
    {
        self::init();
        if (self::$redis === null) {
            Logger::warning('[Cache] deletePattern not supported without Redis', ['pattern' => $pattern]);

            return 0;
        }

        try {
            $cursor = null;
            $deleted = 0;
            $sanitizedPattern = self::sanitizePattern($pattern);
            do {
                $keys = self::$redis->scan($cursor, $sanitizedPattern, 100);
                if ($keys !== false && $keys !== []) {
                    $deleted += self::$redis->del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== null);

            return $deleted;
        } catch (Throwable $e) {
            Logger::warning('[Cache] deletePattern failed', ['exception' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Obtiene o genera un valor de cache (patrón remember)
     *
     * @param string   $key      Clave del cache
     * @param callable $callback Función que genera el valor si no está en cache
     * @param int      $ttl      Tiempo de vida en segundos
     */
    public static function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        // get() already increments hit/miss internally
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Incrementa un contador de forma atómica usando Redis INCRBY.
     * Requiere Redis; retorna false si Redis no está disponible o falla.
     *
     * @param string $key Clave del contador (raw, no sanitizada)
     * @param int    $by  Cantidad a incrementar (default: 1)
     * @return int|false Nuevo valor del contador o false si falla
     */
    public static function increment(string $key, int $by = 1): int|false
    {
        self::init();
        if (self::$redis === null) {
            return false;
        }

        try {
            return self::$redis->incrBy($key, $by);
        } catch (Throwable $e) {
            Logger::warning('[Cache] increment failed', ['key' => $key, 'exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Decrementa un contador de forma atómica usando Redis DECRBY.
     * Requiere Redis; retorna false si Redis no está disponible o falla.
     *
     * @param string $key Clave del contador (raw, no sanitizada)
     * @param int    $by  Cantidad a decrementar (default: 1)
     * @return int|false Nuevo valor del contador o false si falla
     */
    public static function decrement(string $key, int $by = 1): int|false
    {
        self::init();
        if (self::$redis === null) {
            return false;
        }

        try {
            return self::$redis->decrBy($key, $by);
        } catch (Throwable $e) {
            Logger::warning('[Cache] decrement failed', ['key' => $key, 'exception' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Sanitiza una clave de cache para cumplir con PSR-16 (prohíbe {}()/\@: entre otros)
     * Las claves del proyecto usan ':' como separador (ej: products:1), por lo que se hace
     * rawurlencode() para que sean transparentes al llamador.
     */
    private static function sanitizeKey(string $key): string
    {
        return \rawurlencode($key);
    }

    /**
     * Sanitiza un patrón glob preservando los wildcards (* y ?)
     * Usado en deletePattern para que el SCAN de Redis coincida con las claves sanitizadas.
     */
    private static function sanitizePattern(string $pattern): string
    {
        return \str_replace(
            [':', '{', '}', '(', ')', '/', '\\', '@'],
            ['%3A', '%7B', '%7D', '%28', '%29', '%2F', '%5C', '%40'],
            $pattern
        );
    }

    /**
     * Retorna las estadísticas de hit/miss acumuladas desde el último resetStats().
     *
     * @return array{hits: int, misses: int}
     */
    public static function getStats(): array
    {
        return ['hits' => self::$hits, 'misses' => self::$misses];
    }

    /**
     * Reinicia los contadores de hit/miss.
     * Llamado por RequestLogMiddleware en el bloque finally de cada request.
     */
    public static function resetStats(): void
    {
        self::$hits = 0;
        self::$misses = 0;
    }

    /**
     * Reinicia el estado interno (útil para tests y reconfiguración)
     */
    public static function reset(): void
    {
        self::$redis = null;
        self::$pool = null;
        self::$tagAdapter = null;
        self::$initialized = false;
        self::$hits = 0;
        self::$misses = 0;
    }

    // ── TagAware operations ───────────────────────────────────────────────────

    /**
     * Invalida todas las entradas de cache asociadas a los tags dados.
     *
     * @param string[] $tags
     */
    public static function invalidateTags(array $tags): bool
    {
        self::init();
        if (self::$tagAdapter === null) {
            return false;
        }
        try {
            return self::$tagAdapter->invalidateTags($tags);
        } catch (Throwable $e) {
            Logger::warning('[Cache] invalidateTags failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Almacena un valor con tags de invalidación asociados.
     *
     * @param string[] $tags
     */
    public static function setWithTags(string $key, mixed $value, array $tags, int $ttl = 3600): bool
    {
        self::init();
        if (self::$tagAdapter === null) {
            return self::set($key, $value, $ttl);
        }
        try {
            $item = self::$tagAdapter->getItem(self::sanitizeKey($key));
            $item->set($value);
            $item->expiresAfter($ttl);
            $item->tag($tags);
            return self::$tagAdapter->save($item);
        } catch (Throwable $e) {
            Logger::warning('[Cache] setWithTags failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Retorna el valor cacheado; si no existe, ejecuta $fn, lo guarda y lo retorna.
     * Si se pasan tags, usa TagAwareAdapter para permitir invalidación por grupo.
     *
     * @param string[] $tags
     */
    public static function computeIfAbsent(string $key, callable $fn, int $ttl = 3600, array $tags = []): mixed
    {
        self::init();
        $sKey = self::sanitizeKey($key);
        if (self::$tagAdapter !== null) {
            try {
                $item = self::$tagAdapter->getItem($sKey);
                if ($item->isHit()) {
                    self::$hits++;
                    return $item->get();
                }
                self::$misses++;
                $value = $fn();
                $item->set($value);
                $item->expiresAfter($ttl);
                if ($tags !== []) {
                    $item->tag($tags);
                }
                self::$tagAdapter->save($item);
                return $value;
            } catch (Throwable $e) {
                Logger::warning('[Cache] computeIfAbsent failed, falling back to remember()', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
        return self::remember($key, $fn, $ttl);
    }
}
