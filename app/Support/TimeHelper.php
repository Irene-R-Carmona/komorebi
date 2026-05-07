<?php

declare(strict_types=1);

namespace App\Support;

final class TimeHelper
{
    public static function isValid(string $time): bool
    {
        return (bool) \preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time);
    }

    /** Returns negative, zero, or positive like strcmp / spaceship. */
    public static function compare(string $time1, string $time2): int
    {
        return \strtotime(self::normalize($time1)) <=> \strtotime(self::normalize($time2));
    }

    public static function normalize(string $time): string
    {
        if (\preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    /**
     * Returns '14:30' from '14:30:00' or '14:30'.
     * If $time is empty or invalid, returns ''.
     */
    public static function display(string $time): string
    {
        if ($time === '') {
            return '';
        }

        return \substr($time, 0, 5);
    }

    /**
     * Returns human duration like '5h 30min' from two HH:MM[:SS] strings.
     * Returns '' if either value is empty or invalid.
     */
    public static function duration(string $start, string $end): string
    {
        if ($start === '' || $end === '') {
            return '';
        }

        $startNorm = self::normalize($start);
        $endNorm = self::normalize($end);

        $startTs = \strtotime('2000-01-01 ' . $startNorm);
        $endTs = \strtotime('2000-01-01 ' . $endNorm);

        if ($startTs === false || $endTs === false) {
            return '';
        }

        $diff = $endTs - $startTs;

        if ($diff < 0) {
            $diff += 86400; // overnight shift
        }

        $hours = (int) ($diff / 3600);
        $minutes = (int) (($diff % 3600) / 60);

        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'min';
        }

        if ($hours > 0) {
            return $hours . 'h';
        }

        return $minutes . 'min';
    }

    /**
     * Returns array with 'from' and 'to' date strings (Y-m-d) for the ISO week
     * offset by $offset weeks from today. Week starts on Monday.
     *
     * @return array{from: string, to: string}
     */
    public static function weekRange(int $offset = 0): array
    {
        $monday = \strtotime('monday this week');

        if ($monday === false) {
            $monday = \time();
        }

        $monday += $offset * 7 * 86400;

        return [
            'from' => \date('Y-m-d', $monday),
            'to' => \date('Y-m-d', $monday + 6 * 86400),
        ];
    }
}
