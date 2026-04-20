<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */
/**
 * Tests Unitarios de LoyaltyService
 *
 * Valida lógica de negocio pura (sin dependencias de BD).
 */

namespace Tests\Unit\Services;

use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Services\LoyaltyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[CoversClass(LoyaltyService::class)]
final class LoyaltyServiceTest extends TestCase
{
    private LoyaltyService $service;

    protected function setUp(): void
    {
        $this->service = new LoyaltyService(
            $this->createStub(LoyaltyRepositoryInterface::class)
        );
    }

    // ─────────────────────────────────────────────────────────────
    // calculateTier() - Lógica pura de cálculo de tiers
    // ─────────────────────────────────────────────────────────────

    #[TestDox("calculateTier retorna 'bronze' con 0 visitas")]
    public function testCalculateTierReturnsBronzeWithZeroVisits(): void
    {
        $tier = $this->service->calculateTier(0);
        $this->assertSame('bronze', $tier);
    }

    #[TestDox("calculateTier retorna 'bronze' con 9 visitas")]
    public function testCalculateTierReturnsBronzeWithNineVisits(): void
    {
        $tier = $this->service->calculateTier(9);
        $this->assertSame('bronze', $tier);
    }

    #[TestDox("calculateTier retorna 'silver' con 10 visitas (límite)")]
    public function testCalculateTierReturnsSilverWithTenVisits(): void
    {
        $tier = $this->service->calculateTier(10);
        $this->assertSame('silver', $tier);
    }

    #[TestDox("calculateTier retorna 'silver' con 29 visitas")]
    public function testCalculateTierReturnsSilverWithTwentyNineVisits(): void
    {
        $tier = $this->service->calculateTier(29);
        $this->assertSame('silver', $tier);
    }

    #[TestDox("calculateTier retorna 'gold' con 30 visitas (límite)")]
    public function testCalculateTierReturnsGoldWithThirtyVisits(): void
    {
        $tier = $this->service->calculateTier(30);
        $this->assertSame('gold', $tier);
    }

    #[TestDox("calculateTier retorna 'gold' con 49 visitas")]
    public function testCalculateTierReturnsGoldWithFortyNineVisits(): void
    {
        $tier = $this->service->calculateTier(49);
        $this->assertSame('gold', $tier);
    }

    #[TestDox("calculateTier retorna 'platinum' con 50 visitas (límite)")]
    public function testCalculateTierReturnsPlatinumWithFiftyVisits(): void
    {
        $tier = $this->service->calculateTier(50);
        $this->assertSame('platinum', $tier);
    }

    #[TestDox("calculateTier retorna 'platinum' con 100 visitas")]
    public function testCalculateTierReturnsPlatinumWithOneHundredVisits(): void
    {
        $tier = $this->service->calculateTier(100);
        $this->assertSame('platinum', $tier);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableRewards() - Lógica de filtrado de recompensas
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getAvailableRewards retorna array')]
    public function testGetAvailableRewardsReturnsArray(): void
    {
        $rewards = $this->service->getAvailableRewards('bronze', 5);
        $this->assertIsArray($rewards);
    }

    #[TestDox('getAvailableRewards filtra por tier mínimo')]
    public function testGetAvailableRewardsFiltersByMinimumTier(): void
    {
        // Bronze tiene menos recompensas que platinum
        $bronzeRewards = $this->service->getAvailableRewards('bronze', 100);
        $platinumRewards = $this->service->getAvailableRewards('platinum', 100);

        $this->assertIsArray($bronzeRewards);
        $this->assertIsArray($platinumRewards);

        // Platinum debe tener todas las recompensas de bronze + más
        $this->assertGreaterThanOrEqual(\count($bronzeRewards), \count($platinumRewards));
    }

    #[TestDox('getAvailableRewards filtra por sellos disponibles')]
    public function testGetAvailableRewardsFiltersByAvailableStamps(): void
    {
        // Con pocos sellos debe retornar pocas recompensas asequibles
        $fewStampsRewards = $this->service->getAvailableRewards('gold', 3);
        // Con muchos sellos debe retornar todas las recompensas accesibles
        $manyStampsRewards = $this->service->getAvailableRewards('gold', 50);

        $this->assertLessThanOrEqual(\count($manyStampsRewards), \count($fewStampsRewards));
    }
}
