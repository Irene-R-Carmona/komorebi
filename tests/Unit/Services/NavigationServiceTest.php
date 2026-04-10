<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los métodos estáticos de NavigationService: getMenuForRole, getMenuWithBadges
 * e isActive, que generan menús de navegación según el rol del usuario.
 *
 * ¿Qué me quieres demostrar?
 * Que el enrutamiento de menú es correcto para cada rol, que isActive funciona
 * con coincidencia exacta y de prefijo, y que los badges se aplican correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina un rol del switch/match de getMenuForRole, si se cambia la URL
 * de algún ítem de menú, o si se modifica la lógica de prefijo en isActive.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Services\NavigationService;
use PHPUnit\Framework\TestCase;

final class NavigationServiceTest extends TestCase
{
    // ──────────────────────────────────────────────
    // getMenuForRole
    // ──────────────────────────────────────────────

    public function testGetMenuForRoleAdminDevuelveSecciones(): void
    {
        $menu = NavigationService::getMenuForRole(Middleware::ROLE_ADMIN);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
        $this->assertArrayHasKey('Sistema', $menu);
    }

    public function testGetMenuForRoleManagerDevuelveMenuNoVacio(): void
    {
        $menu = NavigationService::getMenuForRole(Middleware::ROLE_MANAGER);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuForRoleKeeperDevuelveMenuAnimal(): void
    {
        $menu = NavigationService::getMenuForRole(Middleware::ROLE_KEEPER);

        $this->assertIsArray($menu);
        $this->assertArrayHasKey('Bienestar Animal', $menu);
    }

    public function testGetMenuForRoleDesconocidoDevuelveArrayVacio(): void
    {
        $menu = NavigationService::getMenuForRole('rol_inexistente');

        $this->assertIsArray($menu);
        $this->assertEmpty($menu);
    }

    public function testGetMenuForRoleAdminContieneItemConUrl(): void
    {
        $menu = NavigationService::getMenuForRole(Middleware::ROLE_ADMIN);

        $items = $menu['Sistema'];
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);

        $firstItem = $items[0];
        $this->assertArrayHasKey('url', $firstItem);
        $this->assertArrayHasKey('label', $firstItem);
        $this->assertArrayHasKey('icon', $firstItem);
    }

    // ──────────────────────────────────────────────
    // isActive
    // ──────────────────────────────────────────────

    public function testIsActiveConIncidenciaExacta(): void
    {
        $this->assertTrue(NavigationService::isActive('/admin/dashboard', '/admin/dashboard'));
    }

    public function testIsActiveConPrefijoCoincidente(): void
    {
        // /admin/users debería ser "activo" cuando la URL actual es /admin/users/1
        $this->assertTrue(NavigationService::isActive('/admin/users', '/admin/users/1'));
    }

    public function testIsActiveRetornaFalseCuandoNoCoincide(): void
    {
        $this->assertFalse(NavigationService::isActive('/admin/users', '/admin/settings'));
    }

    public function testIsActiveConSlashRaizNoEsPrefijoUniversal(): void
    {
        // La raíz '/' no debe marcar como activo cualquier página
        $this->assertFalse(NavigationService::isActive('/', '/admin/dashboard'));
    }

    // ──────────────────────────────────────────────
    // getMenuWithBadges
    // ──────────────────────────────────────────────

    public function testGetMenuWithBadgesAplicaBadgeAItemEspecifico(): void
    {
        $badges = ['ops/reception' => 3];
        $menu = NavigationService::getMenuWithBadges(Middleware::ROLE_SUPERVISOR, $badges);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);

        // Buscar el ítem de recepción y verificar que tiene el badge
        $badgeFound = false;
        foreach ($menu as $items) {
            foreach ($items as $item) {
                if (str_contains($item['url'], '/ops/reception') && isset($item['badge'])) {
                    $this->assertSame(3, $item['badge']);
                    $badgeFound = true;
                }
            }
        }

        $this->assertTrue($badgeFound, 'Badge no encontrado en ítem de recepción');
    }

    public function testGetMenuWithBadgesSinBadgesNoAgregaPropiedadBadge(): void
    {
        $menu = NavigationService::getMenuWithBadges(Middleware::ROLE_ADMIN, []);

        foreach ($menu as $items) {
            foreach ($items as $item) {
                $this->assertArrayNotHasKey('badge', $item);
            }
        }
    }
}
