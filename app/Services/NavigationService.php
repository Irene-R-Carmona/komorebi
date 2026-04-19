<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Middleware;

/**
 * Servicio de Navegación del Backoffice
 *
 * Genera menús de navegación según el rol del usuario.
 */
final class NavigationService
{
    private const string URL_OPS_RECEPTION    = '/ops/reception';
    private const string URL_KEEPER_DASHBOARD = '/keeper/dashboard';

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
                self::item('stock', 'Productos', '/manager/products'),
                self::item('staff', 'Personal', '/manager/staff'),
                self::item('reports', 'Reportes', '/manager/reports'),
            ],
            'Supervisión' => [
                self::item('reception', 'Recepción', self::URL_OPS_RECEPTION),
                self::item('kitchen', 'Cocina', '/ops/kitchen'),
                self::item('animals', 'Animales', self::URL_KEEPER_DASHBOARD),
            ],
        ];
    }

    private static function getKeeperMenu(): array
    {
        return [
            'Bienestar Animal' => [
                self::item('dashboard', 'Estado Diario', self::URL_KEEPER_DASHBOARD),
                self::item('animals', 'Animales', '/keeper/animals'),
                self::item('health', 'Chequeos de Salud', '/keeper/health-checks'),
                self::item('incidents', 'Incidentes', '/keeper/incidents'),
            ],
        ];
    }

    private static function getSupervisorMenu(): array
    {
        return [
            'Supervisión' => [
                self::item('dashboard', 'Dashboard', '/supervisor/dashboard'),
                self::item('reception', 'Recepción', self::URL_OPS_RECEPTION),
                self::item('kitchen', 'Cocina', '/ops/kitchen'),
            ],
            'Reporte' => [
                self::item('animals', 'Animales', self::URL_KEEPER_DASHBOARD),
            ],
        ];
    }

    private static function getReceptionMenu(): array
    {
        return [
            'Operaciones' => [
                self::item('reception', 'Panel de Recepción', self::URL_OPS_RECEPTION),
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
     */
    public function getMenuBadged(string $role, array $badges = []): array
    {
        $menu = $this->getMenu($role);

        foreach ($menu as &$items) {
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
     */
    public function checkIsActive(string $itemUrl, string $currentUrl): bool
    {
        if ($itemUrl === $currentUrl) {
            return true;
        }

        return \str_starts_with($currentUrl, $itemUrl) && $itemUrl !== '/';
    }

    /**
     * Verifica si un path pertenece al backoffice.
     */
    public function isBackofficePath(string $path): bool
    {
        $prefixes = ['/admin', '/manager', '/ops', '/keeper'];

        return \array_any($prefixes, static fn ($prefix) => \str_starts_with($path, $prefix));
    }

    /**
     * Sugiere un enlace de retorno basado en el contexto del error.
     *
     * @return array{href: string, label: string}
     */
    public function suggestedLink(string $path, bool $isAuthenticated, string $role): array
    {
        if ($isAuthenticated && $this->isBackofficePath($path)) {
            return match ($role) {
                'admin'   => ['href' => '/admin/dashboard',   'label' => 'Volver al Dashboard'],
                'manager' => ['href' => '/manager/dashboard', 'label' => 'Volver al Dashboard'],
                'keeper'  => ['href' => self::URL_KEEPER_DASHBOARD,  'label' => 'Volver a Bienestar'],
                'staff'   => ['href' => self::URL_OPS_RECEPTION,     'label' => 'Volver a Operaciones'],
                default   => ['href' => '/',                  'label' => 'Volver al inicio'],
            };
        }

        return ['href' => '/', 'label' => 'Volver al inicio'];
    }
}
