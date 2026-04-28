<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? LoyaltyService: cálculo de tier, guards de stamps y delegación al repositorio.
 * ¿Qué me quieres demostrar? Que calculateTier devuelve el tier correcto según visitas y que addStamp con stamps<=0 retorna fail.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las constantes TIER_SILVER_MIN/GOLD_MIN/PLATINUM_MIN o la guard de stamps<=0.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\LoyaltyCardDTO;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Services\LoyaltyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyService::class)]
final class LoyaltyServiceTest extends TestCase
{
    private LoyaltyRepositoryInterface $repoStub;
    private LoyaltyService $service;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(LoyaltyRepositoryInterface::class);
        $this->service  = new LoyaltyService($this->repoStub);
    }

    public function testCalculateTierReturnsBronzeForZeroVisits(): void
    {
        $this->assertSame('bronze', $this->service->calculateTier(0));
    }

    public function testCalculateTierReturnsBronzeForNineVisits(): void
    {
        $this->assertSame('bronze', $this->service->calculateTier(9));
    }

    public function testCalculateTierReturnsSilverAtTenVisits(): void
    {
        $this->assertSame('silver', $this->service->calculateTier(10));
    }

    public function testCalculateTierReturnsSilverAtTwentyNineVisits(): void
    {
        $this->assertSame('silver', $this->service->calculateTier(29));
    }

    public function testCalculateTierReturnsGoldAtThirtyVisits(): void
    {
        $this->assertSame('gold', $this->service->calculateTier(30));
    }

    public function testCalculateTierReturnsGoldAtFortyNineVisits(): void
    {
        $this->assertSame('gold', $this->service->calculateTier(49));
    }

    public function testCalculateTierReturnsPlatinumAtFiftyVisits(): void
    {
        $this->assertSame('platinum', $this->service->calculateTier(50));
    }

    public function testCalculateTierReturnsPlatinumAboveFiftyVisits(): void
    {
        $this->assertSame('platinum', $this->service->calculateTier(100));
    }

    public function testAddStampFailsWhenStampsIsZero(): void
    {
        $result = $this->service->addStamp(1, 0);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_stamps', $result->code);
    }

    public function testAddStampFailsWhenStampsIsNegative(): void
    {
        $result = $this->service->addStamp(1, -1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('positivo', $result->error);
    }

    public function testGetCardStatusReturnsDelegatedData(): void
    {
        $card = new LoyaltyCardDTO(
            id: 1,
            user_id: 5,
            stamps: 3,
            current_tier: 'bronze',
            visits_count: 1,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findOrCreateCardByUserId')->willReturn($card);
        $this->repoStub->method('getCatalogRewardsForTier')->willReturn([]);
        $this->repoStub->method('findRewardsByUserId')->willReturn([]);

        $result = $this->service->getCardStatus(5);

        $this->assertTrue($result->ok);
        $this->assertSame($card, $result->data['card']);
    }

    public function testGetCardStatusFailsWhenRepoThrowsException(): void
    {
        $this->repoStub->method('findOrCreateCardByUserId')
            ->willThrowException(new \RuntimeException('DB error'));

        $result = $this->service->getCardStatus(999);

        $this->assertFalse($result->ok);
    }

    public function testGetAvailableRewardsReturnsArray(): void
    {
        $this->repoStub->method('getCatalogRewardsForTier')->willReturn([]);

        $result = $this->service->getAvailableRewards('bronze', 3);

        $this->assertIsArray($result);
    }
}
