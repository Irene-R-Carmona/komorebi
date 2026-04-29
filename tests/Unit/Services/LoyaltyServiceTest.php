<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? LoyaltyService: cálculo de tier, guards de stamps y delegación al repositorio.
 * ¿Qué me quieres demostrar? Que calculateTier devuelve el tier correcto según visitas y que addStamp con stamps<=0 retorna fail.
 * ¿Qué va a fallar en este test si se cambia el código? Si cambian las constantes TIER_SILVER_MIN/GOLD_MIN/PLATINUM_MIN o la guard de stamps<=0.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\LoyaltyCardDTO;
use App\Domain\DTO\LoyaltyRewardDTO;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Services\LoyaltyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testAddStampFailsWhenAddStampsReturnsFalse(): void
    {
        $card = new LoyaltyCardDTO(
            id: 1,
            user_id: 1,
            stamps: 0,
            current_tier: 'bronze',
            visits_count: 0,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findOrCreateCardByUserId')->willReturn($card);
        $this->repoStub->method('addStamps')->willReturn(false);

        $result = $this->service->addStamp(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('sellos', $result->error);
    }

    public function testAddStampSucceedsWithoutTierChange(): void
    {
        $card = new LoyaltyCardDTO(
            id: 1,
            user_id: 1,
            stamps: 2,
            current_tier: 'bronze',
            visits_count: 2,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $updatedCard = new LoyaltyCardDTO(
            id: 1,
            user_id: 1,
            stamps: 3,
            current_tier: 'bronze',
            visits_count: 3,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findOrCreateCardByUserId')->willReturn($card);
        $this->repoStub->method('addStamps')->willReturn(true);
        $this->repoStub->method('findCardById')->willReturn($updatedCard);

        $result = $this->service->addStamp(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->data['stamps_added']);
        $this->assertFalse($result->data['tier_changed']);
    }

    public function testAddStampSuccessWithTierChange(): void
    {
        $card = new LoyaltyCardDTO(
            id: 1,
            user_id: 1,
            stamps: 8,
            current_tier: 'bronze',
            visits_count: 8,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $updatedCard = new LoyaltyCardDTO(
            id: 1,
            user_id: 1,
            stamps: 10,
            current_tier: 'silver',
            visits_count: 10,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findOrCreateCardByUserId')->willReturn($card);
        $this->repoStub->method('addStamps')->willReturn(true);
        $this->repoStub->method('findCardById')->willReturn($updatedCard);

        $result = $this->service->addStamp(1, 2);

        $this->assertTrue($result->ok);
        $this->assertSame('silver', $result->data['new_tier']);
        $this->assertTrue($result->data['tier_changed']);
    }

    // ─────────────────────────────────────────────────────────────
    // validateRedemptionCode
    // ─────────────────────────────────────────────────────────────

    public function testValidateRedemptionCodeFailsWhenRewardNotFound(): void
    {
        $this->repoStub->method('findRewardByCode')->willReturn(null);

        $result = $this->service->validateRedemptionCode('INVALID');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('válido', $result->error);
    }

    public function testValidateRedemptionCodeFailsWhenStatusNotPending(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 1,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'used',
            redemption_code: 'KOM-ABCD-1234',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: '2024-01-02 10:00:00',
            expires_at: null,
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);

        $result = $this->service->validateRedemptionCode('KOM-ABCD-1234');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expiró', $result->error);
    }

    public function testValidateRedemptionCodeFailsWhenExpired(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 2,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'pending',
            redemption_code: 'KOM-EFGH-5678',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: null,
            expires_at: '2020-01-01 00:00:00',
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);
        $this->repoStub->method('markRewardsExpired')->willReturn(true);

        $result = $this->service->validateRedemptionCode('KOM-EFGH-5678');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expiró', $result->error);
    }

    public function testValidateRedemptionCodeSucceedsWhenValid(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 3,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'pending',
            redemption_code: 'KOM-WXYZ-9999',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: null,
            expires_at: \date('Y-m-d H:i:s', \strtotime('+30 days')),
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);

        $result = $this->service->validateRedemptionCode('KOM-WXYZ-9999');

        $this->assertTrue($result->ok);
        $this->assertSame($reward, $result->data);
    }

    public function testValidateRedemptionCodeHandlesRepositoryException(): void
    {
        $this->repoStub->method('findRewardByCode')
            ->willThrowException(new RuntimeException('DB failure'));

        $result = $this->service->validateRedemptionCode('KOM-ERR-0001');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // useReward
    // ─────────────────────────────────────────────────────────────

    public function testUseRewardFailsWhenRewardNotFound(): void
    {
        $this->repoStub->method('findRewardByCode')->willReturn(null);

        $result = $this->service->useReward('NO-CODE');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('válido', $result->error);
    }

    public function testUseRewardFailsWhenStatusNotPending(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 4,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'used',
            redemption_code: 'KOM-USED-0001',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: '2024-01-02 10:00:00',
            expires_at: null,
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);

        $result = $this->service->useReward('KOM-USED-0001');

        $this->assertFalse($result->ok);
    }

    public function testUseRewardFailsWhenMarkRewardUsedReturnsFalse(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 5,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'pending',
            redemption_code: 'KOM-MARK-0001',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: null,
            expires_at: null,
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);
        $this->repoStub->method('markRewardUsed')->willReturn(false);

        $result = $this->service->useReward('KOM-MARK-0001');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    public function testUseRewardSucceeds(): void
    {
        $reward = new LoyaltyRewardDTO(
            id: 6,
            user_id: 1,
            loyalty_card_id: 1,
            reward_type: 'drink_free',
            stamps_cost: 5,
            status: 'pending',
            redemption_code: 'KOM-OK-0001',
            redeemed_at: '2024-01-01 10:00:00',
            used_at: null,
            expires_at: null,
            notes: null,
            created_at: '2024-01-01 09:00:00',
        );
        $this->repoStub->method('findRewardByCode')->willReturn($reward);
        $this->repoStub->method('markRewardUsed')->willReturn(true);

        $result = $this->service->useReward('KOM-OK-0001');

        $this->assertTrue($result->ok);
        $this->assertSame('Recompensa aplicada correctamente', $result->data['message']);
    }

    public function testUseRewardHandlesRepositoryException(): void
    {
        $this->repoStub->method('findRewardByCode')
            ->willThrowException(new RuntimeException('Connection lost'));

        $result = $this->service->useReward('KOM-EXC-0001');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // reverseStamp
    // ─────────────────────────────────────────────────────────────

    public function testReverseStampReturnsOkWhenNoCardFound(): void
    {
        $this->repoStub->method('findCardByUserId')->willReturn(null);

        $result = $this->service->reverseStamp(999);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('No hay', $result->data['message']);
    }

    public function testReverseStampReturnsOkWhenStampsAreZero(): void
    {
        $card = new LoyaltyCardDTO(
            id: 10,
            user_id: 1,
            stamps: 0,
            current_tier: 'bronze',
            visits_count: 5,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findCardByUserId')->willReturn($card);

        $result = $this->service->reverseStamp(1);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('No hay', $result->data['message']);
    }

    public function testReverseStampFailsWhenConsumeStampsFails(): void
    {
        $card = new LoyaltyCardDTO(
            id: 11,
            user_id: 2,
            stamps: 3,
            current_tier: 'bronze',
            visits_count: 3,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findCardByUserId')->willReturn($card);
        $this->repoStub->method('consumeStamps')->willReturn(false);

        $result = $this->service->reverseStamp(2);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('revertir', $result->error);
    }

    public function testReverseStampSucceeds(): void
    {
        $card = new LoyaltyCardDTO(
            id: 12,
            user_id: 3,
            stamps: 5,
            current_tier: 'bronze',
            visits_count: 5,
            total_rewards_redeemed: 0,
            last_stamp_at: null,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        );
        $this->repoStub->method('findCardByUserId')->willReturn($card);
        $this->repoStub->method('consumeStamps')->willReturn(true);

        $result = $this->service->reverseStamp(3);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('Sello revertido', $result->data['message']);
    }

    public function testReverseStampHandlesRepositoryException(): void
    {
        $this->repoStub->method('findCardByUserId')
            ->willThrowException(new RuntimeException('DB timeout'));

        $result = $this->service->reverseStamp(4);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error', $result->error);
    }
}
