<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

namespace Events;

use App\Events\UserRegisteredEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UserRegisteredEventTest extends TestCase
{
    public function testEventCanBeCreated(): void
    {
        $userId = 1;
        $email = 'test@example.com';
        $name = 'Test User';
        $registeredAt = new DateTimeImmutable();

        $event = new UserRegisteredEvent($userId, $email, $name, $registeredAt);

        $this->assertSame($userId, $event->userId);
        $this->assertSame($email, $event->email);
        $this->assertSame($name, $event->name);
        $this->assertSame($registeredAt, $event->registeredAt);
    }

    public function testEventIsReadonly(): void
    {
        $event = new UserRegisteredEvent(
            1,
            'test@example.com',
            'Test User',
            new DateTimeImmutable()
        );

        $ref = new ReflectionClass($event);
        $this->assertTrue($ref->isReadOnly());
    }

    public function testEventPropertiesArePublic(): void
    {
        $event = new UserRegisteredEvent(
            42,
            'user@example.com',
            'John Doe',
            new DateTimeImmutable('2026-02-03 10:00:00')
        );

        $this->assertSame(42, $event->userId);
        $this->assertSame('user@example.com', $event->email);
        $this->assertSame('John Doe', $event->name);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->registeredAt);
    }
}
