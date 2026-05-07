<?php

declare(strict_types=1);

namespace App\Core\ValueObjects;

use App\Exceptions\ValidationException;

final readonly class Role
{
    /** @var list<string> */
    public const array VALID_ROLES = ['admin', 'manager', 'supervisor', 'reception', 'kitchen', 'keeper', 'user'];

    private string $value;

    public function __construct(string $value)
    {
        if (!\in_array($value, self::VALID_ROLES, true)) {
            throw new ValidationException(
                'Rol inválido',
                ['role' => \sprintf('El rol debe ser uno de: %s', \implode(', ', self::VALID_ROLES))]
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
