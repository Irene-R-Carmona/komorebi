<?php

declare(strict_types=1);

namespace App\Support;

final class CurrencyFormatting
{
    /**
     * Formatea céntimos como Euro: '12,50 €'
     * Ejemplo: 1250 → '12,50 €'; 800 → '8,00 €'; 0 → '0,00 €'
     */
    public static function euro(int $cents): string
    {
        if ($cents < 0) {
            return '-' . \number_format(abs($cents) / 100, 2, ',', '.') . ' €';
        }
        return \number_format($cents / 100, 2, ',', '.') . ' €';
    }

    /**
     * Formatea céntimos como Euro corto (sin decimales si son .00)
     * Ejemplo: 1200 → '12 €'; 1250 → '12,50 €'
     */
    public static function euroShort(int $cents): string
    {
        $euros = $cents / 100;
        if ($euros === (int) $euros) {
            return (int) $euros . ' €';
        }
        return \number_format($euros, 2, ',', '.') . ' €';
    }

    /**
     * Formatea número con separadores de miles (sin símbolo)
     * Ejemplo: 1234 → '1.234'
     */
    public static function number(int|float $value): string
    {
        return \number_format((float) $value, 0, '.', ',');
    }

    /**
     * Formatea rating 0-5 con 1 decimal
     */
    public static function rating(float $value): string
    {
        $clamped = \max(0.0, \min(5.0, $value));
        return \number_format($clamped, 1, '.', '');
    }

    /**
     * Formatea porcentaje
     */
    public static function percentage(float $value, int $decimals = 1): string
    {
        return \number_format($value, $decimals, ',', '.') . '%';
    }
}
