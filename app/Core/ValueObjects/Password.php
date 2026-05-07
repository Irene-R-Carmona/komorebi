<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Password
{
    private string $value;

    public function __construct(string $value)
    {
        $len = \mb_strlen($value);

        if ($len < 8 || $len > 128) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe tener entre 8 y 128 caracteres']
            );
        }

        if (!\preg_match('/[A-Z]/', $value)) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe contener al menos una letra mayúscula']
            );
        }

        if (!\preg_match('/[0-9]/', $value)) {
            throw new ValidationException(
                'Contraseña inválida',
                ['password' => 'La contraseña debe contener al menos un dígito']
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
