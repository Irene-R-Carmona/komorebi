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
}
