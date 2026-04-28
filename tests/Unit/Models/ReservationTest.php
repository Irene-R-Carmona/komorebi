<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Reservation;
use PHPUnit\Framework\TestCase;

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
final class ReservationTest extends TestCase
{
    // ── Constantes ────────────────────────────────────────────────

    public function testStatusConstants(): void
    {
        $this->assertSame('pending',   Reservation::STATUS_PENDING);
        $this->assertSame('confirmed', Reservation::STATUS_CONFIRMED);
        $this->assertSame('active',    Reservation::STATUS_ACTIVE);
        $this->assertSame('completed', Reservation::STATUS_COMPLETED);
        $this->assertSame('cancelled', Reservation::STATUS_CANCELLED);
        $this->assertSame('no_show',   Reservation::STATUS_NO_SHOW);
    }

    public function testCancellableStatusesContainsPendingAndConfirmed(): void
    {
        $this->assertContains('pending',   Reservation::CANCELLABLE_STATUSES);
        $this->assertContains('confirmed', Reservation::CANCELLABLE_STATUSES);
    }

    public function testActiveStatusesContainsThreeStatuses(): void
    {
        $this->assertCount(3, Reservation::ACTIVE_STATUSES);
    }

    public function testMinAdvanceHoursConstant(): void
    {
        $this->assertSame(2, Reservation::MIN_ADVANCE_HOURS);
    }

    public function testMaxAdvanceDaysConstant(): void
    {
        $this->assertSame(30, Reservation::MAX_ADVANCE_DAYS);
    }
}
