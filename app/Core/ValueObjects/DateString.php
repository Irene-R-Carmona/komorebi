<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class DateString
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ValidationException(
                'Fecha inválida',
                ['date' => 'El formato de fecha debe ser YYYY-MM-DD']
            );
        }

        [$year, $month, $day] = explode('-', $value);
        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            throw new ValidationException(
                'Fecha inválida',
                ['date' => 'La fecha no existe en el calendario']
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
