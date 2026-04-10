<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Middleware;

/**
 * Servicio de Navegación del Backoffice
 *
 * Genera menús de navegación según el rol del usuario.
 */
final class NavigationService
{
    /**
     * Iconos disponibles (Bootstrap Icons).
     * Migrado de Phosphor Icons a Bootstrap Icons.
     */
    private const array ICONS = [
        'dashboard' => 'speedometer2',
        'users' => 'people',
        'cafes' => 'shop',
        'products' => 'box-seam',
        'reservas' => 'calendar-check',
        'reception' => 'bell',
        'kitchen' => 'fire',
        'animals' => 'heart',
        'incidents' => 'exclamation-triangle',
        'reports' => 'bar-chart',
        'settings' => 'gear',
        'stock' => 'list-check',
        'staff' => 'people-fill',
        'health' => 'heart-pulse',
        'globe' => 'globe',
    ];

    /**
     * Obtiene la instancia singleton del servicio (para inyección de dependencias).
     *
     * @deprecated Inyectar NavigationService directo via constructor en lugar de llamar este método.
     */
    public static function instance(): self
    {
        return Container::make(self::class);
    }

    /**
     * Obtiene el menú de navegación para un rol.
     *
     * @deprecated Inyectar NavigationService y llamar getMenuForRole() como método de instancia.
     * @return array<string, array<int, array{icon: string, label: string, url: string, badge?: int}>>
     */
    public static function getMenuForRole(string $role): array
    {
        return match ($role) {
            Middleware::ROLE_ADMIN => self::getAdminMenu(),
            Middleware::ROLE_MANAGER => self::getManagerMenu(),
            Middleware::ROLE_KEEPER => self::getKeeperMenu(),
            Middleware::ROLE_SUPERVISOR => self::getSupervisorMenu(),
            Middleware::ROLE_RECEPTION => self::getReceptionMenu(),
            Middleware::ROLE_KITCHEN => self::getKitchenMenu(),
            default => [],
        };
    }

    /**
     * Obtiene el menú completo con badges dinámicos.
     *
     * @deprecated Inyectar NavigationService y llamar getMenuWithBadges() como método de instancia.
     */
    public static function getMenuWithBadges(string $role, array $badges = []): array
    {
        $menu = self::getMenuForRole($role);

        // Aplicar badges a items específicos
        foreach ($menu as $section => &$items) {
            foreach ($items as &$item) {
                $key = self::urlToKey($item['url']);
                if (isset($badges[$key])) {
                    $item['badge'] = $badges[$key];
                }
            }
        }

        return $menu;
    }

    /**
     * Verifica si una URL pertenece a la sección activa.
     *
     * @deprecated Inyectar NavigationService y llamar isActive() como método de instancia.
     */
    public static function isActive(string $itemUrl, string $currentUrl): bool
    {
        // Coincidencia exacta
        if ($itemUrl === $currentUrl) {
            return true;
        }

        // Coincidencia de prefijo (para subpáginas)
        return \str_starts_with($currentUrl, $itemUrl) && $itemUrl !== '/';
    }

    // ─────────────────────────────────────────────────────────────
    // Menús por Rol
    // ─────────────────────────────────────────────────────────────

    private static function getAdminMenu(): array
    {
        return [
            'Sistema' => [
                self::item('speedometer2', 'Dashboard', '/admin/dashboard'),
                self::item('people', 'Usuarios', '/admin/users'),
                self::item('shop', 'Sedes', '/admin/cafes'),
                self::item('box-seam', 'Productos', '/admin/menu'),
                self::item('calendar-check', 'Reservas', '/admin/reservations'),
            ],
            'Seguridad' => [
                self::item('shield-check', 'Roles', '/admin/roles'),
                self::item('key', 'Permisos', '/admin/roles#permisos'),
            ],
            'Monitoreo' => [
                self::item('clock-history', 'Logs', '/admin/logs'),
            ],
            'Configuración' => [
                self::item('gear', 'Ajustes', '/admin/settings'),
            ],
        ];
    }

    private static function getManagerMenu(): array
    {
        return [
            'Gestión' => [
                self::item('dashboard', 'Dashboard', '/manager/dashboard'),
                self::item('stock', 'Catálogo', '/admin/menu'),
                self::item('staff', 'Personal', '/manager/staff'),
                self::item('reports', 'Reportes', '/manager/reports'),
            ],
            'Supervisión' => [
                self::item('reception', 'Recepción', '/ops/reception'),
                self::item('kitchen', 'Cocina', '/ops/kitchen'),
                self::item('animals', 'Animales', '/keeper/dashboard'),
            ],
        ];
    }

    private static function getKeeperMenu(): array
    {
        return [
            'Bienestar Animal' => [
                self::item('animals', 'Estado Diario', '/keeper/dashboard'),
            ],
        ];
    }

    private static function getSupervisorMenu(): array
    {
        return [
            'Supervisión' => [
                self::item('dashboard', 'Dashboard', '/supervisor/dashboard'),
                self::item('reception', 'Recepción', '/ops/reception'),
                self::item('kitchen', 'Cocina', '/ops/kitchen'),
            ],
            'Reporte' => [
                self::item('animals', 'Animales', '/keeper/dashboard'),
            ],
        ];
    }

    private static function getReceptionMenu(): array
    {
        return [
            'Operaciones' => [
                self::item('reception', 'Panel de Recepción', '/ops/reception'),
                self::item('reservas', 'Reservas', '/ops/reservations'),
            ],
        ];
    }

    private static function getKitchenMenu(): array
    {
        return [
            'Cocina' => [
                self::item('kitchen', 'Panel KDS', '/ops/kitchen'),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un item de menú.
     */
    private static function item(string $iconKey, string $label, string $url): array
    {
        return [
            'icon' => self::ICONS[$iconKey] ?? $iconKey,
            'label' => $label,
            'url' => $url,
        ];
    }

    /**
     * Convierte URL a clave para badges.
     */
    private static function urlToKey(string $url): string
    {
        return \trim($url, '/');
    }

    // ─────────────────────────────────────────────────────────────
    // API de Instancia (reemplaza los métodos estáticos @deprecated)
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene el menú de navegación para un rol.
     *
     * @return array<string, array<int, array{icon: string, label: string, url: string, badge?: int}>>
     */
    public function getMenu(string $role): array
    {
        return self::getMenuForRole($role);
    }

    /**
     * Obtiene el menú completo con badges dinámicos.
     */
    public function getMenuBadged(string $role, array $badges = []): array
    {
        return self::getMenuWithBadges($role, $badges);
    }

    /**
     * Verifica si una URL pertenece a la sección activa.
     */
    public function checkIsActive(string $itemUrl, string $currentUrl): bool
    {
        return self::isActive($itemUrl, $currentUrl);
    }
}
