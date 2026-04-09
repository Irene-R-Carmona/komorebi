<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        if ($value === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new ValidationException(
                'Slug inválido',
                ['slug' => 'El slug solo puede contener letras minúsculas, números y guiones']
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
