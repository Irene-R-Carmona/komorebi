<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? RecentlyViewedService: lógica pura de límite de ítems.
 * ¿Qué me quieres demostrar? Que getMaxItems retorna un entero positivo.
 * ¿Qué va a fallar en este test si se cambia el código? Si MAX_ITEMS cambia o getMaxItems deja de funcionar.
 */

namespace Tests\Unit\Services;

use App\Services\RecentlyViewedService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecentlyViewedService::class)]
final class RecentlyViewedServiceTest extends TestCase
{
    public function testGetMaxItemsReturnsPositiveInteger(): void
    {
        $service = new RecentlyViewedService();

        $max = $service->getMaxItems();

        $this->assertIsInt($max);
        $this->assertGreaterThan(0, $max);
    }
}
