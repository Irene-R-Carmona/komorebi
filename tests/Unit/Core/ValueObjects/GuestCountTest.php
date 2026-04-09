<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? Construcción y rechazo del VO GuestCount.
 * ¿Qué me quieres demostrar? Que GuestCount acepta rango 1–20.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el rango permitido.
 */

use App\Core\ValueObjects\GuestCount;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class GuestCountTest extends TestCase
{
    public function testValidCountIsAccepted(): void
    {
        $g = new GuestCount(4);
        $this->assertSame(4, $g->getValue());
    }

    public function testMinimumBoundaryIsAccepted(): void
    {
        $g = new GuestCount(1);
        $this->assertSame(1, $g->getValue());
    }

    public function testMaximumBoundaryIsAccepted(): void
    {
        $g = new GuestCount(20);
        $this->assertSame(20, $g->getValue());
    }

    public function testZeroGuestsThrows(): void
    {
        $this->expectException(ValidationException::class);
        new GuestCount(0);
    }

    public function testTwentyOneGuestsThrows(): void
    {
        $this->expectException(ValidationException::class);
        new GuestCount(21);
    }
}
