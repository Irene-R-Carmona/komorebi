<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de todos los métodos públicos de ContextService.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de contexto (admin vs staff, vista global, caché, acceso)
 * es correcta y que los cambios en reglas de rol o claves de sesión se detectan.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se cambia ADMIN_CAFE_KEY, los tests de selección de admin fallan.
 * - Si se modifica la lógica de rol en getCafeId(), fallan los tests de contexto.
 * - Si se elimina la clave 'Vista Global', testGetCafeNameReturnsVistaGlobalWhenNoContext falla.
 * - Si se cambia quién puede ver isGlobalView, los tests de vista global fallan.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Core\Session;
use App\Services\ContextService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class ContextServiceTest extends TestCase
{
    private ReflectionProperty $cacheProperty;

    protected function setUp(): void
    {
        Session::start();
        $_SESSION = [];
        ContextService::clearCache();

        $this->cacheProperty = new ReflectionProperty(ContextService::class, 'cafeCache');
        $this->cacheProperty->setAccessible(true);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        ContextService::clearCache();
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    private function setCafeCache(?array $data): void
    {
        $this->cacheProperty->setValue(null, $data);
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeId()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeIdReturnsNullForAdminWithNoSelection(): void
    {
        // ARRANGE: Admin sin café seleccionado
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        // ACT + ASSERT
        $this->assertNull(ContextService::getCafeId());
    }

    public function testGetCafeIdReturnsSelectedCafeIdForAdmin(): void
    {
        // ARRANGE: Admin con café seleccionado vía sesión
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;
        $_SESSION['admin_selected_cafe_id'] = 5;

        // ACT + ASSERT
        $this->assertSame(5, ContextService::getCafeId());
    }

    public function testGetCafeIdReturnsAssignedCafeIdForNonAdmin(): void
    {
        // ARRANGE: Manager con café asignado por el sistema
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 3;

        // ACT + ASSERT
        $this->assertSame(3, ContextService::getCafeId());
    }

    public function testGetCafeIdIgnoresAdminSelectionKeyForNonAdmin(): void
    {
        // ARRANGE: Kitchen staff con café asignado; la clave de admin no debe usarse
        $_SESSION['user_role'] = Middleware::ROLE_KITCHEN;
        $_SESSION['user_cafe_id'] = 2;
        $_SESSION['admin_selected_cafe_id'] = 99;

        // ACT + ASSERT
        $this->assertSame(2, ContextService::getCafeId());
    }

    // ─────────────────────────────────────────────────────────────
    // hasCafeContext()
    // ─────────────────────────────────────────────────────────────

    public function testHasCafeContextReturnsFalseWhenAdminHasNoSelection(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertFalse(ContextService::hasCafeContext());
    }

    public function testHasCafeContextReturnsTrueWhenManagerHasCafe(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 1;

        $this->assertTrue(ContextService::hasCafeContext());
    }

    // ─────────────────────────────────────────────────────────────
    // isGlobalView()
    // ─────────────────────────────────────────────────────────────

    public function testIsGlobalViewReturnsTrueForAdminWithNoSelection(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertTrue(ContextService::isGlobalView());
    }

    public function testIsGlobalViewReturnsFalseForAdminWithCafeSelected(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;
        $_SESSION['admin_selected_cafe_id'] = 1;

        $this->assertFalse(ContextService::isGlobalView());
    }

    public function testIsGlobalViewReturnsFalseForNonAdmin(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 1;

        $this->assertFalse(ContextService::isGlobalView());
    }

    // ─────────────────────────────────────────────────────────────
    // getCafe()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeReturnsNullWhenNoCafeId(): void
    {
        // ARRANGE: Admin sin café — no intenta consultar BD
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertNull(ContextService::getCafe());
    }

    public function testGetCafeReturnsCachedValueWhenCacheMatches(): void
    {
        // ARRANGE: Admin con café ID 1; cache pre-poblada con ese mismo ID
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;
        $_SESSION['admin_selected_cafe_id'] = 1;
        $cached = ['id' => 1, 'name' => 'Café Neko', 'slug' => 'cafe-neko'];
        $this->setCafeCache($cached);

        // ACT: Debe devolver el cache sin tocar la BD
        $result = ContextService::getCafe();

        $this->assertSame($cached, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeName()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeNameReturnsVistaGlobalWhenNoContext(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertSame('Vista Global', ContextService::getCafeName());
    }

    public function testGetCafeNameReturnsCafeNameFromCache(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 2;
        $this->setCafeCache(['id' => 2, 'name' => 'Café Shiba', 'slug' => 'cafe-shiba']);

        $this->assertSame('Café Shiba', ContextService::getCafeName());
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeSlug()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeSlugReturnsNullWhenNoContext(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertNull(ContextService::getCafeSlug());
    }

    public function testGetCafeSlugReturnsCachedSlug(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 3;
        $this->setCafeCache(['id' => 3, 'name' => 'Café Kitsune', 'slug' => 'cafe-kitsune']);

        $this->assertSame('cafe-kitsune', ContextService::getCafeSlug());
    }

    // ─────────────────────────────────────────────────────────────
    // canAccessCafe()
    // ─────────────────────────────────────────────────────────────

    public function testCanAccessCafeReturnsTrueForAdminOnAnyCafe(): void
    {
        // ARRANGE: Admin siempre puede acceder
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->assertTrue(ContextService::canAccessCafe(1));
        $this->assertTrue(ContextService::canAccessCafe(999));
    }

    public function testCanAccessCafeReturnsTrueWhenCafeMatchesAssigned(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 2;

        $this->assertTrue(ContextService::canAccessCafe(2));
    }

    public function testCanAccessCafeReturnsFalseForWrongCafe(): void
    {
        // ARRANGE: Kitchen staff con café 1 intenta acceder al café 5
        $_SESSION['user_role'] = Middleware::ROLE_KITCHEN;
        $_SESSION['user_cafe_id'] = 1;

        $this->assertFalse(ContextService::canAccessCafe(5));
    }

    // ─────────────────────────────────────────────────────────────
    // requireCafeContext()
    // ─────────────────────────────────────────────────────────────

    public function testRequireCafeContextReturnsCafeIdWhenExists(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 4;

        $this->assertSame(4, ContextService::requireCafeContext());
    }

    public function testRequireCafeContextThrowsRuntimeExceptionWhenNoContext(): void
    {
        // ARRANGE: Admin sin selección → sin contexto de café
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Se requiere seleccionar un café para esta operación.');

        ContextService::requireCafeContext();
    }

    // ─────────────────────────────────────────────────────────────
    // clearSelection()
    // ─────────────────────────────────────────────────────────────

    public function testClearSelectionRemovesAdminCafeKeyAndResetsCache(): void
    {
        // ARRANGE: Admin con café seleccionado y cache poblada
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;
        $_SESSION['admin_selected_cafe_id'] = 7;
        $this->setCafeCache(['id' => 7, 'name' => 'Café Test', 'slug' => 'cafe-test']);

        // ACT
        ContextService::clearSelection();

        // ASSERT: Sin contexto, vista global activa, cache vacía
        $this->assertNull(ContextService::getCafeId());
        $this->assertTrue(ContextService::isGlobalView());
        $this->assertNull($this->cacheProperty->getValue(null));
    }

    public function testClearSelectionDoesNothingForNonAdmin(): void
    {
        // ARRANGE: Manager con café asignado
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 1;

        // ACT: clearSelection no debe afectar a roles no-admin
        ContextService::clearSelection();

        // ASSERT: El café sigue asignado
        $this->assertSame(1, ContextService::getCafeId());
    }

    // ─────────────────────────────────────────────────────────────
    // getViewData()
    // ─────────────────────────────────────────────────────────────

    public function testGetViewDataReturnsCorrectStructureForGlobalAdmin(): void
    {
        // ARRANGE: Admin sin selección
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;

        // ACT
        $data = ContextService::getViewData();

        // ASSERT: Todas las claves presentes y valores de vista global
        $this->assertArrayHasKey('cafe_id', $data);
        $this->assertArrayHasKey('cafe_name', $data);
        $this->assertArrayHasKey('cafe', $data);
        $this->assertArrayHasKey('is_global', $data);
        $this->assertArrayHasKey('can_switch', $data);
        $this->assertNull($data['cafe_id']);
        $this->assertSame('Vista Global', $data['cafe_name']);
        $this->assertNull($data['cafe']);
        $this->assertTrue($data['is_global']);
        $this->assertTrue($data['can_switch']);
    }

    public function testGetViewDataReturnsCorrectStructureForManagerWithCafe(): void
    {
        // ARRANGE: Manager con café asignado y cacheado
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 1;
        $cafeData = ['id' => 1, 'name' => 'Café Mochi', 'slug' => 'cafe-mochi'];
        $this->setCafeCache($cafeData);

        // ACT
        $data = ContextService::getViewData();

        // ASSERT: Contexto de café activo, sin permisos de cambio
        $this->assertSame(1, $data['cafe_id']);
        $this->assertSame('Café Mochi', $data['cafe_name']);
        $this->assertSame($cafeData, $data['cafe']);
        $this->assertFalse($data['is_global']);
        $this->assertFalse($data['can_switch']);
    }
}
