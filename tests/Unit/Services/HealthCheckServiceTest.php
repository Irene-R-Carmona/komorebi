<?php

declare(strict_types=1);

/**
 * Tests de HealthCheckService
 *
 * ¿Qué pruebas aquí?
 * - Creación de chequeos: validaciones de métricas y restricción de duplicado diario
 * - Detección automática de alertas (temperatura, apetito, energía, movilidad, ojos, pelaje)
 * - Delegación correcta al repositorio para: hasCheckToday, getKeeperStatistics,
 *   getTodayDashboard, getAnimalHistory, getActiveAlerts
 * - Decodificación de alertas JSON al leer historial
 *
 * ¿Qué me quieres demostrar?
 * - Que las reglas de negocio de validación son independientes de la BD (inyección de repo)
 * - Que detectAlerts() identifica todos los umbrales clínicos definidos en el servicio
 * - Que los métodos de consulta desensamblan correctamente el JSON de alertas almacenado
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si se cambia TEMPERATURE_HIGH_THRESHOLD (39.5°C) o TEMPERATURE_LOW_THRESHOLD (36.0°C)
 * - Si se cambia el rango de temperatura viable (30–45°C)
 * - Si se amplía/restringe el rango de peso válido (0.1–50 kg)
 * - Si se modifican los ENUMs de appetite, energy_level o coat_condition
 * - Si se elimina o cambia el mensaje de duplicado diario
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\HealthCheckRepositoryInterface;
use App\Services\HealthCheckService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(\App\Services\HealthCheckService::class)]
final class HealthCheckServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&HealthCheckRepositoryInterface */
    private HealthCheckRepositoryInterface $repo;
    private HealthCheckService $service;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(HealthCheckRepositoryInterface::class);
        $this->service = new HealthCheckService($this->repo);
    }

    // ─────────────────────────────────────────────────────────────
    // createHealthCheck() — restricción de duplicado diario
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function createHealthCheckWhenCheckAlreadyExistsTodayReturnsFail(): void
    {
        $this->repo
            ->method('exists')
            ->willReturn(true);

        $result = $this->service->createHealthCheck(1, 1, []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Ya existe un chequeo', $result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // createHealthCheck() — validaciones de métricas físicas
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function createHealthCheckWithWeightBelowMinimumReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 0.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Peso fuera de rango', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithWeightAboveMaximumReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['weight_kg' => 100.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Peso fuera de rango', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithTemperatureBelowViableRangeReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['temperature_c' => 29.9]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Temperatura fuera de rango viable', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithTemperatureAboveViableRangeReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['temperature_c' => 45.1]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Temperatura fuera de rango viable', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithInvalidAppetiteEnumReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['appetite' => 'starving']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('apetito inválido', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithInvalidEnergyLevelEnumReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['energy_level' => 'hyper']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('energía inválido', $result->getMessage());
    }

    #[Test]
    public function createHealthCheckWithInvalidCoatConditionEnumReturnsFail(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['coat_condition' => 'shiny']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('pelaje inválida', $result->getMessage());
    }

    // ─────────────────────────────────────────────────────────────
    // createHealthCheck() — camino feliz con y sin alertas
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function createHealthCheckWithValidDataAndNoAlertsReturnsOk(): void
    {
        $this->repo->method('exists')->willReturn(false);
        $this->repo->method('create')->willReturn(42);

        $data = [
            'weight_kg'       => 5.0,
            'temperature_c'   => 38.0,
            'appetite'        => 'normal',
            'energy_level'    => 'normal',
            'coat_condition'  => 'good',
            'eyes_clear'      => true,
            'breathing_normal' => true,
            'mobility_normal' => true,
        ];

        $result = $this->service->createHealthCheck(1, 1, $data);

        $this->assertTrue($result->ok);
        $this->assertSame(42, $result->data['id']);
        $this->assertEmpty($result->data['alerts']);
    }

    #[Test]
    public function createHealthCheckWithFeverTemperatureReturnsOkWithAlerts(): void
    {
        $this->repo->method('exists')->willReturn(false);
        $this->repo->method('create')->willReturn(10);

        $data = ['temperature_c' => 40.0]; // > 39.5°C umbral de fiebre

        $result = $this->service->createHealthCheck(1, 1, $data);

        $this->assertTrue($result->ok);
        $this->assertNotEmpty($result->data['alerts']);
        $this->assertStringContainsString('Fiebre', $result->data['alerts'][0]);
    }

    #[Test]
    public function createHealthCheckDelegatesToRepositoryCreate(): void
    {
        $this->repo->method('exists')->willReturn(false);
        $this->repo
            ->expects($this->once())
            ->method('create')
            ->with($this->arrayHasKey('animal_id'))
            ->willReturn(5);

        $this->service->createHealthCheck(3, 2, []);
    }

    // ─────────────────────────────────────────────────────────────
    // detectAlerts() — temperatura
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function detectAlertsWithNormalDataReturnsEmptyAlerts(): void
    {
        $data = [
            'temperature_c'   => 38.0,
            'appetite'        => 'normal',
            'energy_level'    => 'normal',
            'eyes_clear'      => true,
            'breathing_normal' => true,
            'mobility_normal' => true,
            'coat_condition'  => 'good',
        ];

        $alerts = $this->service->detectAlerts($data);

        $this->assertEmpty($alerts);
    }

    #[Test]
    public function detectAlertsWithTemperatureAboveHighThresholdReturnsFeverAlert(): void
    {
        $alerts = $this->service->detectAlerts(['temperature_c' => 39.6]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Fiebre', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithTemperatureBelowLowThresholdReturnsHypothermiaAlert(): void
    {
        $alerts = $this->service->detectAlerts(['temperature_c' => 35.9]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Temperatura baja', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithTemperatureAtExactHighThresholdDoesNotTriggerFeverAlert(): void
    {
        // 39.5 es el umbral. Solo > 39.5 activa la alerta.
        $alerts = $this->service->detectAlerts(['temperature_c' => 39.5]);

        $feverAlerts = \array_filter($alerts, fn(string $a) => \str_contains($a, 'Fiebre'));
        $this->assertEmpty($feverAlerts);
    }

    // ─────────────────────────────────────────────────────────────
    // detectAlerts() — apetito
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function detectAlertsWithNoAppetiteReturnsVetAlert(): void
    {
        $alerts = $this->service->detectAlerts(['appetite' => 'none']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Sin apetito', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithReducedAppetiteReturnsMonitorAlert(): void
    {
        $alerts = $this->service->detectAlerts(['appetite' => 'reduced']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Apetito reducido', $alerts[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // detectAlerts() — energía y movilidad
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function detectAlertsWithLowEnergyAndMobilityIssueReturnsLethargyAlert(): void
    {
        $alerts = $this->service->detectAlerts([
            'energy_level'    => 'low',
            'mobility_normal' => false,
        ]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Letargo severo', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithLowEnergyOnlyReturnsEnergyAlert(): void
    {
        $alerts = $this->service->detectAlerts(['energy_level' => 'low']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Nivel de energía bajo', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithMobilityIssueOnlyReturnsMobilityAlert(): void
    {
        $alerts = $this->service->detectAlerts(['mobility_normal' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Movilidad reducida', $alerts[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // detectAlerts() — respiración y ojos
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function detectAlertsWithBreathingIssueReturnsBreathingAlert(): void
    {
        $alerts = $this->service->detectAlerts(['breathing_normal' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('respiratoria', $alerts[0]);
    }

    #[Test]
    public function detectAlertsWithEyesIssueReturnsEyesAlert(): void
    {
        $alerts = $this->service->detectAlerts(['eyes_clear' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Ojos', $alerts[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // detectAlerts() — pelaje
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function detectAlertsWithPoorCoatReturnsCoatAlert(): void
    {
        $alerts = $this->service->detectAlerts(['coat_condition' => 'poor']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Pelaje', $alerts[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // hasCheckToday()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function hasCheckTodayDelegatesToRepositoryExists(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('exists')
            ->with(5, \date('Y-m-d'))
            ->willReturn(true);

        $result = $this->service->hasCheckToday(5);

        $this->assertTrue($result);
    }

    #[Test]
    public function hasCheckTodayReturnsFalseWhenNoPreviousCheck(): void
    {
        $this->repo->method('exists')->willReturn(false);

        $this->assertFalse($this->service->hasCheckToday(99));
    }

    // ─────────────────────────────────────────────────────────────
    // getKeeperStatistics()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getKeeperStatisticsReturnsExpectedStructure(): void
    {
        $this->repo
            ->method('countByKeeperInPeriod')
            ->willReturn(12);

        $stats = $this->service->getKeeperStatistics(3);

        $this->assertSame(3, $stats['keeper_id']);
        $this->assertSame(12, $stats['checks_count']);
        $this->assertArrayHasKey('period_start', $stats);
        $this->assertArrayHasKey('period_end', $stats);
    }

    #[Test]
    public function getKeeperStatisticsUsesPreviousStartDateWhenProvided(): void
    {
        $this->repo->method('countByKeeperInPeriod')->willReturn(5);

        $stats = $this->service->getKeeperStatistics(1, '2026-01-01', '2026-01-31');

        $this->assertSame('2026-01-01', $stats['period_start']);
        $this->assertSame('2026-01-31', $stats['period_end']);
    }

    // ─────────────────────────────────────────────────────────────
    // getTodayDashboard()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getTodayDashboardReturnsExpectedKeys(): void
    {
        $this->repo->method('getTodayChecks')->willReturn([]);
        $this->repo->method('getPendingAnimals')->willReturn([]);

        $dashboard = $this->service->getTodayDashboard();

        $this->assertArrayHasKey('completed', $dashboard);
        $this->assertArrayHasKey('pending', $dashboard);
        $this->assertArrayHasKey('completed_count', $dashboard);
        $this->assertArrayHasKey('pending_count', $dashboard);
    }

    #[Test]
    public function getTodayDashboardDecodesAlertsJsonInCompletedChecks(): void
    {
        $checkWithJsonAlerts = [
            'id'     => 1,
            'alerts' => '["Fiebre detectada"]',
        ];
        $this->repo->method('getTodayChecks')->willReturn([$checkWithJsonAlerts]);
        $this->repo->method('getPendingAnimals')->willReturn([]);

        $dashboard = $this->service->getTodayDashboard();

        $decodedAlerts = $dashboard['completed'][0]['alerts'];
        $this->assertIsArray($decodedAlerts);
        $this->assertSame('Fiebre detectada', $decodedAlerts[0]);
    }

    // ─────────────────────────────────────────────────────────────
    // getAnimalHistory()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getAnimalHistoryDecodesJsonAlerts(): void
    {
        $rawCheck = [
            'id'      => 7,
            'alerts'  => '["Apetito reducido - monitorear de cerca"]',
        ];
        $this->repo
            ->method('getCheckHistory')
            ->willReturn([$rawCheck]);

        $history = $this->service->getAnimalHistory(2);

        $this->assertIsArray($history[0]['alerts']);
        $this->assertStringContainsString('Apetito reducido', $history[0]['alerts'][0]);
    }

    #[Test]
    public function getAnimalHistoryPassesLimitToRepository(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('getCheckHistory')
            ->with(4, 10)
            ->willReturn([]);

        $this->service->getAnimalHistory(4, 10);
    }

    // ─────────────────────────────────────────────────────────────
    // getActiveAlerts()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getActiveAlertsDecodesJsonAlerts(): void
    {
        $rawCheck = [
            'id'     => 3,
            'alerts' => '["Letargo severo - evaluación veterinaria urgente"]',
        ];
        $this->repo
            ->method('getCheckswithAlerts')
            ->willReturn([$rawCheck]);

        $alerts = $this->service->getActiveAlerts();

        $this->assertIsArray($alerts[0]['alerts']);
        $this->assertStringContainsString('Letargo severo', $alerts[0]['alerts'][0]);
    }

    #[Test]
    public function getActiveAlertsPassesDaysToRepository(): void
    {
        $this->repo
            ->expects($this->once())
            ->method('getCheckswithAlerts')
            ->with(14)
            ->willReturn([]);

        $this->service->getActiveAlerts(14);
    }

    // ─────────────────────────────────────────────────────────────
    // getCheckById()
    // ─────────────────────────────────────────────────────────────

    #[Test]
    public function getCheckByIdReturnsNullWhenNotFound(): void
    {
        $this->repo->method('findById')->willReturn(null);

        $this->assertNull($this->service->getCheckById(999));
    }

    #[Test]
    public function getCheckByIdDecodesJsonAlerts(): void
    {
        $this->repo->method('findById')->willReturn([
            'id'     => 1,
            'alerts' => '["Fiebre detectada: 40.0°C"]',
        ]);

        $check = $this->service->getCheckById(1);

        $this->assertNotNull($check);
        $this->assertIsArray($check['alerts']);
        $this->assertStringContainsString('Fiebre', $check['alerts'][0]);
    }
}
