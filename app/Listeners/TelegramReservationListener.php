<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Queue;
use App\Events\ReservationConfirmedEvent;
use App\Jobs\SendTelegramNotificationJob;

final class TelegramReservationListener
{
    public function __invoke(ReservationConfirmedEvent $event): void
    {
        $message = "Reserva #: {$event->reservationId}\n"
            . "Cliente: {$event->userEmail}\n"
            . "Fecha: {$event->date} a las {$event->time}\n"
            . "Comensales: {$event->guests}";

        Queue::push(SendTelegramNotificationJob::class, [
            'icon'    => '📅',
            'title'   => 'Reserva confirmada',
            'message' => $message,
        ]);
    }
}
