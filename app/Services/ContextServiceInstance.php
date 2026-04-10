<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Middleware;
use App\Repositories\Contracts\CafeRepositoryInterface;
use RuntimeException;

/**
 * Versión inyectable de ContextService.
 *
 * Diseñada para inyección de dependencias (per-request via Container::bind).
 * Reemplaza el uso estático de ContextService en nuevos controladores.
 *
 * @see \App\Services\ContextService  Clase original (métodos estáticos, @deprecated)
 */
final class ContextServiceInstance
{
    private ?array $cafeCache = null;

    public function __construct(
        private readonly CafeRepositoryInterface $cafeRepo,
        private readonly string $role,
        private readonly ?int $userCafeId,
        private readonly ?int $adminSelectedCafeId
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Obtención de Contexto
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el ID del café activo para el usuario actual.
     */
    public function getCafeId(): ?int
    {
        // Staff/Manager/Keeper: siempre su café asignado
        if ($this->role !== Middleware::ROLE_ADMIN && $this->userCafeId !== null) {
            return $this->userCafeId;
        }

        // Admin: puede tener un café seleccionado o vista global
        if ($this->role === Middleware::ROLE_ADMIN) {
            return $this->adminSelectedCafeId;
        }

        return $this->userCafeId;
    }

    /**
     * Verifica si el usuario tiene contexto de café definido.
     */
    public function hasCafeContext(): bool
    {
        return $this->getCafeId() !== null;
    }

    /**
     * Verifica si el usuario puede ver todos los cafés (admin sin selección).
     */
    public function isGlobalView(): bool
    {
        return $this->role === Middleware::ROLE_ADMIN
            && $this->getCafeId() === null;
    }

    /**
     * Obtiene el café actual con todos sus datos.
     */
    public function getCafe(): ?array
    {
        $cafeId = $this->getCafeId();

        if ($cafeId === null) {
            return null;
        }

        // Cache para evitar múltiples queries en la misma request
        if ($this->cafeCache !== null && ($this->cafeCache['id'] ?? null) === $cafeId) {
            return $this->cafeCache;
        }

        $this->cafeCache = $this->cafeRepo->findById($cafeId);

        return $this->cafeCache;
    }

    /**
     * Obtiene el nombre del café actual.
     */
    public function getCafeName(): string
    {
        return $this->getCafe()['name'] ?? 'Vista Global';
    }

    /**
     * Obtiene el slug del café actual.
     */
    public function getCafeSlug(): ?string
    {
        return $this->getCafe()['slug'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────
    // Validación de Acceso
    // ─────────────────────────────────────────────────────────────

    /**
     * Verifica si el usuario puede acceder a un café específico.
     */
    public function canAccessCafe(int $cafeId): bool
    {
        // Admin puede acceder a cualquier café
        if ($this->role === Middleware::ROLE_ADMIN) {
            return true;
        }

        // Otros roles solo a su café asignado
        return $this->userCafeId === $cafeId;
    }

    /**
     * Requiere que el usuario tenga contexto de café.
     * Lanza excepción si no hay contexto.
     */
    public function requireCafeContext(): int
    {
        $cafeId = $this->getCafeId();

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
    public function getViewData(): array
    {
        return [
            'cafe_id'    => $this->getCafeId(),
            'cafe_name'  => $this->getCafeName(),
            'cafe'       => $this->getCafe(),
            'is_global'  => $this->isGlobalView(),
            'can_switch' => $this->role === Middleware::ROLE_ADMIN,
        ];
    }
}
