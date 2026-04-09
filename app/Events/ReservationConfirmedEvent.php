<?php

declare(strict_types=1);

namespace App\Events;

use DateTimeImmutable;

/**
 * Evento disparado cuando se confirma una reserva.
 */
final readonly class ReservationConfirmedEvent
{
    public function __construct(
        public int $reservationId,
        public int $userId,
        public string $userEmail,
        public string $date,
        public string $time,
        public int $guests,
        public DateTimeImmutable $confirmedAt
    ) {
    }
}
