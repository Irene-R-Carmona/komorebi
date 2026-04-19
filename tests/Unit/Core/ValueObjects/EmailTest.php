<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción, normalización y rechazo de Email VO.
 * ¿Qué me quieres demostrar? Que Email garantiza un valor normalizado o lanza ValidationException.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina la normalización o se relajan las validaciones.
 */

use App\Core\ValueObjects\Email;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    public function testValidEmailIsAccepted(): void
    {
        $email = new Email('User@Example.COM');
        $this->assertSame('user@example.com', $email->getValue());
    }

    public function testToStringMatchesGetValue(): void
    {
        $email = new Email('hello@example.com');
        $this->assertSame('hello@example.com', (string) $email);
    }

    public function testEmptyEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Email('');
    }

    public function testInvalidEmailThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Email('not-an-email');
    }

    public function testEmailWithSpacesIsNormalized(): void
    {
        $email = new Email('  test@EXAMPLE.org  ');
        $this->assertSame('test@example.org', $email->getValue());
    }
}
