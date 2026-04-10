<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Middleware;
use App\Core\Session;
use App\Models\Cafe;
use RuntimeException;

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
    // Obtención de Contexto
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el ID del café activo para el usuario actual.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     * @return integer|null ID del café o null (vista global para admin)
     */
    public static function getCafeId(): ?int
    {
        return Container::make(ContextServiceInstance::class)->getCafeId();
    }

    /**
     * Verifica si el usuario tiene contexto de café definido.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function hasCafeContext(): bool
    {
        return Container::make(ContextServiceInstance::class)->hasCafeContext();
    }

    /**
     * Verifica si el usuario puede ver todos los cafés (admin sin selección).
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function isGlobalView(): bool
    {
        return Container::make(ContextServiceInstance::class)->isGlobalView();
    }

    /**
     * Obtiene el café actual con todos sus datos.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function getCafe(): ?array
    {
        return Container::make(ContextServiceInstance::class)->getCafe();
    }

    /**
     * Obtiene el nombre del café actual.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function getCafeName(): string
    {
        return Container::make(ContextServiceInstance::class)->getCafeName();
    }

    /**
     * Obtiene el slug del café actual.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function getCafeSlug(): ?string
    {
        return Container::make(ContextServiceInstance::class)->getCafeSlug();
    }

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
    // Validación de Acceso
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica si el usuario puede acceder a un café específico.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function canAccessCafe(int $cafeId): bool
    {
        return Container::make(ContextServiceInstance::class)->canAccessCafe($cafeId);
    }

    /**
     * Requiere que el usuario tenga contexto de café.
     * Lanza excepción si no hay contexto.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function requireCafeContext(): int
    {
        return Container::make(ContextServiceInstance::class)->requireCafeContext();
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers para Vistas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene datos de contexto para pasar a las vistas.
     *
     * @deprecated Use ContextServiceInstance via inyección de dependencias.
     */
    public static function getViewData(): array
    {
        return Container::make(ContextServiceInstance::class)->getViewData();
    }

    /**
     * Limpia la cache (útil para testing).
     */
    public static function clearCache(): void
    {
        // Cache is now managed per-request by ContextServiceInstance
    }
}
