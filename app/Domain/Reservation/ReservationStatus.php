<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

enum ReservationStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';
    case CheckedIn = 'checked_in';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Active    => 'Activa',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::NoShow    => 'No Show',
            self::CheckedIn => 'En local',
        };
    }

    public static function labelFor(string $status): string
    {
        $case = self::tryFrom($status);

        return $case !== null ? $case->label() : \ucfirst($status);
    }
}
