<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AdminService::calculateTrend (private) vía ReflectionMethod y el método
 * getDatabase() que expone la instancia PDO del servicio.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de cálculo de tendencias funciona correctamente en todos los
 * casos límite: sin datos previos, incremento, decremento y sin cambio.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cambia la fórmula de calculateTrend, si se añade/quita el prefijo '+',
 * o si getDatabase() deja de devolver la instancia PDO.
 */

namespace Tests\Unit\Services;

use App\Services\AdminService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AdminServiceTest extends TestCase
{
    private AdminService $service;

    protected function setUp(): void
    {
        $this->service = new AdminService();
    }

    // ──────────────────────────────────────────────
    // getDatabase
    // ──────────────────────────────────────────────

    public function testGetDatabaseRetornaInstanciaPDO(): void
    {
        $this->assertInstanceOf(PDO::class, $this->service->getDatabase());
    }

    // ──────────────────────────────────────────────
    // calculateTrend (private) via reflection
    // ──────────────────────────────────────────────

    private function calcularTrend(int $current, int $previous): string
    {
        $method = new ReflectionMethod($this->service, 'calculateTrend');
        return $method->invoke($this->service, $current, $previous);
    }

    public function testCalculateTrendSinDatosPreviosConCrecimientoDevuelveMas100(): void
    {
        $result = $this->calcularTrend(5, 0);

        $this->assertSame('+100%', $result);
    }

    public function testCalculateTrendSinDatosPreviosSinActualDevuelveCero(): void
    {
        $result = $this->calcularTrend(0, 0);

        $this->assertSame('0%', $result);
    }

    public function testCalculateTrendConDuplicacionDevuelveMas100(): void
    {
        $result = $this->calcularTrend(10, 5);

        $this->assertSame('+100%', $result);
    }

    public function testCalculateTrendConDecrecimientoDevuelveNegativo(): void
    {
        $result = $this->calcularTrend(5, 10);

        $this->assertStringStartsWith('-', $result);
        $this->assertStringEndsWith('%', $result);
    }

    public function testCalculateTrendSinCambioDevuelveCero(): void
    {
        $result = $this->calcularTrend(7, 7);

        $this->assertSame('+0%', $result);
    }

    public function testCalculateTrendConMejoraParecialDevuelveMasX(): void
    {
        // +50%: de 4 a 6
        $result = $this->calcularTrend(6, 4);

        $this->assertStringStartsWith('+', $result);
        $this->assertStringContainsString('50', $result);
    }
}
