<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Logger;
use App\Events\ReservationConfirmedEvent;
use Throwable;

/**
 * Listener que registra cuando se confirma una reserva.
 */
final class LogReservationConfirmedListener
{
    public function __invoke(ReservationConfirmedEvent $event): void
    {
        try {
            Logger::info('[Reservation] Reserva confirmada', [
                'reservation_id' => $event->reservationId,
                'user_id' => $event->userId,
                'date' => $event->date,
                'time' => $event->time,
                'guests' => $event->guests,
            ]);
        } catch (Throwable $e) {
            Logger::error('[LogReservationConfirmedListener] Error: ' . $e->getMessage(), [
                'reservation_id' => $event->reservationId,
            ]);
        }
    }
}
