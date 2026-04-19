<?php

declare(strict_types=1);

namespace App\Domain\Allergen;

/**
 * Genera códigos cortos de alérgenos a partir de un nombre.
 *
 * Clase pura: sin PDO, sin estado, sin efectos secundarios.
 * Testeable sin infraestructura. Longitud máxima: 10 caracteres ASCII mayúsculas.
 */
final class AllergenCodeGenerator
{
    private const int MAX_LENGTH = 10;
    private const string FALLBACK = 'ALLERGEN';

    /**
     * Genera un código de hasta 10 caracteres ASCII en mayúsculas a partir de un nombre.
     * Translitea caracteres Unicode, elimina no alfanuméricos y trunca.
     */
    public static function fromName(string $name): string
    {
        $transliterated = @\iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($transliterated === false) {
            $transliterated = '';
        }

        $normalized = (string) \preg_replace('/[^A-Za-z0-9]/', '', $transliterated);
        $normalized = \strtoupper($normalized);

        if ($normalized === '') {
            return self::FALLBACK;
        }

        return \substr($normalized, 0, self::MAX_LENGTH);
    }
}

