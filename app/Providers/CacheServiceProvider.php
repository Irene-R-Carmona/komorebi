<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Services\CacheService;
use Override;
use Psr\Cache\CacheItemPoolInterface;

final class CacheServiceProvider extends ServiceProvider
{
    #[Override]
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

    #[Override]
    public function boot(): void {}
}
