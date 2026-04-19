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
}
