<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Middleware;
use App\Core\Session;
use App\Models\Cafe;

/**
 * Servicio de Contexto de Operación
 *
 * Gestiona el contexto del café activo para operaciones del backoffice.
 * - Staff: usa su cafe_id asignado
 * - Manager: usa su cafe_id asignado
 * - Admin: puede seleccionar cualquier café (impersonation)
 */
final class ContextService
{
    private const string ADMIN_CAFE_KEY = 'admin_selected_cafe_id';

    // ─────────────────────────────────────────────────────────────
    // Selección de Café (Admin)
    // ─────────────────────────────────────────────────────────────

    /**
     * Selecciona un café para el admin (impersonation).
     */
    public static function selectCafe(int $cafeId): bool
    {
        if (Session::role() !== Middleware::ROLE_ADMIN) {
            return false;
        }

        // Verificar que el café existe
        $cafeModel = new Cafe();
        $cafe = $cafeModel->findById($cafeId);

        if (!$cafe) {
            return false;
        }

        Session::set(self::ADMIN_CAFE_KEY, $cafeId);

        return true;
    }

    /**
     * Limpia la selección de café del admin (vuelve a vista global).
     */
    public static function clearSelection(): void
    {
        if (Session::role() === Middleware::ROLE_ADMIN) {
            Session::remove(self::ADMIN_CAFE_KEY);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────

    /**
     * Limpia la cache (útil para testing).
     */
    public static function clearCache(): void
    {
        // Cache is now managed per-request by ContextServiceInstance
    }
}
