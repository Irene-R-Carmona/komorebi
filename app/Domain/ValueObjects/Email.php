<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Email
{
    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $email): self
    {
        $email = \strtolower(\trim($email));

        if (\strlen($email) > 254) {
            throw new InvalidArgumentException('Email supera la longitud máxima permitida (254 caracteres).');
        }

        if (!\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email inválido: \"{$email}\".");
        }

        return new self($email);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
