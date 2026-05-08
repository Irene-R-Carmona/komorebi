<?php

declare(strict_types=1);

namespace App\Domain\Waitlist;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Notified = 'notified';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Notified => 'Notificado',
            self::Confirmed => 'Confirmado',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
        };
    }

    public static function labelFor(string $status): string
    {
        $case = self::tryFrom($status);

        return $case !== null ? $case->label() : \ucfirst($status);
    }
}
