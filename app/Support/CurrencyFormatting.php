<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralizes number / currency formatting patterns repeated across views and services.
 *
 * All methods return '0' / '¥0' style safe defaults rather than throwing
 * when values are null or NaN, so callers can pass `$row['price'] ?? 0`
 * without extra coercion.
 */
final class CurrencyFormatting
{
    /**
     * Formats an integer or float as a Yen string: '¥1,234'.
     * Negative values are rendered as '¥-1,234'.
     *
     * Example: 1234 → '¥1,234'
     */
    public static function yen(int|float $value): string
    {
        return '¥' . \number_format((float) $value, 0, '.', ',');
    }

    /**
     * Formats a float rating to 1 decimal place: '4.5'.
     * Clamps to the range [0, 5] to prevent nonsensical display values.
     *
     * Example: 4.567 → '4.6'
     */
    public static function rating(float $value): string
    {
        $clamped = \max(0.0, \min(5.0, $value));

        return \number_format($clamped, 1, '.', '');
    }

    /**
     * Formats a float as a percentage string: '45.5%'.
     *
     * Example: 45.5 → '45.5%' ; 45.567 (decimals=1) → '45.6%'
     */
    public static function percentage(float $value, int $decimals = 1): string
    {
        return \number_format($value, $decimals, '.', ',') . '%';
    }

    /**
     * Formats an integer or float with thousand separators and no symbol: '1,234'.
     * Useful for guest counts, item quantities, etc.
     *
     * Example: 1234 → '1,234'
     */
    public static function number(int|float $value): string
    {
        return \number_format((float) $value, 0, '.', ',');
    }
}
