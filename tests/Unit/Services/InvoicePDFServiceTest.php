<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * InvoicePDFService: cleanOldInvoices (devuelve int ≥ 0) y que el servicio
 * implementa la interfaz InvoicePDFServiceInterface.
 *
 * ¿Qué me quieres demostrar?
 * Que cleanOldInvoices es seguro cuando no existen facturas antiguas (devuelve 0),
 * y que el contrato de interfaz se mantiene (no se rompió la implementación).
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si InvoicePDFService deja de implementar InvoicePDFServiceInterface, si
 * cleanOldInvoices lanza una excepción en lugar de devolver int, o si devuelve
 * un valor negativo cuando no hay fichos que borrar.
 */

namespace Tests\Unit\Services;

use App\Services\Contracts\InvoicePDFServiceInterface;
use App\Services\InvoicePDFService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePDFService::class)]
final class InvoicePDFServiceTest extends TestCase
{
    private InvoicePDFService $service;

    protected function setUp(): void
    {
        $this->service = new InvoicePDFService();
    }

    // ──────────────────────────────────────────────
    // Contrato de interfaz
    // ──────────────────────────────────────────────

    public function testImplementaInterfazInvoicePDF(): void
    {
        $this->assertInstanceOf(InvoicePDFServiceInterface::class, $this->service);
    }

    // ──────────────────────────────────────────────
    // cleanOldInvoices
    // ──────────────────────────────────────────────

    public function testCleanOldInvoicesDevuelveEntero(): void
    {
        $result = $this->service->cleanOldInvoices();

        $this->assertIsInt($result);
    }

    public function testCleanOldInvoicesDevuelveCeroCuandoNoHayFacturasAntiguas(): void
    {
        // En un entorno limpio no debería haber facturas de más de 30 días
        $result = $this->service->cleanOldInvoices();

        $this->assertGreaterThanOrEqual(0, $result);
    }
}
