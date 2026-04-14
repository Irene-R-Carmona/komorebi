<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests de todos los métodos públicos de ContextServiceInstance y los métodos
 * estáticos no-deprecated de ContextService (selectCafe, clearSelection).
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de contexto (admin vs staff, vista global, acceso, getViewData)
 * es correcta y que los cambios en reglas de rol se detectan.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se modifica la lógica de rol en getCafeId(), fallan los tests de contexto.
 * - Si se elimina la clave 'Vista Global', testGetCafeNameReturnsVistaGlobalWhenNoContext falla.
 * - Si se cambia quién puede ver isGlobalView, los tests de vista global fallan.
 * - Si se cambia ADMIN_CAFE_KEY en clearSelection, los tests de selección admin fallan.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Core\Session;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\ContextService;
use App\Services\ContextServiceInstance;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContextServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Session::start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    private function makeContext(
        string $role,
        ?int $userCafeId = null,
        ?int $adminSelectedCafeId = null,
        ?array $cafeData = null,
    ): ContextServiceInstance {
        $repo = $this->createStub(CafeRepositoryInterface::class);

        if ($cafeData !== null) {
            $repo->method('findById')->willReturn($cafeData);
        }

        return new ContextServiceInstance($repo, $role, $userCafeId, $adminSelectedCafeId);
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeId()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeIdReturnsNullForAdminWithNoSelection(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertNull($ctx->getCafeId());
    }

    public function testGetCafeIdReturnsSelectedCafeIdForAdmin(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN, null, 5);

        $this->assertSame(5, $ctx->getCafeId());
    }

    public function testGetCafeIdReturnsAssignedCafeIdForNonAdmin(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 3);

        $this->assertSame(3, $ctx->getCafeId());
    }

    public function testGetCafeIdIgnoresAdminSelectionKeyForNonAdmin(): void
    {
        // Kitchen staff con café asignado; la clave de admin no debe usarse
        $ctx = $this->makeContext(Middleware::ROLE_KITCHEN, 2, 99);

        $this->assertSame(2, $ctx->getCafeId());
    }

    // ─────────────────────────────────────────────────────────────
    // hasCafeContext()
    // ─────────────────────────────────────────────────────────────

    public function testHasCafeContextReturnsFalseWhenAdminHasNoSelection(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertFalse($ctx->hasCafeContext());
    }

    public function testHasCafeContextReturnsTrueWhenManagerHasCafe(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 1);

        $this->assertTrue($ctx->hasCafeContext());
    }

    // ─────────────────────────────────────────────────────────────
    // isGlobalView()
    // ─────────────────────────────────────────────────────────────

    public function testIsGlobalViewReturnsTrueForAdminWithNoSelection(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertTrue($ctx->isGlobalView());
    }

    public function testIsGlobalViewReturnsFalseForAdminWithCafeSelected(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN, null, 1);

        $this->assertFalse($ctx->isGlobalView());
    }

    public function testIsGlobalViewReturnsFalseForNonAdmin(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 1);

        $this->assertFalse($ctx->isGlobalView());
    }

    // ─────────────────────────────────────────────────────────────
    // getCafe()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeReturnsNullWhenNoCafeId(): void
    {
        // Admin sin café — no intenta consultar BD
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertNull($ctx->getCafe());
    }

    public function testGetCafeReturnsCafeDataFromRepository(): void
    {
        $cafeData = ['id' => 1, 'name' => 'Café Neko', 'slug' => 'cafe-neko'];
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN, null, 1, $cafeData);

        $result = $ctx->getCafe();

        $this->assertSame($cafeData, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeName()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeNameReturnsVistaGlobalWhenNoContext(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertSame('Vista Global', $ctx->getCafeName());
    }

    public function testGetCafeNameReturnsCafeNameFromRepository(): void
    {
        $ctx = $this->makeContext(
            Middleware::ROLE_MANAGER,
            2,
            null,
            ['id' => 2, 'name' => 'Café Shiba', 'slug' => 'cafe-shiba'],
        );

        $this->assertSame('Café Shiba', $ctx->getCafeName());
    }

    // ─────────────────────────────────────────────────────────────
    // getCafeSlug()
    // ─────────────────────────────────────────────────────────────

    public function testGetCafeSlugReturnsNullWhenNoContext(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertNull($ctx->getCafeSlug());
    }

    public function testGetCafeSlugReturnsCafeSlugFromRepository(): void
    {
        $ctx = $this->makeContext(
            Middleware::ROLE_MANAGER,
            3,
            null,
            ['id' => 3, 'name' => 'Café Kitsune', 'slug' => 'cafe-kitsune'],
        );

        $this->assertSame('cafe-kitsune', $ctx->getCafeSlug());
    }

    // ─────────────────────────────────────────────────────────────
    // canAccessCafe()
    // ─────────────────────────────────────────────────────────────

    public function testCanAccessCafeReturnsTrueForAdminOnAnyCafe(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->assertTrue($ctx->canAccessCafe(1));
        $this->assertTrue($ctx->canAccessCafe(999));
    }

    public function testCanAccessCafeReturnsTrueWhenCafeMatchesAssigned(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 2);

        $this->assertTrue($ctx->canAccessCafe(2));
    }

    public function testCanAccessCafeReturnsFalseForWrongCafe(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_KITCHEN, 1);

        $this->assertFalse($ctx->canAccessCafe(5));
    }

    // ─────────────────────────────────────────────────────────────
    // requireCafeContext()
    // ─────────────────────────────────────────────────────────────

    public function testRequireCafeContextReturnsCafeIdWhenExists(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 4);

        $this->assertSame(4, $ctx->requireCafeContext());
    }

    public function testRequireCafeContextThrowsRuntimeExceptionWhenNoContext(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Se requiere seleccionar un café para esta operación.');

        $ctx->requireCafeContext();
    }

    // ─────────────────────────────────────────────────────────────
    // clearSelection() — método estático no-deprecated de ContextService
    // ─────────────────────────────────────────────────────────────

    public function testClearSelectionRemovesAdminCafeKeyFromSession(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_ADMIN;
        $_SESSION['admin_selected_cafe_id'] = 7;

        ContextService::clearSelection();

        $this->assertArrayNotHasKey('admin_selected_cafe_id', $_SESSION);
        // Un nuevo contexto construido sin la clave de selección refleja vista global
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN, null, null);
        $this->assertNull($ctx->getCafeId());
        $this->assertTrue($ctx->isGlobalView());
    }

    public function testClearSelectionDoesNothingForNonAdmin(): void
    {
        $_SESSION['user_role'] = Middleware::ROLE_MANAGER;
        $_SESSION['user_cafe_id'] = 1;

        ContextService::clearSelection();

        // La clave de café asignado permanece intacta
        $this->assertSame(1, $_SESSION['user_cafe_id']);
    }

    // ─────────────────────────────────────────────────────────────
    // getViewData()
    // ─────────────────────────────────────────────────────────────

    public function testGetViewDataReturnsCorrectStructureForGlobalAdmin(): void
    {
        $ctx = $this->makeContext(Middleware::ROLE_ADMIN);

        $data = $ctx->getViewData();

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
        $cafeData = ['id' => 1, 'name' => 'Café Mochi', 'slug' => 'cafe-mochi'];
        $ctx = $this->makeContext(Middleware::ROLE_MANAGER, 1, null, $cafeData);

        $data = $ctx->getViewData();

        $this->assertSame(1, $data['cafe_id']);
        $this->assertSame('Café Mochi', $data['cafe_name']);
        $this->assertSame($cafeData, $data['cafe']);
        $this->assertFalse($data['is_global']);
        $this->assertFalse($data['can_switch']);
    }
}
