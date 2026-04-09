<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cookie Manager - Gestión centralizada de cookies
 * RGPD compliant con consentimiento granular
 */
final class CookieManager
{
    // Categorías de cookies
    public const CATEGORY_ESSENTIAL = 'essential';
    public const CATEGORY_FUNCTIONAL = 'functional';
    public const CATEGORY_ANALYTICS = 'analytics';

    // Nombres de cookies
    public const COOKIE_CONSENT = 'cookie_consent';
    public const FILTER_PREFERENCES = 'filter_preferences';
    public const RECENTLY_VIEWED = 'recently_viewed';
    public const NEWSLETTER_PROMPTED = 'newsletter_prompted';
    public const DIETARY_PREFERENCES = 'dietary_preferences';

    // Duración (en días)
    private const DURATIONS = [
        self::COOKIE_CONSENT => 365,
        self::FILTER_PREFERENCES => 90,
        self::RECENTLY_VIEWED => 30,
        self::NEWSLETTER_PROMPTED => 180,
        self::DIETARY_PREFERENCES => 180,
    ];

    /**
     * Establece una cookie con configuración segura
     */
    public static function set(string $name, string|false $value, ?int $days = null): bool
    {
        $days ??= (self::DURATIONS[$name] ?? 30);
        $expires = \time() + ($days * 86400);
        return \setcookie(
            $name,
            (string) $value,
            [
                'expires' => $expires,
                'path' => '/',
                'domain' => '', // Same-origin
                'secure' => isset($_SERVER['HTTPS']), // Solo HTTPS en producción
                'httponly' => false, // Accesible desde JS para funcionales
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Obtiene valor de cookie
     *
     * @param string $name
     * @param null|string $default
     *
     * @return mixed
     */
    public static function get(string $name, string|null $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Elimina una cookie
     */
    public static function delete(string $name): bool
    {
        if (isset($_COOKIE[$name])) {
            unset($_COOKIE[$name]);
        }

        return \setcookie(
            $name,
            '',
            [
                'expires' => \time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Verifica si usuario ha dado consentimiento
     */
    public static function hasConsent(string $category = self::CATEGORY_FUNCTIONAL): bool
    {
        // Esenciales siempre permitidas
        if ($category === self::CATEGORY_ESSENTIAL) {
            return true;
        }

        $consent = self::get(self::COOKIE_CONSENT);
        if (!is_string($consent) || $consent === '') {
            return false;
        }

        $preferences = \json_decode((string) $consent, true);
        if (!is_array($preferences)) {
            return false;
        }

        return $preferences[$category] ?? false;
    }

    /**
     * Guarda preferencias de consentimiento
     */
    public static function saveConsent(array $preferences): bool
    {
        return self::set(
            self::COOKIE_CONSENT,
            \json_encode($preferences),
            365
        );
    }

    /**
     * Acepta todas las cookies
     */
    public static function acceptAll(): bool
    {
        return self::saveConsent([
            self::CATEGORY_ESSENTIAL => true,
            self::CATEGORY_FUNCTIONAL => true,
            self::CATEGORY_ANALYTICS => false, // Por ahora no hay analytics
        ]);
    }

    /**
     * Rechaza cookies opcionales (solo esenciales)
     */
    public static function rejectOptional(): bool
    {
        // Eliminar cookies funcionales existentes
        self::delete(self::FILTER_PREFERENCES);
        self::delete(self::RECENTLY_VIEWED);
        self::delete(self::NEWSLETTER_PROMPTED);
        self::delete(self::DIETARY_PREFERENCES);

        return self::saveConsent([
            self::CATEGORY_ESSENTIAL => true,
            self::CATEGORY_FUNCTIONAL => false,
            self::CATEGORY_ANALYTICS => false,
        ]);
    }

    /**
     * Guarda filtros del catálogo
     */
    public static function saveFilters(array $filters): bool
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return false;
        }

        return self::set(
            self::FILTER_PREFERENCES,
            \json_encode($filters),
            90
        );
    }

    /**
     * Obtiene filtros guardados
     */
    public static function getFilters(): ?array
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return null;
        }

        $value = self::get(self::FILTER_PREFERENCES);
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = \json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Añade producto a vistos recientemente (máximo 10)
     */
    public static function addRecentlyViewed(int $productId): bool
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return false;
        }

        $recent = self::getRecentlyViewed();

        // Eliminar si ya existe (para moverlo al principio)
        $recent = \array_filter($recent, static fn($id) => $id !== $productId);

        // Añadir al principio
        \array_unshift($recent, $productId);

        // Limitar a 10 productos
        $recent = \array_slice($recent, 0, 10);

        return self::set(
            self::RECENTLY_VIEWED,
            \json_encode($recent),
            30
        );
    }

    /**
     * Obtiene productos vistos recientemente
     */
    public static function getRecentlyViewed(): array
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return [];
        }

        $value = self::get(self::RECENTLY_VIEWED);
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = \json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Marca newsletter como mostrado
     */
    public static function markNewsletterPrompted(): bool
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return false;
        }

        return self::set(
            self::NEWSLETTER_PROMPTED,
            '1',
            180
        );
    }

    /**
     * Verifica si ya se mostró popup newsletter
     */
    public static function wasNewsletterPrompted(): bool
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return false;
        }

        return (bool) self::get(self::NEWSLETTER_PROMPTED);
    }

    /**
     * Guarda preferencias dietéticas
     */
    public static function saveDietaryPreferences(array $preferences): bool
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return false;
        }

        return self::set(
            self::DIETARY_PREFERENCES,
            \json_encode($preferences),
            180
        );
    }

    /**
     * Obtiene preferencias dietéticas
     */
    public static function getDietaryPreferences(): ?array
    {
        if (!self::hasConsent(self::CATEGORY_FUNCTIONAL)) {
            return null;
        }

        $value = self::get(self::DIETARY_PREFERENCES);
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = \json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
