<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Tests\Unit\Listeners;

use App\Events\UserRegisteredEvent;
use App\Listeners\LogUserRegisteredListener;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogUserRegisteredListener::class)]
final class LogUserRegisteredListenerTest extends TestCase
{
    public function testListenerCanBeInvoked(): void
    {
        $listener = new LogUserRegisteredListener();
        $event = new UserRegisteredEvent(
            1,
            'test@example.com',
            'Test User',
            new DateTimeImmutable()
        );

        // El listener solo hace logging, verificamos que no lance excepciones
        $listener($event);

        $this->assertTrue(true);
    }

    public function testListenerIsCallable(): void
    {
        $listener = new LogUserRegisteredListener();

        $this->assertTrue(\is_callable($listener));
    }
}
