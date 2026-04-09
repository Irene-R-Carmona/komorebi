<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

/**
 * Wrapper para strings que NO deben escaparse por View::escapeData().
 *
 * Uso típico:
 * - JSON para Alpine x-data
 * - HTML pre-renderizado seguro
 *
 * AVISO: SOLO usar con datos que YA han sido sanitizados o generados internamente.
 */
final readonly class Raw
{
    public function __construct(
        public string $value
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Genera JSON seguro para embeber en HTML.
     *
     * Los flags HEX_* previenen XSS al escapar caracteres problemáticos:
     * - HEX_TAG: < y > → \u003C y \u003E
     * - HEX_AMP: & → \u0026
     * - HEX_APOS: ' → \u0027
     * - HEX_QUOT: " → \u0022
     *
     * @throws JsonException
     */
    public static function json(array $data): self
    {
        $json = \json_encode(
            $data,
            JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
        );

        // Con JSON_THROW_ON_ERROR, nunca será false, pero TypeScript-style safety
        return new self($json);
    }

    /**
     * Crea Raw desde HTML ya sanitizado.
     *
     * PELIGROSO si el HTML viene de usuario sin sanitizar.
     * Solo usar con HTML generado internamente o pasado por un sanitizer.
     */
    public static function html(string $trustedHtml): self
    {
        return new self($trustedHtml);
    }

    /**
     * Envuelve un valor que ya está escapado (evita doble escape).
     */
    public static function safe(string $alreadyEscaped): self
    {
        return new self($alreadyEscaped);
    }

    /**
     * Decodifica JSON de forma segura retornando array.
     * Si falla, retorna array vacío (no lanza excepción).
     * Útil para JSON almacenado en BD que puede estar malformado.
     *
     * @param string $json
     *
     * @return array
     */
    public static function decodeJsonArray(string $json): array
    {
        try {
            $decoded = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return \is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Decodifica JSON de forma segura retornando objeto como array asociativo.
     * Si falla, retorna array vacío.
     * Alias semántico de decodeJsonArray() para objetos JSON.
     *
     * @return array<string, mixed>
     */
    public static function decodeJsonObject(string $json): array
    {
        return self::decodeJsonArray($json);
    }
}
