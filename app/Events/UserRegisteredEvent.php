<?php

declare(strict_types=1);

namespace App\Events;

use DateTimeImmutable;

/**
 * Evento disparado cuando un usuario se registra.
 */
final readonly class UserRegisteredEvent
{
    public function __construct(
        public int $userId,
        public string $email,
        public string $name,
        public DateTimeImmutable $registeredAt
    ) {
    }
}
