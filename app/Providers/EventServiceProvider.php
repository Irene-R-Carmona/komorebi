<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Events\ReservationConfirmedEvent;
use App\Events\ReviewPublishedEvent;
use App\Events\UserRegisteredEvent;
use App\Listeners\LogReservationConfirmedListener;
use App\Listeners\LogReviewPublishedListener;
use App\Listeners\LogUserRegisteredListener;
use App\Listeners\TelegramNewUserListener;
use App\Listeners\TelegramReservationListener;
use App\Listeners\TelegramReviewListener;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EventServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        Container::singleton(EventDispatcherInterface::class, static function (): EventDispatcher {
            return new EventDispatcher();
        });
    }

    #[Override]
    public function boot(): void
    {
        $dispatcher = Container::make(EventDispatcherInterface::class);

        if (!$dispatcher instanceof EventDispatcher) {
            return;
        }

        $dispatcher->addListener(
            UserRegisteredEvent::class,
            new LogUserRegisteredListener()
        );

        $dispatcher->addListener(
            ReservationConfirmedEvent::class,
            new LogReservationConfirmedListener()
        );

        $dispatcher->addListener(
            ReviewPublishedEvent::class,
            new LogReviewPublishedListener()
        );

        // Notificaciones Telegram (asíncronas vía Queue)
        $dispatcher->addListener(
            UserRegisteredEvent::class,
            new TelegramNewUserListener()
        );
        $dispatcher->addListener(
            ReservationConfirmedEvent::class,
            new TelegramReservationListener()
        );
        $dispatcher->addListener(
            ReviewPublishedEvent::class,
            new TelegramReviewListener()
        );
    }
}
