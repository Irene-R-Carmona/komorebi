<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * El enum ReservationStatus representa los estados de ciclo de vida de una
 * reserva y expone etiquetas en español para mostrar en la UI.
 *
 * ¿Qué me quieres demostrar?
 * Que cada case devuelve la etiqueta correcta en label(), que labelFor()
 * resuelve un string válido a la etiqueta y usa ucfirst como fallback.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en las etiquetas, en los valores de los cases, o en la
 * lógica de fallback de labelFor().
 */

namespace Tests\Unit\Domain;

use App\Domain\Reservation\ReservationStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationStatus::class)]
final class ReservationStatusTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Backed enum values
    // ──────────────────────────────────────────────────────────

    public function testEnumCasesHaveCorrectStringValues(): void
    {
        self::assertSame('pending', ReservationStatus::Pending->value);
        self::assertSame('confirmed', ReservationStatus::Confirmed->value);
        self::assertSame('active', ReservationStatus::Active->value);
        self::assertSame('completed', ReservationStatus::Completed->value);
        self::assertSame('cancelled', ReservationStatus::Cancelled->value);
        self::assertSame('no_show', ReservationStatus::NoShow->value);
        self::assertSame('checked_in', ReservationStatus::CheckedIn->value);
    }

    // ──────────────────────────────────────────────────────────
    // label()
    // ──────────────────────────────────────────────────────────

    public function testPendingLabel(): void
    {
        self::assertSame('Pendiente', ReservationStatus::Pending->label());
    }

    public function testConfirmedLabel(): void
    {
        self::assertSame('Confirmada', ReservationStatus::Confirmed->label());
    }

    public function testActiveLabel(): void
    {
        self::assertSame('Activa', ReservationStatus::Active->label());
    }

    public function testCompletedLabel(): void
    {
        self::assertSame('Completada', ReservationStatus::Completed->label());
    }

    public function testCancelledLabel(): void
    {
        self::assertSame('Cancelada', ReservationStatus::Cancelled->label());
    }

    public function testNoShowLabel(): void
    {
        self::assertSame('No Show', ReservationStatus::NoShow->label());
    }

    public function testCheckedInLabel(): void
    {
        self::assertSame('En local', ReservationStatus::CheckedIn->label());
    }

    // ──────────────────────────────────────────────────────────
    // labelFor(string)
    // ──────────────────────────────────────────────────────────

    public function testLabelForKnownStatusReturnsLabel(): void
    {
        self::assertSame('Pendiente', ReservationStatus::labelFor('pending'));
        self::assertSame('Confirmada', ReservationStatus::labelFor('confirmed'));
        self::assertSame('No Show', ReservationStatus::labelFor('no_show'));
    }

    public function testLabelForUnknownStatusReturnsUcfirst(): void
    {
        self::assertSame('Unknown', ReservationStatus::labelFor('unknown'));
    }

    public function testLabelForEmptyStringReturnsEmpty(): void
    {
        // ucfirst('') === ''
        self::assertSame('', ReservationStatus::labelFor(''));
    }

    public function testAllCasesHaveNonEmptyLabel(): void
    {
        foreach (ReservationStatus::cases() as $case) {
            self::assertNotEmpty($case->label(), "Label vacío para case: {$case->name}");
        }
    }
}
