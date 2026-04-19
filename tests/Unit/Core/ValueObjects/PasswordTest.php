<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO Password.
 * ¿Qué me quieres demostrar? Que Password garantiza longitud ≥8 ≤128, una mayúscula y un dígito.
 * ¿Qué va a fallar en este test si se cambia el código? Si se relajan las reglas de complejidad.
 */

use App\Core\ValueObjects\Password;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Password::class)]
final class PasswordTest extends TestCase
{
    public function testValidPasswordIsAccepted(): void
    {
        $pwd = new Password('SecurePass1');
        $this->assertSame('SecurePass1', $pwd->getValue());
    }

    public function testShortPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('Ab1');
    }

    public function testTooLongPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password(str_repeat('A1', 65)); // 130 chars
    }

    public function testPasswordWithoutUppercaseThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('lowercase1');
    }

    public function testPasswordWithoutDigitThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('NoDigitsHere');
    }

    public function testEmptyPasswordThrows(): void
    {
        $this->expectException(ValidationException::class);
        new Password('');
    }
}
