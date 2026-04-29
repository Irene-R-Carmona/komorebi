<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Waitlist;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí? Constantes del modelo Waitlist (estados y timeout por defecto).
 * ¿Qué me quieres demostrar? Que los valores canónicos de las constantes no cambian por accidente.
 * ¿Qué va a fallar en este test si se cambia el código? Si se modifican los valores de las constantes.
 */
final class WaitlistTest extends TestCase
{
    public function testStatusConstants(): void
    {
        $this->assertSame('waiting',   Waitlist::STATUS_WAITING);
        $this->assertSame('notified',  Waitlist::STATUS_NOTIFIED);
        $this->assertSame('confirmed', Waitlist::STATUS_CONFIRMED);
        $this->assertSame('expired',   Waitlist::STATUS_EXPIRED);
        $this->assertSame('cancelled', Waitlist::STATUS_CANCELLED);
    }

    public function testDefaultResponseTimeoutConstant(): void
    {
        $this->assertSame(15, Waitlist::DEFAULT_RESPONSE_TIMEOUT);
    }
}
