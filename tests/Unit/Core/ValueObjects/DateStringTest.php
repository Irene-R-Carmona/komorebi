<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO DateString.
 * ¿Qué me quieres demostrar? Que DateString garantiza formato YYYY-MM-DD y fecha real.
 * ¿Qué va a fallar en este test si se cambia el código? Si se acepta una fecha inválida como 2024-02-30.
 */

use App\Core\ValueObjects\DateString;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class DateStringTest extends TestCase
{
    public function testValidDateIsAccepted(): void
    {
        $d = new DateString('2025-06-15');
        $this->assertSame('2025-06-15', $d->getValue());
    }

    public function testInvalidFormatThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('15-06-2025');
    }

    public function testImpossibleDateThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('2024-02-30');
    }

    public function testEmptyStringThrows(): void
    {
        $this->expectException(ValidationException::class);
        new DateString('');
    }

    public function testToStringMatchesGetValue(): void
    {
        $d = new DateString('2025-01-01');
        $this->assertSame('2025-01-01', (string) $d);
    }
}
