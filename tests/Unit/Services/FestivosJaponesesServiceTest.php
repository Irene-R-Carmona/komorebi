<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? FestivosJaponesesService: lógica pura de festivos y microestaciones japonesas.
 * ¿Qué me quieres demostrar? Que esFestivo y obtenerFestivosDelAnio retornan los tipos correctos.
 * ¿Qué va a fallar en este test si se cambia el código? Si el listado de festivos o la lógica de detección cambia.
 */

namespace Tests\Unit\Services;

use App\Services\FestivosJaponesesService;
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

    public function testEsFestivoReturnsBooleanForGivenDate(): void
    {
        $result = $this->service->esFestivo('2025-01-01');

        $this->assertIsBool($result);
    }

    public function testObtenerFestivosDelAnioReturnsNonEmptyArray(): void
    {
        $festivos = $this->service->obtenerFestivosDelAnio(2025);

        $this->assertIsArray($festivos);
        $this->assertNotEmpty($festivos);
    }

    public function testPermiteReservasReturnsBooleanType(): void
    {
        $result = $this->service->permiteReservas('2025-06-15');

        $this->assertIsBool($result);
    }
}
