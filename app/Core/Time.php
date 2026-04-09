<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class Time
{
    public static function businessTz(): DateTimeZone
    {
        $tz = Env::get('APP_BUSINESS_TIMEZONE', 'Asia/Tokyo');

        try {
            return new DateTimeZone($tz);
        } catch (Exception) {
            return new DateTimeZone('Asia/Tokyo');
        }
    }

    /**
     * @throws \DateMalformedStringException
     *
     * @return DateTimeImmutable
     */
    public static function nowBusiness(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::businessTz());
    }

    /**
     * @throws \DateMalformedStringException
     *
     * @return string
     */
    public static function todayBusinessYmd(): string
    {
        return self::nowBusiness()->format('Y-m-d');
    }

    /**
     * Combina date Y-m-d + time HH:MM en timezone negocio.
     */
    public static function combineBusiness(string $dateYmd, string $timeHHMM): DateTimeImmutable
    {
        // Validación mínima de formato para evitar parseos raros
        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            throw DateMalformedStringException::fromValue($dateYmd, 'Y-m-d');
        }

        if (!\preg_match('/^\d{2}:\d{2}$/', $timeHHMM)) {
            throw DateMalformedStringException::fromValue($timeHHMM, 'H:i');
        }

        $dt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $dateYmd . ' ' . $timeHHMM,
            self::businessTz()
        );

        if ($dt === false) {
            throw DateMalformedStringException::fromValue($dateYmd . ' ' . $timeHHMM, 'Y-m-d H:i');
        }

        return $dt;
    }

    /**
     * Días desde hoy (negocio) hasta $dateYmd:
     * - hoy => 0
     * - mañana => 1
     * - ayer => -1
     *
     * @param string $dateYmd
     *
     * @throws \DateMalformedStringException
     *
     * @return integer
     */
    public static function daysAheadBusiness(string $dateYmd): int
    {
        $today = DateTimeImmutable::createFromFormat('Y-m-d', self::todayBusinessYmd(), self::businessTz());
        $target = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd, self::businessTz());

        if ($today === false || $target === false) {
            throw DateMalformedStringException::fromValue($dateYmd, 'Y-m-d');
        }

        // diff -> %r%a (días con signo)
        return (int) $today->diff($target)->format('%r%a');
    }
}
