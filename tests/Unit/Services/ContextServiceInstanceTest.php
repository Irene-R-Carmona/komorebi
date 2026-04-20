<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Verifica el comportamiento de ContextService como clase inyectable.
 *
 * ¿Qué me quieres demostrar?
 * Que getCafeId() retorna el cafe_id del usuario para staff,
 * el seleccionado en sesión para admin, o null para vista global.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se cambia la lógica de resolución de café por rol.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\ContextServiceInstance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ContextServiceInstance::class)]
final class ContextServiceInstanceTest extends TestCase
{
    private function makeService(
        string $role,
        ?int $userCafeId,
        ?int $sessionSelectedCafeId,
        ?array $cafeData = null
    ): ContextServiceInstance {
        $cafeRepo = $this->createMock(CafeRepositoryInterface::class);
        if ($cafeData !== null) {
            $cafeRepo->method('findById')->willReturn($cafeData);
        }

        return new ContextServiceInstance($cafeRepo, $role, $userCafeId, $sessionSelectedCafeId);
    }

    public function test_staff_gets_their_assigned_cafe_id(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertSame(3, $service->getCafeId());
    }

    public function test_admin_with_no_selection_gets_null(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertNull($service->getCafeId());
    }

    public function test_admin_with_selection_gets_selected_cafe(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, 7);
        $this->assertSame(7, $service->getCafeId());
    }

    public function test_is_global_view_true_for_admin_without_selection(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertTrue($service->isGlobalView());
    }

    public function test_is_global_view_false_for_staff(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertFalse($service->isGlobalView());
    }

    public function test_has_cafe_context_true_when_cafe_id_set(): void
    {
        $service = $this->makeService(Middleware::ROLE_RECEPTION, 5, null);
        $this->assertTrue($service->hasCafeContext());
    }

    public function test_has_cafe_context_false_for_global_admin(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertFalse($service->hasCafeContext());
    }

    public function test_can_access_cafe_true_for_admin(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertTrue($service->canAccessCafe(99));
    }

    public function test_can_access_cafe_only_their_own_for_staff(): void
    {
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null);
        $this->assertTrue($service->canAccessCafe(3));
        $this->assertFalse($service->canAccessCafe(5));
    }

    public function test_require_cafe_context_returns_cafe_id(): void
    {
        $service = $this->makeService(Middleware::ROLE_MANAGER, 2, null);
        $this->assertSame(2, $service->requireCafeContext());
    }

    public function test_require_cafe_context_throws_when_no_context(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->expectException(RuntimeException::class);
        $service->requireCafeContext();
    }

    public function test_get_view_data_returns_expected_keys(): void
    {
        $service = $this->makeService(
            Middleware::ROLE_MANAGER,
            2,
            null,
            ['id' => 2, 'name' => 'Café Luna', 'slug' => 'luna']
        );
        $data = $service->getViewData();
        $this->assertArrayHasKey('cafe_id', $data);
        $this->assertArrayHasKey('cafe_name', $data);
        $this->assertArrayHasKey('cafe', $data);
        $this->assertArrayHasKey('is_global', $data);
        $this->assertArrayHasKey('can_switch', $data);
        $this->assertSame(2, $data['cafe_id']);
        $this->assertSame('Café Luna', $data['cafe_name']);
        $this->assertFalse($data['can_switch']);
    }

    public function test_get_view_data_can_switch_true_for_admin(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $data = $service->getViewData();
        $this->assertTrue($data['can_switch']);
        $this->assertTrue($data['is_global']);
        $this->assertSame('Vista Global', $data['cafe_name']);
    }

    public function test_get_cafe_returns_null_when_no_cafe_id(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertNull($service->getCafe());
    }

    public function test_get_cafe_slug_returns_null_when_no_cafe(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertNull($service->getCafeSlug());
    }

    public function test_get_cafe_name_returns_vista_global_when_no_cafe(): void
    {
        $service = $this->makeService(Middleware::ROLE_ADMIN, null, null);
        $this->assertSame('Vista Global', $service->getCafeName());
    }

    public function test_get_cafe_returns_cafe_data_for_staff(): void
    {
        $cafeData = ['id' => 3, 'name' => 'Café Bruma', 'slug' => 'bruma'];
        $service = $this->makeService(Middleware::ROLE_KEEPER, 3, null, $cafeData);
        $this->assertSame($cafeData, $service->getCafe());
        $this->assertSame('bruma', $service->getCafeSlug());
    }
}
