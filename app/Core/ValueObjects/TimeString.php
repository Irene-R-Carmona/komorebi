<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class TimeString
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new ValidationException(
                'Hora inválida',
                ['time' => 'El formato de hora debe ser HH:MM (00:00–23:59)']
            );
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
