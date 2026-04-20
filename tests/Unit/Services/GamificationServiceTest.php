<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios de GamificationService: cálculo de niveles, nombres y subidas de nivel.
 * ¿Qué me quieres demostrar?
 * Que los umbrales de nivel, la lógica de progreso porcentual y la detección de level-up
 * son completamente predecibles y acordes a las constantes LEVELS definidas en el servicio.
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en los umbrales (min/next), en los nombres de nivel, en la fórmula de
 * progreso o en la condición de level-up romperá estos tests.
 */

namespace Tests\Unit\Services;

use App\Services\GamificationService;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[CoversClass(GamificationService::class)]
final class GamificationServiceTest extends TestCase
{
    private GamificationService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->service = new GamificationService();
    }

    // ─────────────────────────────────────────────────────────────
    // calculateUserLevel() — Cálculo de nivel según reservas
    // ─────────────────────────────────────────────────────────────

    #[TestDox("calculateUserLevel retorna nivel 1 'Aprendiz' con 0 reservas")]
    public function testCalculateUserLevelReturnsLevel1WithZeroReservations(): void
    {
        $result = $this->service->calculateUserLevel(0);

        $this->assertSame(1, $result['nivel']);
        $this->assertSame('Aprendiz', $result['nombre']);
        $this->assertSame(0, $result['progreso']);
        $this->assertSame(3, $result['siguiente']);
    }

    #[TestDox('calculateUserLevel retorna nivel 1 con 2 reservas y progreso correcto')]
    public function testCalculateUserLevelReturnsLevel1WithTwoReservations(): void
    {
        $result = $this->service->calculateUserLevel(2);

        $this->assertSame(1, $result['nivel']);
        $this->assertSame('Aprendiz', $result['nombre']);
        // round((2/3)*100) = round(66.67) = 67
        $this->assertSame(67, $result['progreso']);
        $this->assertSame(3, $result['siguiente']);
    }

    #[TestDox("calculateUserLevel retorna nivel 2 'Habitual' con 3 reservas (umbral exacto)")]
    public function testCalculateUserLevelReturnsLevel2AtThreshold(): void
    {
        $result = $this->service->calculateUserLevel(3);

        $this->assertSame(2, $result['nivel']);
        $this->assertSame('Habitual', $result['nombre']);
        // round((3/7)*100) = round(42.86) = 43
        $this->assertSame(43, $result['progreso']);
        $this->assertSame(7, $result['siguiente']);
    }

    #[TestDox("calculateUserLevel retorna nivel 3 'Senpai' con 7 reservas (umbral exacto)")]
    public function testCalculateUserLevelReturnsLevel3AtThreshold(): void
    {
        $result = $this->service->calculateUserLevel(7);

        $this->assertSame(3, $result['nivel']);
        $this->assertSame('Senpai', $result['nombre']);
        // round((7/15)*100) = round(46.67) = 47
        $this->assertSame(47, $result['progreso']);
        $this->assertSame(15, $result['siguiente']);
    }

    #[TestDox("calculateUserLevel retorna nivel 4 'Maestro' con 15 reservas y progreso 100")]
    public function testCalculateUserLevelReturnsLevel4AtThresholdWithFullProgress(): void
    {
        $result = $this->service->calculateUserLevel(15);

        $this->assertSame(4, $result['nivel']);
        $this->assertSame('Maestro', $result['nombre']);
        $this->assertSame(100, $result['progreso']);
        $this->assertSame(999999, $result['siguiente']);
    }

    #[TestDox('calculateUserLevel retorna progreso 100 con muchas reservas en nivel máximo')]
    public function testCalculateUserLevelReturnsFullProgressAtMaxLevel(): void
    {
        $result = $this->service->calculateUserLevel(500);

        $this->assertSame(4, $result['nivel']);
        $this->assertSame('Maestro', $result['nombre']);
        $this->assertSame(100, $result['progreso']);
    }

    #[TestDox('calculateUserLevel retorna las cuatro claves esperadas en el array')]
    public function testCalculateUserLevelReturnsExpectedArrayKeys(): void
    {
        $result = $this->service->calculateUserLevel(5);

        $this->assertArrayHasKey('nivel', $result);
        $this->assertArrayHasKey('nombre', $result);
        $this->assertArrayHasKey('progreso', $result);
        $this->assertArrayHasKey('siguiente', $result);
    }

    // ─────────────────────────────────────────────────────────────
    // getLevelName() — Nombre del nivel por número
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getLevelName retorna los nombres correctos para cada nivel válido')]
    public function testGetLevelNameReturnsCorrectNamesForAllLevels(): void
    {
        $this->assertSame('Aprendiz', $this->service->getLevelName(1));
        $this->assertSame('Habitual', $this->service->getLevelName(2));
        $this->assertSame('Senpai', $this->service->getLevelName(3));
        $this->assertSame('Maestro', $this->service->getLevelName(4));
    }

    #[TestDox("getLevelName retorna 'Desconocido' para un nivel que no existe")]
    public function testGetLevelNameReturnsDesconocidoForUnknownLevel(): void
    {
        $this->assertSame('Desconocido', $this->service->getLevelName(99));
        $this->assertSame('Desconocido', $this->service->getLevelName(0));
        $this->assertSame('Desconocido', $this->service->getLevelName(-1));
    }

    // ─────────────────────────────────────────────────────────────
    // checkLevelUp() — Detección de subida de nivel
    // ─────────────────────────────────────────────────────────────

    #[TestDox('checkLevelUp retorna level_up=false si el nivel no cambia')]
    public function testCheckLevelUpReturnsFalseWhenLevelDoesNotChange(): void
    {
        // De 1 reserva a 2 reservas: ambas en nivel 1
        $result = $this->service->checkLevelUp(1, 2);

        $this->assertSame(['level_up' => false], $result);
    }

    #[TestDox('checkLevelUp retorna level_up=true con datos al subir de nivel 1 a 2')]
    public function testCheckLevelUpReturnsTrueWhenMovingFromLevel1To2(): void
    {
        // 2 reservas → nivel 1; 3 reservas → nivel 2
        $result = $this->service->checkLevelUp(2, 3);

        $this->assertTrue($result['level_up']);
        $this->assertSame(2, $result['new_level']);
        $this->assertSame('Habitual', $result['new_level_name']);
    }

    #[TestDox('checkLevelUp retorna level_up=true con datos al subir de nivel 2 a 3')]
    public function testCheckLevelUpReturnsTrueWhenMovingFromLevel2To3(): void
    {
        // 6 reservas → nivel 2; 7 reservas → nivel 3
        $result = $this->service->checkLevelUp(6, 7);

        $this->assertTrue($result['level_up']);
        $this->assertSame(3, $result['new_level']);
        $this->assertSame('Senpai', $result['new_level_name']);
    }

    #[TestDox('checkLevelUp retorna level_up=true al subir de nivel 3 a 4 (Maestro)')]
    public function testCheckLevelUpReturnsTrueWhenReachingMaxLevel(): void
    {
        // 14 reservas → nivel 3; 15 reservas → nivel 4
        $result = $this->service->checkLevelUp(14, 15);

        $this->assertTrue($result['level_up']);
        $this->assertSame(4, $result['new_level']);
        $this->assertSame('Maestro', $result['new_level_name']);
    }

    #[TestDox('checkLevelUp retorna level_up=false si ya está en nivel máximo')]
    public function testCheckLevelUpReturnsFalseWhenAlreadyAtMaxLevel(): void
    {
        // 20 reservas → nivel 4; 21 reservas → nivel 4 (sin cambio)
        $result = $this->service->checkLevelUp(20, 21);

        $this->assertSame(['level_up' => false], $result);
    }

    #[TestDox('checkLevelUp retorna level_up=false con iguales reservas antes y después')]
    public function testCheckLevelUpReturnsFalseWithEqualReservations(): void
    {
        $result = $this->service->checkLevelUp(5, 5);

        $this->assertSame(['level_up' => false], $result);
    }
}
