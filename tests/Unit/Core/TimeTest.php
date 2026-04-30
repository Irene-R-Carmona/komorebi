<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * La clase Time centraliza el acceso al tiempo de negocio (zona horaria
 * configurada por APP_BUSINESS_TIMEZONE) y la combinación fecha+hora.
 *
 * ¿Qué me quieres demostrar?
 * Que businessTz() devuelve una DateTimeZone, que nowBusiness() devuelve una
 * DateTimeImmutable, que todayBusinessYmd() sigue el formato Y-m-d, y que
 * combineBusiness() valida el formato de entrada y lanza excepción al fallar.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cambios en el regex de validación, en el tipo de excepción lanzada, o en el
 * formato de salida de combineBusiness/todayBusinessYmd.
 */

namespace Tests\Unit\Core;

use App\Core\Time;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(Time::class)]
final class TimeTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // businessTz()
    // ──────────────────────────────────────────────────────────

    public function testBusinessTzReturnsDateTimeZone(): void
    {
        $tz = Time::businessTz();
        self::assertInstanceOf(DateTimeZone::class, $tz);
    }

    public function testBusinessTzReturnsSomeTimezoneBasedOnEnv(): void
    {
        // Env::get() usa caché interna — validamos que el tz devuelto es un tz válido de PHP
        $tz = Time::businessTz();
        $allTz = DateTimeZone::listIdentifiers();
        self::assertContains($tz->getName(), $allTz);
    }

    public function testBusinessTzReturnsSameInstanceAcrossCalls(): void
    {
        $tz1 = Time::businessTz();
        $tz2 = Time::businessTz();
        self::assertSame($tz1->getName(), $tz2->getName());
    }

    // ──────────────────────────────────────────────────────────
    // nowBusiness()
    // ──────────────────────────────────────────────────────────

    public function testNowBusinessReturnsDateTimeImmutable(): void
    {
        $now = Time::nowBusiness();
        self::assertInstanceOf(DateTimeImmutable::class, $now);
    }

    public function testNowBusinessTimezoneMatchesBusinessTz(): void
    {
        $tz = Time::businessTz();
        $now = Time::nowBusiness();
        self::assertSame($tz->getName(), $now->getTimezone()->getName());
    }

    // ──────────────────────────────────────────────────────────
    // todayBusinessYmd()
    // ──────────────────────────────────────────────────────────

    public function testTodayBusinessYmdReturnsYmdFormat(): void
    {
        $today = Time::todayBusinessYmd();
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $today);
    }

    public function testTodayBusinessYmdMatchesNowBusiness(): void
    {
        $expected = Time::nowBusiness()->format('Y-m-d');
        self::assertSame($expected, Time::todayBusinessYmd());
    }

    // ──────────────────────────────────────────────────────────
    // combineBusiness()
    // ──────────────────────────────────────────────────────────

    public function testCombineBusinessReturnsDateTimeImmutable(): void
    {
        $dt = Time::combineBusiness('2025-06-15', '10:30');
        self::assertInstanceOf(DateTimeImmutable::class, $dt);
    }

    public function testCombineBusinessPreservesDateAndTime(): void
    {
        $dt = Time::combineBusiness('2025-12-25', '09:00');
        self::assertSame('2025-12-25', $dt->format('Y-m-d'));
        self::assertSame('09:00', $dt->format('H:i'));
    }

    public function testCombineBusinessTimezoneMatchesBusinessTz(): void
    {
        $tz = Time::businessTz();
        $dt = Time::combineBusiness('2025-01-01', '12:00');
        self::assertSame($tz->getName(), $dt->getTimezone()->getName());
    }

    public function testCombineBusinessThrowsOnInvalidDateFormat(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('25-06-15', '10:30'); // DD-MM-YY instead of YYYY-MM-DD
    }

    public function testCombineBusinessThrowsOnDateWithoutHyphens(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('20250615', '10:30');
    }

    public function testCombineBusinessThrowsOnInvalidTimeFormat(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('2025-06-15', '1030'); // no colon
    }

    public function testCombineBusinessThrowsOnTimeWithSeconds(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('2025-06-15', '10:30:00'); // HH:MM:SS not accepted
    }

    public function testCombineBusinessThrowsOnEmptyDate(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('', '10:30');
    }

    public function testCombineBusinessThrowsOnEmptyTime(): void
    {
        $this->expectException(Throwable::class);
        Time::combineBusiness('2025-06-15', '');
    }

    // ──────────────────────────────────────────────────────────
    // daysAheadBusiness()
    // ──────────────────────────────────────────────────────────

    public function testDaysAheadBusinessReturnsIntegerForFutureDate(): void
    {
        // Pick a date far in the future to always be positive
        $result = Time::daysAheadBusiness('2099-12-31');
        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);
    }

    public function testDaysAheadBusinessReturnNegativeForPastDate(): void
    {
        $result = Time::daysAheadBusiness('2000-01-01');
        self::assertLessThan(0, $result);
    }

    public function testDaysAheadBusinessReturnsZeroForToday(): void
    {
        $today = Time::todayBusinessYmd();
        self::assertSame(0, Time::daysAheadBusiness($today));
    }

    public function testDaysAheadBusinessThrowsOnInvalidFormat(): void
    {
        $this->expectException(Throwable::class);
        Time::daysAheadBusiness('15/06/2025');
    }
}
