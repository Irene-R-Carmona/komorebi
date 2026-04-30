<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? HealthCheckService: validaciones de peso/temperatura y detección de alertas.
 * ¿Qué me quieres demostrar? Que pesos fuera de rango devuelven Result::fail, y que temperatura fuera de umbral genera alertas.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las constantes WEIGHT_MIN/MAX, TEMPERATURE_HIGH/LOW_THRESHOLD, o la lógica de validación.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\AnimalHealthCheckDTO;
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
        $this->service = new HealthCheckService($this->repoStub);
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

    // ──────────────────────────────────────────────
    // detectAlerts() — lógica pura, sin statics
    // ──────────────────────────────────────────────

    public function testDetectAlertsReturnsFeverAlertForHighTemperature(): void
    {
        $alerts = $this->service->detectAlerts(['temperature_c' => 40.0]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Fiebre', $alerts[0]);
    }

    public function testDetectAlertsReturnsHypothermiaAlertForLowTemperature(): void
    {
        $alerts = $this->service->detectAlerts(['temperature_c' => 35.0]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Temperatura baja', $alerts[0]);
    }

    public function testDetectAlertsReturnsNoAppetiteAlert(): void
    {
        $alerts = $this->service->detectAlerts(['appetite' => 'none']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Sin apetito', $alerts[0]);
    }

    public function testDetectAlertsReturnsReducedAppetiteAlert(): void
    {
        $alerts = $this->service->detectAlerts(['appetite' => 'reduced']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Apetito reducido', $alerts[0]);
    }

    public function testDetectAlertsReturnsSevereLethargicWhenLowEnergyAndMobilityIssue(): void
    {
        $alerts = $this->service->detectAlerts(['energy_level' => 'low', 'mobility_normal' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Letargo severo', $alerts[0]);
    }

    public function testDetectAlertsReturnsLowEnergyAlertAlone(): void
    {
        $alerts = $this->service->detectAlerts(['energy_level' => 'low']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('energía bajo', $alerts[0]);
    }

    public function testDetectAlertsReturnsMobilityAlertAlone(): void
    {
        $alerts = $this->service->detectAlerts(['mobility_normal' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Movilidad reducida', $alerts[0]);
    }

    public function testDetectAlertsReturnsBreathingAlert(): void
    {
        $alerts = $this->service->detectAlerts(['breathing_normal' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Dificultad respiratoria', $alerts[0]);
    }

    public function testDetectAlertsReturnsEyesAlertWhenNoBreathingIssue(): void
    {
        $alerts = $this->service->detectAlerts(['eyes_clear' => false]);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Ojos con secreción', $alerts[0]);
    }

    public function testDetectAlertsReturnsPoorCoatAlert(): void
    {
        $alerts = $this->service->detectAlerts(['coat_condition' => 'poor']);

        $this->assertNotEmpty($alerts);
        $this->assertStringContainsString('Pelaje en mal estado', $alerts[0]);
    }

    public function testDetectAlertsReturnsEmptyArrayWhenNoIssues(): void
    {
        $alerts = $this->service->detectAlerts([
            'appetite' => 'normal',
            'energy_level' => 'normal',
            'mobility_normal' => true,
            'breathing_normal' => true,
            'eyes_clear' => true,
            'coat_condition' => 'good',
        ]);

        $this->assertEmpty($alerts);
    }

    // ──────────────────────────────────────────────
    // validateMetrics via createHealthCheck
    // ──────────────────────────────────────────────

    public function testCreateHealthCheckFailsWhenTemperatureTooLow(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['temperature_c' => 28.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Temperatura fuera de rango', $result->error);
    }

    public function testCreateHealthCheckFailsWhenTemperatureTooHigh(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['temperature_c' => 46.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Temperatura fuera de rango', $result->error);
    }

    public function testCreateHealthCheckFailsWhenAppetiteInvalid(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['appetite' => 'starving']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('apetito', $result->error);
    }

    public function testCreateHealthCheckFailsWhenEnergyLevelInvalid(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['energy_level' => 'exhausted']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('energía', $result->error);
    }

    public function testCreateHealthCheckFailsWhenCoatConditionInvalid(): void
    {
        $this->repoStub->method('existsForAnimalOnDate')->willReturn(false);

        $result = $this->service->createHealthCheck(1, 1, ['coat_condition' => 'shiny']);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('pelaje', $result->error);
    }

    // ──────────────────────────────────────────────
    // update()
    // ──────────────────────────────────────────────

    public function testUpdateFailsWhenCheckNotFound(): void
    {
        $this->repoStub->method('findById')->willReturn(null);

        $result = $this->service->update(999, []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testUpdateFailsWhenMetricsValidationFails(): void
    {
        $dto = new AnimalHealthCheckDTO(
            id: 1,
            animal_id: 1,
            checked_by: 1,
            check_date: '2024-01-01',
            created_at: '2024-01-01',
            weight_kg: 5.0,
            temperature_c: 38.0,
            appetite: 'normal',
            energy_level: 'normal',
            coat_condition: 'good',
            eyes_clear: true,
            breathing_normal: true,
            mobility_normal: true,
            notes: null,
            alerts: null,
        );
        $this->repoStub->method('findById')->willReturn($dto);

        $result = $this->service->update(1, ['temperature_c' => 28.0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Temperatura fuera de rango', $result->error);
    }

    public function testUpdateFailsWhenRepoReturnsFalse(): void
    {
        $dto = new AnimalHealthCheckDTO(
            id: 1,
            animal_id: 1,
            checked_by: 1,
            check_date: '2024-01-01',
            created_at: '2024-01-01',
            weight_kg: 5.0,
            temperature_c: 38.0,
            appetite: 'normal',
            energy_level: 'normal',
            coat_condition: 'good',
            eyes_clear: true,
            breathing_normal: true,
            mobility_normal: true,
            notes: null,
            alerts: null,
        );
        $this->repoStub->method('findById')->willReturn($dto);
        $this->repoStub->method('update')->willReturn(false);

        $result = $this->service->update(1, []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al actualizar', $result->error);
    }

    public function testUpdateSucceeds(): void
    {
        $dto = new AnimalHealthCheckDTO(
            id: 1,
            animal_id: 1,
            checked_by: 1,
            check_date: '2024-01-01',
            created_at: '2024-01-01',
            weight_kg: 5.0,
            temperature_c: 38.0,
            appetite: 'normal',
            energy_level: 'normal',
            coat_condition: 'good',
            eyes_clear: true,
            breathing_normal: true,
            mobility_normal: true,
            notes: null,
            alerts: null,
        );
        $this->repoStub->method('findById')->willReturn($dto);
        $this->repoStub->method('update')->willReturn(true);

        $result = $this->service->update(1, []);

        $this->assertTrue($result->ok);
    }

    // ──────────────────────────────────────────────
    // getTodayDashboard()
    // ──────────────────────────────────────────────

    public function testGetTodayDashboardReturnsExpectedKeys(): void
    {
        $this->repoStub->method('getTodayChecks')->willReturn([]);
        $this->repoStub->method('getPendingAnimals')->willReturn([]);

        $result = $this->service->getTodayDashboard();

        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('completed_count', $result);
        $this->assertArrayHasKey('pending_count', $result);
    }

    public function testGetTodayDashboardCountsMatchArraySize(): void
    {
        $this->repoStub->method('getTodayChecks')->willReturn([['id' => 1], ['id' => 2]]);
        $this->repoStub->method('getPendingAnimals')->willReturn([['id' => 3]]);

        $result = $this->service->getTodayDashboard();

        $this->assertSame(2, $result['completed_count']);
        $this->assertSame(1, $result['pending_count']);
    }

    // ──────────────────────────────────────────────
    // getActiveAlerts()
    // ──────────────────────────────────────────────

    public function testGetActiveAlertsDecodesJsonAlerts(): void
    {
        $this->repoStub->method('getCheckswithAlerts')->willReturn([
            ['id' => 1, 'animal_id' => 2, 'alerts' => '["Fiebre detectada","Sin apetito"]'],
        ]);

        $result = $this->service->getActiveAlerts();

        $this->assertIsArray($result[0]['alerts']);
        $this->assertSame('Fiebre detectada', $result[0]['alerts'][0]);
    }

    public function testGetActiveAlertsReturnsEmptyArrayWhenNoAlerts(): void
    {
        $this->repoStub->method('getCheckswithAlerts')->willReturn([]);

        $result = $this->service->getActiveAlerts();

        $this->assertEmpty($result);
    }

    // ──────────────────────────────────────────────
    // getKeeperStatistics()
    // ──────────────────────────────────────────────

    public function testGetKeeperStatisticsReturnsExpectedStructure(): void
    {
        $this->repoStub->method('countByKeeperInPeriod')->willReturn(7);

        $result = $this->service->getKeeperStatistics(5);

        $this->assertSame(5, $result['keeper_id']);
        $this->assertSame(7, $result['checks_count']);
        $this->assertArrayHasKey('period_start', $result);
        $this->assertArrayHasKey('period_end', $result);
    }
}
