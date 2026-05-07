<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO TimeString.
 * ¿Qué me quieres demostrar? Que TimeString garantiza formato HH:MM válido.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta "25:00" o "8:5".
 */

use App\Core\ValueObjects\TimeString;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeString::class)]
final class TimeStringTest extends TestCase
{
    public function testValidTimeIsAccepted(): void
    {
        $t = new TimeString('14:30');
        $this->assertSame('14:30', $t->getValue());
    }

    public function testMidnightIsAccepted(): void
    {
        $t = new TimeString('00:00');
        $this->assertSame('00:00', $t->getValue());
    }

    public function testInvalidHourThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('25:00');
    }

    public function testInvalidMinuteThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('12:60');
    }

    public function testBadFormatThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('9:5');
    }

    public function testEmptyStringThrows(): void
    {
        $this->expectException(ValidationException::class);
        new TimeString('');
    }
}
