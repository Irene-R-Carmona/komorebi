<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? ContextServiceInstance: lógica pura de contexto de café por rol.
 * ¿Qué me quieres demostrar? Que getCafeId, hasCafeContext e isGlobalView se comportan según el rol.
 * ¿Qué va a fallar en este test si se cambia el código? Si la lógica de selección de café por rol se modifica.
 */

namespace Tests\Unit\Services;

use App\Core\Middleware;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Services\ContextServiceInstance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContextServiceInstance::class)]
final class ContextServiceInstanceTest extends TestCase
{
    public function testAdminWithNoSelectionIsGlobalView(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $service  = new ContextServiceInstance($cafeRepo, Middleware::ROLE_ADMIN, null, null);

        $this->assertTrue($service->isGlobalView());
        $this->assertFalse($service->hasCafeContext());
        $this->assertNull($service->getCafeId());
    }

    public function testAdminWithSelectedCafeHasContext(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $service  = new ContextServiceInstance($cafeRepo, Middleware::ROLE_ADMIN, null, 5);

        $this->assertFalse($service->isGlobalView());
        $this->assertTrue($service->hasCafeContext());
        $this->assertSame(5, $service->getCafeId());
    }

    public function testManagerWithAssignedCafeHasContext(): void
    {
        $cafeRepo = $this->createStub(CafeRepositoryInterface::class);
        $service  = new ContextServiceInstance($cafeRepo, Middleware::ROLE_MANAGER, 3, null);

        $this->assertFalse($service->isGlobalView());
        $this->assertTrue($service->hasCafeContext());
        $this->assertSame(3, $service->getCafeId());
    }
}
