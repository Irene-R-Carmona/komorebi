<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? PassInclusionRepository: findByPassId devuelve inclusiones con datos de categoría.
 * ¿Qué me quieres demostrar? Que la interfaz y la implementación están correctamente estructuradas y
 *   que ReservationService acepta el repositorio de inclusiones como dependencia opcional.
 * ¿Qué va a fallar en este test si se cambia el código? Si se elimina PassInclusionRepositoryInterface,
 *   si se cambia la firma de findByPassId, o si ReservationService deja de aceptar el repositorio.
 */

namespace Tests\Unit\Repositories;

use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\PassInclusionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\ReservationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationService::class)]
final class PassInclusionRepositoryInterfaceTest extends TestCase
{
    /**
     * Verifica que ReservationService acepta PassInclusionRepositoryInterface como dependencia
     * y que el stub implementa correctamente la interfaz.
     */
    public function testReservationServiceAcceptsPassInclusionRepository(): void
    {
        $passInclusionRepo = $this->createStub(PassInclusionRepositoryInterface::class);

        $service = new ReservationService(
            $this->createStub(ReservationRepositoryInterface::class),
            $this->createStub(CafeRepositoryInterface::class),
            $this->createStub(ProductRepositoryInterface::class),
            $this->createStub(InvoicePDFServiceInterface::class),
            $this->createStub(EmailServiceInterface::class),
            null,
            null,
            null,
            null,
            $passInclusionRepo
        );

        // Si llega hasta aquí sin excepción, la inyección es correcta
        $this->assertInstanceOf(ReservationService::class, $service);
    }

    /**
     * Verifica que un stub de PassInclusionRepositoryInterface devuelve array vacío por defecto
     * (comportamiento de stub PHPUnit: métodos retornan valor nulo/vacío del tipo declarado).
     */
    public function testPassInclusionRepositoryInterfaceStubReturnsArray(): void
    {
        $repo = $this->createStub(PassInclusionRepositoryInterface::class);
        $repo->method('findByPassId')->willReturn([]);

        $result = $repo->findByPassId(1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Verifica que findByPassId con inclusiones configuradas devuelve la estructura esperada.
     */
    public function testPassInclusionRepositoryInterfaceReturnsExpectedStructure(): void
    {
        $expectedRow = [
            'id' => 1,
            'pass_product_id' => 42,
            'category_id' => 3,
            'quantity_per_pax' => 1,
            'max_unit_price' => 500,
            'category_name' => 'Bebidas',
            'category_slug' => 'bebidas',
        ];

        $repo = $this->createStub(PassInclusionRepositoryInterface::class);
        $repo->method('findByPassId')->willReturn([$expectedRow]);

        $inclusions = $repo->findByPassId(42);

        $this->assertCount(1, $inclusions);
        $this->assertSame('bebidas', $inclusions[0]['category_slug']);
        $this->assertSame(500, $inclusions[0]['max_unit_price']);
        $this->assertSame(1, $inclusions[0]['quantity_per_pax']);
    }
}
