<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? GamificationService: cálculo de niveles, nombres y detección de level-up.
 * ¿Qué me quieres demostrar? Que los umbrales de nivel son correctos y que checkLevelUp detecta el cambio.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian los umbrales de LEVELS o la lógica de calculateUserLevel/checkLevelUp.
 */

namespace Tests\Unit\Services;

use App\Services\GamificationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GamificationService::class)]
final class GamificationServiceTest extends TestCase
{
    private GamificationService $service;

    protected function setUp(): void
    {
        $this->service = new GamificationService();
    }

    public function testCalculateUserLevelReturnsLevel1ForZeroReservations(): void
    {
        $result = $this->service->calculateUserLevel(0);

        $this->assertSame(1, $result['nivel']);
        $this->assertSame('Aprendiz', $result['nombre']);
    }

    public function testCalculateUserLevelReturnsLevel2At3Reservations(): void
    {
        $result = $this->service->calculateUserLevel(3);

        $this->assertSame(2, $result['nivel']);
        $this->assertSame('Habitual', $result['nombre']);
    }

    public function testCalculateUserLevelReturnsLevel3At7Reservations(): void
    {
        $result = $this->service->calculateUserLevel(7);

        $this->assertSame(3, $result['nivel']);
        $this->assertSame('Senpai', $result['nombre']);
    }

    public function testCalculateUserLevelReturnsLevel4At15Reservations(): void
    {
        $result = $this->service->calculateUserLevel(15);

        $this->assertSame(4, $result['nivel']);
        $this->assertSame('Maestro', $result['nombre']);
    }

    public function testCalculateUserLevelProgressIsMaxAt100ForHighestLevel(): void
    {
        $result = $this->service->calculateUserLevel(100);

        $this->assertSame(4, $result['nivel']);
        $this->assertSame(100, $result['progreso']);
    }

    public function testCalculateUserLevelReturnsAllExpectedKeys(): void
    {
        $result = $this->service->calculateUserLevel(5);

        $this->assertArrayHasKey('nivel', $result);
        $this->assertArrayHasKey('nombre', $result);
        $this->assertArrayHasKey('progreso', $result);
        $this->assertArrayHasKey('siguiente', $result);
    }

    public function testGetLevelNameReturnsCorrectName(): void
    {
        $this->assertSame('Aprendiz', $this->service->getLevelName(1));
        $this->assertSame('Habitual', $this->service->getLevelName(2));
        $this->assertSame('Senpai', $this->service->getLevelName(3));
        $this->assertSame('Maestro', $this->service->getLevelName(4));
    }

    public function testGetLevelNameReturnsDesconocidoForInvalidLevel(): void
    {
        $this->assertSame('Desconocido', $this->service->getLevelName(99));
    }

    public function testCheckLevelUpReturnsFalseWhenNoLevelChange(): void
    {
        $result = $this->service->checkLevelUp(0, 1);

        $this->assertFalse($result['level_up']);
    }

    public function testCheckLevelUpReturnsTrueWhenLevelChanges(): void
    {
        $result = $this->service->checkLevelUp(2, 3);

        $this->assertTrue($result['level_up']);
        $this->assertSame(2, $result['new_level']);
        $this->assertSame('Habitual', $result['new_level_name']);
    }

    public function testCheckLevelUpReturnsTrueWhenGoingToLevel3(): void
    {
        $result = $this->service->checkLevelUp(6, 7);

        $this->assertTrue($result['level_up']);
        $this->assertSame(3, $result['new_level']);
    }

    public function testCheckLevelUpReturnsTrueWhenGoingToMaestro(): void
    {
        $result = $this->service->checkLevelUp(14, 15);

        $this->assertTrue($result['level_up']);
        $this->assertSame(4, $result['new_level']);
        $this->assertSame('Maestro', $result['new_level_name']);
    }
}
