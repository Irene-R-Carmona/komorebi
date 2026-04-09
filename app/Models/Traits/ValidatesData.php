<?php

declare(strict_types=1);

namespace App\Models\Traits;

use RuntimeException;

/**
 * Trait con helpers de validación comunes para modelos.
 */
trait ValidatesData
{
    /**
     * Valida que existan campos requeridos.
     *
     * @param array         $data   Datos a validar
     * @param array<string> $fields Campos requeridos
     * @throws RuntimeException Si falta algún campo
     */
    protected function validateRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                throw new RuntimeException("El campo '$field' es requerido.");
            }

            if (\is_string($data[$field]) && \trim($data[$field]) === '') {
                throw new RuntimeException("El campo '$field' no puede estar vacío.");
            }
        }
    }

    /**
     * Valida que un valor esté en una lista permitida.
     *
     * @param mixed  $value   Valor a validar
     * @param array  $allowed Valores permitidos
     * @param string $field   Nombre del campo (para mensaje de error)
     * @throws RuntimeException
     */
    protected function validateInArray(mixed $value, array $allowed, string $field): void
    {
        if (!\in_array($value, $allowed, true)) {
            $allowedStr = \implode(', ', $allowed);
            throw new RuntimeException("Valor inválido para '$field'. Permitidos: $allowedStr");
        }
    }

    /**
     * Valida que un valor sea un entero positivo.
     */
    protected function validatePositiveInt(mixed $value, string $field): int
    {
        $int = \filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false || $int < 1) {
            throw new RuntimeException("'$field' debe ser un número entero positivo.");
        }

        return $int;
    }

    /**
     * Sanitiza un string (trim + límite de longitud).
     */
    protected function sanitizeString(string $value, int $maxLength = 255): string
    {
        return \mb_substr(\trim($value), 0, $maxLength);
    }

    /**
     * Sanitiza un slug (lowercase, solo caracteres válidos).
     */
    protected function sanitizeSlug(string $value): string
    {
        $slug = \strtolower(\trim($value));
        $slug = \preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = \preg_replace('/-+/', '-', $slug);

        return \trim($slug, '-');
    }
}
