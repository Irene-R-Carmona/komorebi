<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface NavigationServiceInterface
{
    /**
     * Obtiene el menú de navegación para un rol.
     *
     * @return array<string, array<int, array{icon: string, label: string, url: string, badge?: int}>>
     */
    public function getMenu(string $role): array;

    /**
     * Obtiene el menú completo con badges dinámicos.
     *
     * @param array<string, int> $badges
     * @return array<string, array<int, array{icon: string, label: string, url: string, badge?: int}>>
     */
    public function getMenuBadged(string $role, array $badges = []): array;

    /**
     * Verifica si una URL pertenece a la sección activa.
     */
    public function checkIsActive(string $itemUrl, string $currentUrl): bool;

    /**
     * Verifica si un path pertenece al backoffice.
     */
    public function isBackofficePath(string $path): bool;

    /**
     * Sugiere un enlace de retorno basado en el contexto del error.
     *
     * @return array{href: string, label: string}
     */
    public function suggestedLink(string $path, bool $isAuthenticated, string $role): array;
}
