<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class GuestCount
{
    public const int MIN = 1;
    public const int MAX = 20;

    private int $value;

    public function __construct(int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new ValidationException(
                'Número de comensales inválido',
                ['guest_count' => \sprintf('El número de comensales debe estar entre %d y %d', self::MIN, self::MAX)]
            );
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
