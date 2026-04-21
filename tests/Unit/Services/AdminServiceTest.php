<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * AdminStatisticsService::calculateTrend (private) vía ReflectionMethod,
 * y los métodos públicos de dominio: getSystemStatistics()
 * y getUserDistributionByRole().
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de cálculo de tendencias funciona correctamente en todos los
 * casos límite: sin datos previos, incremento, decremento y sin cambio.
 * Que los métodos de dominio devuelven las claves requeridas con los tipos correctos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si cambia la fórmula de calculateTrend, si se añade/quita el prefijo '+',
 * o si getSystemStatistics() deja de incluir las claves obligatorias.
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\StatisticsRepositoryInterface;
use App\Services\AdminStatisticsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(AdminStatisticsService::class)]
final class AdminServiceTest extends TestCase
{
    private AdminStatisticsService $service;

    protected function setUp(): void
    {
        $statsRepo = $this->createStub(StatisticsRepositoryInterface::class);
        $statsRepo->method('getSystemCounts')->willReturn([
            'users' => 0,
            'cafes' => 0,
            'reservations' => 0,
            'reviews' => 0,
            'pending_reviews' => 0,
        ]);
        $statsRepo->method('getWeeklyUserCounts')->willReturn(['current_week' => 0, 'previous_week' => 0]);
        $statsRepo->method('getWeeklyReservationCounts')->willReturn(['current_week' => 0, 'previous_week' => 0]);
        $this->service = new AdminStatisticsService($statsRepo);
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

    // ──────────────────────────────────────────────
    // getSystemStatistics
    // ──────────────────────────────────────────────

    public function testGetSystemStatisticsDevuelveClavesObligatorias(): void
    {
        $stats = $this->service->getSystemStatistics();

        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('cafes', $stats);
        $this->assertArrayHasKey('reservations', $stats);
        $this->assertArrayHasKey('reviews', $stats);
        $this->assertArrayHasKey('pending_reviews', $stats);
        $this->assertArrayHasKey('users_trend', $stats);
        $this->assertArrayHasKey('reservations_trend', $stats);
    }

    public function testGetSystemStatisticsDevuelveContadoresNoNegativos(): void
    {
        $stats = $this->service->getSystemStatistics();

        $this->assertGreaterThanOrEqual(0, $stats['users']);
        $this->assertGreaterThanOrEqual(0, $stats['cafes']);
        $this->assertGreaterThanOrEqual(0, $stats['reservations']);
        $this->assertGreaterThanOrEqual(0, $stats['reviews']);
        $this->assertGreaterThanOrEqual(0, $stats['pending_reviews']);
    }

    public function testGetUserDistributionByRoleDevuelveArray(): void
    {
        $result = $this->service->getUserDistributionByRole();

        $this->assertIsArray($result);
    }
}
