<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Database;
use App\Core\ServiceProvider;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use App\Repositories\NewsletterSubscriptionRepository;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\NewsletterService;
use Override;

final class NewsletterServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(NewsletterSubscriptionRepository::class, fn() => new NewsletterSubscriptionRepository(
            Database::getConnection()
        ));

        Container::singleton(NewsletterSubscriptionRepositoryInterface::class, fn() => Container::make(
            NewsletterSubscriptionRepository::class
        ));

        Container::singleton(NewsletterService::class, fn() => new NewsletterService(
            Container::make(NewsletterSubscriptionRepositoryInterface::class)
        ));

        Container::singleton(NewsletterServiceInterface::class, fn() => Container::make(NewsletterService::class));
    }

    #[Override]
    public function boot(): void {}
}
