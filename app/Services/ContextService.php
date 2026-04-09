<?php

declare(strict_types=1);

namespace App\Services;

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

    private static ?array $cafeCache = null;

    // ─────────────────────────────────────────────────────────────
    // Obtención de Contexto
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el ID del café activo para el usuario actual.
     *
     * @return integer|null ID del café o null (vista global para admin)
     */
    public static function getCafeId(): ?int
    {
        $role = Session::role();
        $userCafeId = Session::userCafeId();

        // Staff/Manager/Keeper: siempre su café asignado
        if ($role !== Middleware::ROLE_ADMIN && $userCafeId !== null) {
            return $userCafeId;
        }

        // Admin: puede tener un café seleccionado o vista global
        if ($role === Middleware::ROLE_ADMIN) {
            $selectedId = Session::get(self::ADMIN_CAFE_KEY);

            return $selectedId !== null ? (int) $selectedId : null;
        }

        return $userCafeId;
    }

    /**
     * Verifica si el usuario tiene contexto de café definido.
     */
    public static function hasCafeContext(): bool
    {
        return self::getCafeId() !== null;
    }

    /**
     * Verifica si el usuario puede ver todos los cafés (admin sin selección).
     */
    public static function isGlobalView(): bool
    {
        return Session::role() === Middleware::ROLE_ADMIN
            && self::getCafeId() === null;
    }

    /**
     * Obtiene el café actual con todos sus datos.
     */
    public static function getCafe(): ?array
    {
        $cafeId = self::getCafeId();

        if ($cafeId === null) {
            return null;
        }

        // Cache para evitar múltiples queries en la misma request
        if (self::$cafeCache !== null && self::$cafeCache['id'] === $cafeId) {
            return self::$cafeCache;
        }

        $cafe = new Cafe();
        self::$cafeCache = $cafe->findById($cafeId);

        return self::$cafeCache;
    }

    /**
     * Obtiene el nombre del café actual.
     */
    public static function getCafeName(): string
    {
        $cafe = self::getCafe();

        return $cafe['name'] ?? 'Vista Global';
    }

    /**
     * Obtiene el slug del café actual.
     */
    public static function getCafeSlug(): ?string
    {
        $cafe = self::getCafe();

        return $cafe['slug'] ?? null;
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
        self::$cafeCache = $cafe;

        return true;
    }

    /**
     * Limpia la selección de café del admin (vuelve a vista global).
     */
    public static function clearSelection(): void
    {
        if (Session::role() === Middleware::ROLE_ADMIN) {
            Session::remove(self::ADMIN_CAFE_KEY);
            self::$cafeCache = null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Validación de Acceso
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica si el usuario puede acceder a un café específico.
     */
    public static function canAccessCafe(int $cafeId): bool
    {
        $role = Session::role();

        // Admin puede acceder a cualquier café
        if ($role === Middleware::ROLE_ADMIN) {
            return true;
        }

        // Otros roles solo a su café asignado
        return Session::userCafeId() === $cafeId;
    }

    /**
     * Requiere que el usuario tenga contexto de café.
     * Lanza excepción si no hay contexto.
     */
    public static function requireCafeContext(): int
    {
        $cafeId = self::getCafeId();

        if ($cafeId === null) {
            throw new RuntimeException('Se requiere seleccionar un café para esta operación.');
        }

        return $cafeId;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers para Vistas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene datos de contexto para pasar a las vistas.
     */
    public static function getViewData(): array
    {
        return [
            'cafe_id' => self::getCafeId(),
            'cafe_name' => self::getCafeName(),
            'cafe' => self::getCafe(),
            'is_global' => self::isGlobalView(),
            'can_switch' => Session::role() === Middleware::ROLE_ADMIN,
        ];
    }

    /**
     * Limpia la cache (útil para testing).
     */
    public static function clearCache(): void
    {
        self::$cafeCache = null;
    }
}
