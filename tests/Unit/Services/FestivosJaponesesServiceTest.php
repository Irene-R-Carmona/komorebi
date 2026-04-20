<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los métodos de FestivosJaponesesService: obtenerFestivo, esFestivo, permiteReservas
 * y obtenerFestivosDelAnio con fechas concretas de festivos japoneses.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica pura de festivos fijos (New Year, Foundation Day, etc.) funciona
 * correctamente sin ninguna dependencia externa: el servicio es 100 % determinista.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina o renombra un festivo fijo en FESTIVOS_FIJOS, si se invierte la
 * lógica de permiteReservas, o si el formato de retorno de obtenerFestivo cambia.
 */

namespace Tests\Unit\Services;

use App\Services\FestivosJaponesesService;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FestivosJaponesesService::class)]
final class FestivosJaponesesServiceTest extends TestCase
{
    private FestivosJaponesesService $service;

    protected function setUp(): void
    {
        $this->service = new FestivosJaponesesService();
    }

    // ──────────────────────────────────────────────
    // obtenerFestivo
    // ──────────────────────────────────────────────

    public function testObtenerFestivoDevuelveDatosParaAnoNuevo(): void
    {
        $result = $this->service->obtenerFestivo('2025-01-01');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nombre_es', $result);
        $this->assertArrayHasKey('nombre_ja', $result);
        $this->assertSame('Año Nuevo', $result['nombre_es']);
    }

    public function testObtenerFestivoDevuelveNullParaDiaNoFestivo(): void
    {
        // 15 de enero no es festivo fijo japonés
        $result = $this->service->obtenerFestivo('2025-01-15');

        $this->assertNull($result);
    }

    public function testObtenerFestivoFuncionaConObjetosDateTime(): void
    {
        $fecha = new DateTime('2025-02-11');
        $result = $this->service->obtenerFestivo($fecha);

        $this->assertIsArray($result);
        $this->assertSame('Día de la Fundación Nacional', $result['nombre_es']);
    }

    // ──────────────────────────────────────────────
    // esFestivo
    // ──────────────────────────────────────────────

    public function testEsFestivoRetornaTrueParaFestivoConocido(): void
    {
        $this->assertTrue($this->service->esFestivo('2025-01-01'));
    }

    public function testEsFestivoRetornaFalseParaDiaNormal(): void
    {
        $this->assertFalse($this->service->esFestivo('2025-01-10'));
    }

    // ──────────────────────────────────────────────
    // permiteReservas
    // ──────────────────────────────────────────────

    public function testNoPermiteReservasEnAnoNuevo(): void
    {
        // Año Nuevo tiene permite_reservas: false
        $this->assertFalse($this->service->permiteReservas('2025-01-01'));
    }

    public function testPermiteReservasEnFestivosNormales(): void
    {
        // Día de la Fundación Nacional tiene permite_reservas: true
        $this->assertTrue($this->service->permiteReservas('2025-02-11'));
    }

    public function testPermiteReservasEnDiaNormal(): void
    {
        // Un día cualquiera que no es festivo siempre permite reservas
        $this->assertTrue($this->service->permiteReservas('2025-03-15'));
    }

    // ──────────────────────────────────────────────
    // obtenerFestivosDelAnio
    // ──────────────────────────────────────────────

    public function testObtenerFestivosDelAnioDevuelveArrayNoVacio(): void
    {
        $festivos = $this->service->obtenerFestivosDelAnio(2025);

        $this->assertIsArray($festivos);
        $this->assertNotEmpty($festivos);
    }

    public function testObtenerFestivosDelAnioContieneAnoNuevo(): void
    {
        $festivos = $this->service->obtenerFestivosDelAnio(2025);

        $fechas = \array_column($festivos, 'fecha');
        $this->assertContains('2025-01-01', $fechas);
    }
}
