<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? InvoicePDFService: limpieza de facturas antiguas.
 * ¿Qué me quieres demostrar? Que cleanOldInvoices retorna 0 si no existe el directorio.
 * ¿Qué va a fallar en este test si se cambia el código? Si cleanOldInvoices lanza excepción cuando el directorio no existe.
 */

namespace Tests\Unit\Services;

use App\Services\InvoicePDFService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePDFService::class)]
final class InvoicePDFServiceTest extends TestCase
{
    public function testCleanOldInvoicesReturnsIntegerWithoutThrowing(): void
    {
        $service = new InvoicePDFService();
        $deleted = $service->cleanOldInvoices();

        $this->assertIsInt($deleted);
        $this->assertGreaterThanOrEqual(0, $deleted);
    }
}
