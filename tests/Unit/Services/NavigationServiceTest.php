<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? NavigationService: lógica pura de URLs activas y rutas de backoffice.
 * ¿Qué me quieres demostrar? Que checkIsActive y isBackofficePath retornan los valores correctos.
 * ¿Qué va a fallar en este test si se cambia el código? Si la comparación de URLs o el prefijo de backoffice cambia.
 */

namespace Tests\Unit\Services;

use App\Services\Contracts\NavigationServiceInterface;
use App\Services\NavigationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NavigationService::class)]
final class NavigationServiceTest extends TestCase
{
    private NavigationService $service;

    protected function setUp(): void
    {
        $this->service = new NavigationService();
    }

    public function testCheckIsActiveReturnsTrueForExactMatch(): void
    {
        $this->assertTrue($this->service->checkIsActive('/admin/users', '/admin/users'));
    }

    public function testCheckIsActiveReturnsFalseForDifferentUrls(): void
    {
        $this->assertFalse($this->service->checkIsActive('/admin/users', '/admin/cafes'));
    }

    public function testIsBackofficePathReturnsTrueForAdminPath(): void
    {
        $this->assertTrue($this->service->isBackofficePath('/admin/dashboard'));
    }

    public function testIsBackofficePathReturnsFalseForPublicPath(): void
    {
        $this->assertFalse($this->service->isBackofficePath('/menu'));
    }

    public function testGetMenuReturnsSomeItemsForAdminRole(): void
    {
        $menu = $this->service->getMenu('admin');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testCheckIsActiveReturnsTrueForPrefixMatch(): void
    {
        $this->assertTrue($this->service->checkIsActive('/admin/users', '/admin/users/5'));
    }

    public function testCheckIsActiveReturnsFalseWhenItemUrlIsSlash(): void
    {
        $this->assertFalse($this->service->checkIsActive('/', '/about'));
    }

    public function testIsBackofficePathReturnsTrueForManagerPath(): void
    {
        $this->assertTrue($this->service->isBackofficePath('/manager/dashboard'));
    }

    public function testGetMenuReturnsEmptyForUnknownRole(): void
    {
        $menu = $this->service->getMenu('unknown');

        $this->assertSame([], $menu);
    }

    public function testSuggestedLinkReturnsHomeWhenNotAuthenticated(): void
    {
        $link = $this->service->suggestedLink('/admin/users', false, 'admin');

        $this->assertSame('/', $link['href']);
    }

    public function testSuggestedLinkReturnsAdminDashboardForAdmin(): void
    {
        $link = $this->service->suggestedLink('/admin/users', true, 'admin');

        $this->assertStringContainsString('/admin/dashboard', $link['href']);
    }

    public function testGetMenuBadgedAddsBadge(): void
    {
        $menu = $this->service->getMenuBadged('admin', []);

        $this->assertIsArray($menu);
    }

    public function testNavigationServiceImplementsInterface(): void
    {
        $this->assertInstanceOf(NavigationServiceInterface::class, $this->service);
    }

    public function testGetMenuReturnsSomeItemsForManagerRole(): void
    {
        $menu = $this->service->getMenu('manager');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuReturnsSomeItemsForKeeperRole(): void
    {
        $menu = $this->service->getMenu('keeper');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuReturnsSomeItemsForSupervisorRole(): void
    {
        $menu = $this->service->getMenu('supervisor');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuReturnsSomeItemsForReceptionRole(): void
    {
        $menu = $this->service->getMenu('reception');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testGetMenuReturnsSomeItemsForKitchenRole(): void
    {
        $menu = $this->service->getMenu('kitchen');

        $this->assertIsArray($menu);
        $this->assertNotEmpty($menu);
    }

    public function testIsBackofficePathReturnsTrueForOpsPath(): void
    {
        $this->assertTrue($this->service->isBackofficePath('/ops/reception'));
    }

    public function testIsBackofficePathReturnsTrueForKeeperPath(): void
    {
        $this->assertTrue($this->service->isBackofficePath('/keeper/dashboard'));
    }

    public function testSuggestedLinkReturnsManagerDashboardForManager(): void
    {
        $link = $this->service->suggestedLink('/manager/products', true, 'manager');

        $this->assertStringContainsString('/manager/dashboard', $link['href']);
    }

    public function testSuggestedLinkReturnsKeeperDashboardForKeeper(): void
    {
        $link = $this->service->suggestedLink('/keeper/animals', true, 'keeper');

        $this->assertStringContainsString('/keeper/dashboard', $link['href']);
    }

    public function testSuggestedLinkReturnsOpsForStaff(): void
    {
        $link = $this->service->suggestedLink('/ops/reception', true, 'staff');

        $this->assertStringContainsString('/ops/reception', $link['href']);
    }

    public function testSuggestedLinkReturnsHomeForDefaultBackofficeRole(): void
    {
        $link = $this->service->suggestedLink('/admin/something', true, 'other');

        $this->assertSame('/', $link['href']);
    }

    public function testSuggestedLinkReturnsHomeWhenAuthenticatedButNotBackofficePath(): void
    {
        $link = $this->service->suggestedLink('/menu', true, 'admin');

        $this->assertSame('/', $link['href']);
    }

    public function testGetMenuBadgedAttachesBadgeWhenKeyMatches(): void
    {
        $menu = $this->service->getMenuBadged('admin', ['admin/reservations' => 5]);

        $found = false;
        foreach ($menu as $items) {
            foreach ($items as $item) {
                if ($item['url'] === '/admin/reservations' && isset($item['badge']) && $item['badge'] === 5) {
                    $found = true;
                }
            }
        }

        $this->assertTrue($found);
    }
}
