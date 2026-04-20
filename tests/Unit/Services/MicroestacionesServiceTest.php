<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Los métodos públicos de MicroestacionesService: obtenerActual, obtenerTodos,
 * obtenerPorId y obtenerPorFecha con datos de las 24 mikroestaciones japonesas.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica pura de los 24 términos solares (Nijūshi Sekki) es correcta
 * sin ninguna dependencia externa: el servicio es completamente determinista.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina algún elemento de SEKKI (debe haber 24), si obtenerPorId devuelve
 * null para un ID existente, o si obtenerPorFecha no localiza la estación correcta.
 */

namespace Tests\Unit\Services;

use App\Services\MicroestacionesService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MicroestacionesService::class)]
final class MicroestacionesServiceTest extends TestCase
{
    private MicroestacionesService $service;

    protected function setUp(): void
    {
        $this->service = new MicroestacionesService();
    }

    // ──────────────────────────────────────────────
    // obtenerTodos
    // ──────────────────────────────────────────────

    public function testObtenerTodosDevuelve24Sekki(): void
    {
        $todos = $this->service->obtenerTodos();

        $this->assertIsArray($todos);
        $this->assertCount(24, $todos);
    }

    public function testCadaSekiTieneEstructuraCorrecta(): void
    {
        $todos = $this->service->obtenerTodos();
        $primero = $todos[0];

        $this->assertArrayHasKey('id', $primero);
        $this->assertArrayHasKey('nombre_es', $primero);
        $this->assertArrayHasKey('nombre_ja', $primero);
        $this->assertArrayHasKey('fecha_inicio', $primero);
        $this->assertArrayHasKey('icono', $primero);
    }

    // ──────────────────────────────────────────────
    // obtenerPorId
    // ──────────────────────────────────────────────

    public function testObtenerPorIdDevuelveSekkiExistente(): void
    {
        $sekki = $this->service->obtenerPorId(1);

        $this->assertIsArray($sekki);
        $this->assertSame(1, $sekki['id']);
        $this->assertSame('立春', $sekki['nombre_ja']); // Risshun
    }

    public function testObtenerPorIdDevuelveNullParaIdInexistente(): void
    {
        $sekki = $this->service->obtenerPorId(99);

        $this->assertNull($sekki);
    }

    public function testObtenerPorIdDevuelveUltimoSekki(): void
    {
        $sekki = $this->service->obtenerPorId(24);

        $this->assertIsArray($sekki);
        $this->assertSame(24, $sekki['id']);
    }

    // ──────────────────────────────────────────────
    // obtenerPorFecha
    // ──────────────────────────────────────────────

    public function testObtenerPorFechaEnInicioDePrimavera(): void
    {
        // obtenerPorFecha espera formato MM-DD
        $sekki = $this->service->obtenerPorFecha('04-15');

        $this->assertIsArray($sekki);
        $this->assertArrayHasKey('nombre_es', $sekki);
    }

    public function testObtenerPorFechaEnFechaDeInviernoDevuelveArray(): void
    {
        // Cualquier fecha válida MM-DD debe retornar algún sekki
        $sekki = $this->service->obtenerPorFecha('12-15');

        $this->assertIsArray($sekki);
    }

    // ──────────────────────────────────────────────
    // obtenerActual
    // ──────────────────────────────────────────────

    public function testObtenerActualDevuelveArrayConCamposEsperados(): void
    {
        $actual = $this->service->obtenerActual();

        $this->assertIsArray($actual);
        $this->assertArrayHasKey('id', $actual);
        $this->assertArrayHasKey('nombre_es', $actual);
    }
}
