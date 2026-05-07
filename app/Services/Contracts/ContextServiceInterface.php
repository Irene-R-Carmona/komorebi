<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use RuntimeException;

/**
 * Contrato para ContextServiceInstance.
 *
 * Define las operaciones de contexto de café para el usuario autenticado.
 * Implementado por ContextServiceInstance (versión inyectable).
 *
 * @see \App\Services\ContextServiceInstance
 */
interface ContextServiceInterface
{
    /**
     * Obtiene el ID del café activo para el usuario actual.
     * Retorna null en vista global (admin sin café seleccionado).
     */
    public function getCafeId(): ?int;

    /**
     * Verifica si el usuario tiene contexto de café definido.
     */
    public function hasCafeContext(): bool;

    /**
     * Verifica si el usuario puede ver todos los cafés (admin sin selección).
     */
    public function isGlobalView(): bool;

    /**
     * Obtiene el café actual con todos sus datos.
     * Retorna null si no hay contexto de café.
     */
    public function getCafe(): ?array;

    /**
     * Obtiene el nombre del café actual.
     * Retorna 'Vista Global' si no hay café seleccionado.
     */
    public function getCafeName(): string;

    /**
     * Obtiene el slug del café actual.
     * Retorna null si no hay contexto de café.
     */
    public function getCafeSlug(): ?string;

    /**
     * Verifica si el usuario puede acceder a un café específico.
     * Admin puede acceder a cualquier café; otros roles solo a su café asignado.
     */
    public function canAccessCafe(int $cafeId): bool;

    /**
     * Requiere que el usuario tenga contexto de café.
     *
     * @throws RuntimeException Si no hay contexto de café definido.
     */
    public function requireCafeContext(): int;

    /**
     * Obtiene datos de contexto para pasar a las vistas.
     *
     * @return array{cafe_id: ?int, cafe_name: string, cafe: ?array, is_global: bool, can_switch: bool}
     */
    public function getViewData(): array;
}
