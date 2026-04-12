<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Services\NewsletterService;
use App\Services\Contracts\NewsletterServiceInterface;
use PDO;

/**
 * Newsletter Service Provider.
 *
 * Registra NewsletterService en el Container.
 */
final class NewsletterServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        Container::singleton(NewsletterService::class, function () {
            $pdo = Container::make(PDO::class);
            return new NewsletterService($pdo);
        });

        Container::singleton(NewsletterServiceInterface::class, fn() => Container::make(NewsletterService::class));
    }

    #[\Override]
    public function boot(): void
    {
        // No hay lógica de boot necesaria
    }
}
