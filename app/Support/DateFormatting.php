<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralizes date formatting patterns repeated across views and services.
 *
 * All methods return an empty string when the input is empty or unparseable,
 * so callers can safely pass `$row['date'] ?? ''` without null-checks.
 */
final class DateFormatting
{
    /**
     * Formats a Y-m-d date string as 'd/m/Y' (Spanish convention).
     * Returns '' when $dateYmd is empty or invalid.
     *
     * Example: '2026-05-06' → '06/05/2026'
     */
    public static function toSpanishDate(string $dateYmd): string
    {
        if ($dateYmd === '') {
            return '';
        }

        $ts = \strtotime($dateYmd);

        if ($ts === false) {
            return '';
        }

        return \date('d/m/Y', $ts);
    }

    /**
     * Formats a datetime string as 'd/m/Y H:i' (Spanish convention).
     * Returns '' when $datetimeStr is empty or invalid.
     *
     * Example: '2026-05-06 14:30:00' → '06/05/2026 14:30'
     */
    public static function toSpanishDateTime(string $datetimeStr): string
    {
        if ($datetimeStr === '') {
            return '';
        }

        $ts = \strtotime($datetimeStr);

        if ($ts === false) {
            return '';
        }

        return \date('d/m/Y H:i', $ts);
    }

    /**
     * Adds $days to a Y-m-d date string and returns the result as Y-m-d.
     * Returns '' when $dateYmd is empty or invalid, or when $days = 0.
     *
     * Replaces inline `date('Y-m-d', strtotime('+N days', strtotime($date)))`.
     */
    public static function dateAdd(string $dateYmd, int $days): string
    {
        if ($dateYmd === '') {
            return '';
        }

        $ts = \strtotime($dateYmd);

        if ($ts === false) {
            return '';
        }

        return \date('Y-m-d', $ts + $days * 86400);
    }

    public static function toSpanishLongDate(string $datetimeStr): string
    {
        if ($datetimeStr === '') {
            return '';
        }

        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $ts = \strtotime($datetimeStr);
        if ($ts === false) {
            return $datetimeStr; // Fallback si no es válido
        }

        $day = (int) \date('j', $ts); // 'j' para día sin cero inicial
        $month = (int) \date('n', $ts); // 'n' para mes numérico sin cero
        $year = \date('Y', $ts);
        $time = \date('H:i', $ts);

        return "$day de {$months[$month]} de $year a las $time";
    }
}
