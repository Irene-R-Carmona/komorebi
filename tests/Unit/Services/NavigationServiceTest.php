<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los métodos de instancia de NavigationService: getMenu, getMenuBadged
 * y checkIsActive, que generan menús de navegación según el rol del usuario.
 *
 * ¿Qué me quieres demostrar?
 * Que el enrutamiento de menú es correcto para cada rol, que isActive funciona
 * con coincidencia exacta y de prefijo, y que los badges se aplican correctamente.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina un rol del switch/match de getMenu, si se cambia la URL
 * de algún ítem de menú, o si se modifica la lógica de prefijo en checkIsActive.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Services\NavigationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NavigationService::class)]
final class NavigationServiceTest extends TestCase
{
    // ──────────────────────────────────────────────
    // getMenu
    // ──────────────────────────────────────────────

    public function testGetMenuAdminDevuelveSecciones(): void
    {
        $menu = new NavigationService()->getMenu(Middleware::ROLE_ADMIN);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
        $this->assertArrayHasKey('Sistema', $menu);
    }

    public function testGetMenuManagerDevuelveMenuNoVacio(): void
    {
        $menu = new NavigationService()->getMenu(Middleware::ROLE_MANAGER);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuKeeperDevuelveMenuAnimal(): void
    {
        $menu = new NavigationService()->getMenu(Middleware::ROLE_KEEPER);

        $this->assertIsArray($menu);
        $this->assertArrayHasKey('Bienestar Animal', $menu);
    }

    public function testGetMenuDesconocidoDevuelveArrayVacio(): void
    {
        $menu = new NavigationService()->getMenu('rol_inexistente');

        $this->assertIsArray($menu);
        $this->assertEmpty($menu);
    }

    public function testGetMenuAdminContieneItemConUrl(): void
    {
        $menu = new NavigationService()->getMenu(Middleware::ROLE_ADMIN);

        $items = $menu['Sistema'];
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);

        $firstItem = $items[0];
        $this->assertArrayHasKey('url', $firstItem);
        $this->assertArrayHasKey('label', $firstItem);
        $this->assertArrayHasKey('icon', $firstItem);
    }

    // ──────────────────────────────────────────────
    // checkIsActive
    // ──────────────────────────────────────────────

    public function testCheckIsActiveConIncidenciaExacta(): void
    {
        $this->assertTrue(new NavigationService()->checkIsActive('/admin/dashboard', '/admin/dashboard'));
    }

    public function testCheckIsActiveConPrefijoCoincidente(): void
    {
        // /admin/users debería ser "activo" cuando la URL actual es /admin/users/1
        $this->assertTrue(new NavigationService()->checkIsActive('/admin/users', '/admin/users/1'));
    }

    public function testCheckIsActiveRetornaFalseCuandoNoCoincide(): void
    {
        $this->assertFalse(new NavigationService()->checkIsActive('/admin/users', '/admin/settings'));
    }

    public function testCheckIsActiveConSlashRaizNoEsPrefijoUniversal(): void
    {
        // La raíz '/' no debe marcar como activo cualquier página
        $this->assertFalse(new NavigationService()->checkIsActive('/', '/admin/dashboard'));
    }

    // ──────────────────────────────────────────────
    // getMenuBadged
    // ──────────────────────────────────────────────

    public function testGetMenuBadgedAplicaBadgeAItemEspecifico(): void
    {
        $badges = ['ops/reception' => 3];
        $menu = new NavigationService()->getMenuBadged(Middleware::ROLE_SUPERVISOR, $badges);

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);

        // Buscar el ítem de recepción y verificar que tiene el badge
        $badgeFound = false;
        foreach ($menu as $items) {
            foreach ($items as $item) {
                if (\str_contains($item['url'], '/ops/reception') && isset($item['badge'])) {
                    $this->assertSame(3, $item['badge']);
                    $badgeFound = true;
                }
            }
        }

        $this->assertTrue($badgeFound, 'Badge no encontrado en ítem de recepción');
    }

    public function testGetMenuBadgedSinBadgesNoAgregaPropiedadBadge(): void
    {
        $menu = new NavigationService()->getMenuBadged(Middleware::ROLE_ADMIN, []);

        foreach ($menu as $items) {
            foreach ($items as $item) {
                $this->assertArrayNotHasKey('badge', $item);
            }
        }
    }
}
