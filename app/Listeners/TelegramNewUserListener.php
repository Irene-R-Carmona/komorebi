<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Queue;
use App\Events\UserRegisteredEvent;
use App\Jobs\SendTelegramNotificationJob;

final class TelegramNewUserListener
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        Queue::push(SendTelegramNotificationJob::class, [
            'icon'    => '🆕',
            'title'   => 'Nuevo usuario registrado',
            'message' => "Nombre: {$event->name}\nEmail: {$event->email}",
        ]);
    }
}
