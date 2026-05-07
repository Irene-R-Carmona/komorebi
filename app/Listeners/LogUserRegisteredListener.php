<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Logger;
use App\Events\UserRegisteredEvent;
use Throwable;

final class LogUserRegisteredListener
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        try {
            Logger::info('[User] Nuevo usuario registrado', [
                'user_id' => $event->userId,
                'email' => $event->email,
                'name' => $event->name,
            ]);
        } catch (Throwable $e) {
            Logger::error('[LogUserRegisteredListener] Error: ' . $e->getMessage(), [
                'user_id' => $event->userId,
            ]);
        }
    }
}
