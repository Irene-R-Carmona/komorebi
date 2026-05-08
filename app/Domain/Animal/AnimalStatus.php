<?php

declare(strict_types=1);

namespace App\Domain\Animal;

enum AnimalStatus: string
{
    case Active = 'active';
    case Resting = 'resting';
    case Sick = 'sick';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Resting => 'Descansando',
            self::Sick => 'Enfermo',
            self::Retired => 'Retirado',
        };
    }

    public static function labelFor(string $status): string
    {
        $case = self::tryFrom($status);

        return $case !== null ? $case->label() : \ucfirst($status);
    }
}
