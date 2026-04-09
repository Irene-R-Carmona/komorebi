<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Services\CacheService;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache Service Provider.
 *
 * Registra CacheService (PSR-6) en el Container como singleton.
 */
final class CacheServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Container::singleton(CacheItemPoolInterface::class, static function (): CacheService {
            return new CacheService();
        });

        Container::singleton(CacheService::class, static function (): CacheService {
            return Container::make(CacheItemPoolInterface::class);
        });

        // Alias corto
        Container::alias('cache', CacheService::class);
    }

    #[\Override]
    public function boot(): void
    {
        // No hay nada que hacer aquí
    }
}
