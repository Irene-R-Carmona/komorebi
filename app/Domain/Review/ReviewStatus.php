<?php

declare(strict_types=1);

namespace App\Domain\Review;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
        };
    }

    public static function labelFor(string $status): string
    {
        $case = self::tryFrom($status);

        return $case !== null ? $case->label() : \ucfirst($status);
    }
}
