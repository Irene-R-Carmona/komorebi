<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

enum LoyaltyRewardStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Used => 'Usado',
            self::Expired => 'Expirado',
        };
    }

    public static function labelFor(string $status): string
    {
        $case = self::tryFrom($status);

        return $case !== null ? $case->label() : \ucfirst($status);
    }
}
