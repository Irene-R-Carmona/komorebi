<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Slug
{
    private function __construct(public readonly string $value) {}

    public static function fromString(string $slug): self
    {
        if (!\preg_match('/^[a-z0-9][a-z0-9-]{0,98}[a-z0-9]$/', $slug)) {
            throw new InvalidArgumentException(
                "Slug inválido: \"{$slug}\". Debe tener entre 2 y 100 caracteres, " .
                    'solo minúsculas, dígitos y guiones, y no puede empezar ni terminar con guión.'
            );
        }

        return new self($slug);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
