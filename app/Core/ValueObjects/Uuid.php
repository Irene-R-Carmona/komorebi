<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Uuid
{
    private string $value;

    public function __construct(string $value)
    {
        if (!\preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new ValidationException(
                'UUID inválido',
                ['uuid' => 'El valor no es un UUID v4 válido']
            );
        }

        $this->value = \strtolower($value);
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
