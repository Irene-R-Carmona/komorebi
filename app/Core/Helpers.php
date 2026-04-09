<?php

declare(strict_types=1);

/**
 * Funciones helper globales.
 *
 * Contiene funciones globales reutilizables por las vistas y templates.
 */

if (!function_exists('e')) {
    /**
     * Escapa HTML para prevenir XSS
     *
     * @param string|null $value
     *
     * @return string
     */
    function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
