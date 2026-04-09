<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Random\RandomException;

/**
 * Trait para modelos que usan UUID como identificador público.
 *
 * Genera UUIDs v4 (random) compatibles con RFC 4122.
 */
trait HasUuid
{
    /**
     * Genera un UUID v4 (random).
     *
     * Formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     * Donde y es 8, 9, A, o B.
     *
     * @throws RandomException
     */
    protected function generateUuid(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \random_int(0, 0xffff),
            \random_int(0, 0xffff),
            \random_int(0, 0xffff),
            \random_int(0, 0x0fff) | 0x4000,  // Versión 4
            \random_int(0, 0x3fff) | 0x8000,  // Variante RFC 4122
            \random_int(0, 0xffff),
            \random_int(0, 0xffff),
            \random_int(0, 0xffff)
        );
    }

    /**
     * Valida formato de UUID.
     */
    protected function isValidUuid(string $uuid): bool
    {
        return (bool) \preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}
