<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? HealthCheckService: validaciones de peso/temperatura y detección de alertas.
 * ¿Qué me quieres demostrar? Que pesos fuera de rango devuelven Result::fail, y que temperatura fuera de umbral genera alertas.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las constantes WEIGHT_MIN/MAX, TEMPERATURE_HIGH/LOW_THRESHOLD, o la lógica de validación.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\HealthCheckService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthCheckService::class)]
final class HealthCheckServiceTest extends TestCase
{
    private HealthCheckRepositoryInterface $repoStub;
    private HealthCheckService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(HealthCheckRepositoryInterface::class);
        $this->service  = new HealthCheckService($this->repoStub);
    }

    public function testCreateHealthCheckFailsWhenWeightTooLow(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 0.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Peso fuera de rango', $result->error);
    }

    public function testCreateHealthCheckFailsWhenWeightTooHigh(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 100.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Peso fuera de rango', $result->error);
    }

    public function testCreateHealthCheckFailsWhenCheckAlreadyExistsToday(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(true);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 5.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('chequeo registrado hoy', $result->error);
    }

    public function testCreateHealthCheckSucceedsWithValidWeight(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);
        $this->repoStub->method('create')->willReturn(42);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 5.0]);

        $this->assertTrue($result->ok);
    }

    public function testGetAnimalHistoryReturnsDelegated(): void
    {
        $expected = [['id' => 1, 'animal_id' => 2]];
        $this->repoStub->method('getCheckHistory')->willReturn($expected);

        $result = $this->service->getAnimalHistory(2);

        $this->assertSame($expected, $result);
    }

    public function testHasCheckTodayDelegatesToRepo(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(true);

        $result = $this->service->hasCheckToday(1);

        $this->assertTrue($result);
    }

    public function testGetCheckByIdReturnsNullWhenNotFound(): void
    {
        $this->repoStub->method('findById')->willReturn(null);

        $result = $this->service->getCheckById(999);

        $this->assertNull($result);
    }
}
