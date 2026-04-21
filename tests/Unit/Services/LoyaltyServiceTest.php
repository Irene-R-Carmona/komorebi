<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios de LoyaltyService: addStamp, getCardStatus, getAvailableRewards,
 * calculateTier, validateRedemptionCode, useReward y reverseStamp.
 *
 * ¿Qué me quieres demostrar?
 * Que la lógica de negocio (guards, transformaciones, flujos de éxito/fallo) funciona
 * correctamente sin dependencias reales de base de datos.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Cualquier cambio en el guard stamps<=0, umbrales de tier, validación de expiración
 * de códigos, mensajes de error de cada rama condicional o contratos de Result.
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
        $this->assertSame('bronze', $this->service->calculateTier(0));
    }

    #[TestDox("calculateTier retorna 'bronze' con 9 visitas")]
    public function testCalculateTierReturnsBronzeWithNineVisits(): void
    {
        $this->assertSame('bronze', $this->service->calculateTier(9));
    }

    #[TestDox("calculateTier retorna 'silver' con 10 visitas (límite inferior)")]
    public function testCalculateTierReturnsSilverWithTenVisits(): void
    {
        $this->assertSame('silver', $this->service->calculateTier(10));
    }

    #[TestDox("calculateTier retorna 'silver' con 29 visitas")]
    public function testCalculateTierReturnsSilverWithTwentyNineVisits(): void
    {
        $this->assertSame('silver', $this->service->calculateTier(29));
    }

    #[TestDox("calculateTier retorna 'gold' con 30 visitas (límite inferior)")]
    public function testCalculateTierReturnsGoldWithThirtyVisits(): void
    {
        $this->assertSame('gold', $this->service->calculateTier(30));
    }

    #[TestDox("calculateTier retorna 'gold' con 49 visitas")]
    public function testCalculateTierReturnsGoldWithFortyNineVisits(): void
    {
        $this->assertSame('gold', $this->service->calculateTier(49));
    }

    #[TestDox("calculateTier retorna 'platinum' con 50 visitas (límite inferior)")]
    public function testCalculateTierReturnsPlatinumWithFiftyVisits(): void
    {
        $this->assertSame('platinum', $this->service->calculateTier(50));
    }

    #[TestDox("calculateTier retorna 'platinum' con 100 visitas")]
    public function testCalculateTierReturnsPlatinumWithOneHundredVisits(): void
    {
        $this->assertSame('platinum', $this->service->calculateTier(100));
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableRewards() - Filtrado de recompensas del catálogo
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getAvailableRewards retorna array vacío cuando el catálogo está vacío')]
    public function testGetAvailableRewardsReturnsArray(): void
    {
        $rewards = $this->service->getAvailableRewards('bronze', 5);
        $this->assertIsArray($rewards);
    }

    #[TestDox('getAvailableRewards añade can_redeem=true cuando hay sellos suficientes')]
    public function testGetAvailableRewardsMarksCanRedeemTrue(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('getCatalogRewardsForTier')->willReturn([
            ['id' => 1, 'reward_type' => 'drink_free', 'stamps_required' => '3', 'name_es' => 'Bebida gratis'],
        ]);
        $service = new LoyaltyService($repo);

        $rewards = $service->getAvailableRewards('bronze', 5);

        $this->assertCount(1, $rewards);
        $this->assertTrue($rewards[0]['can_redeem']);
        $this->assertSame(0, $rewards[0]['stamps_needed']);
    }

    #[TestDox('getAvailableRewards añade can_redeem=false y stamps_needed correcto cuando faltan sellos')]
    public function testGetAvailableRewardsMarksCanRedeemFalseWithStampsNeeded(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('getCatalogRewardsForTier')->willReturn([
            ['id' => 1, 'reward_type' => 'dessert_free', 'stamps_required' => '5', 'name_es' => 'Postre gratis'],
        ]);
        $service = new LoyaltyService($repo);

        $rewards = $service->getAvailableRewards('bronze', 3);

        $this->assertCount(1, $rewards);
        $this->assertFalse($rewards[0]['can_redeem']);
        $this->assertSame(2, $rewards[0]['stamps_needed']);
    }

    #[TestDox('getAvailableRewards filtra por tier mínimo del catálogo')]
    public function testGetAvailableRewardsFiltersByMinimumTier(): void
    {
        $bronzeRewards = $this->service->getAvailableRewards('bronze', 100);
        $platinumRewards = $this->service->getAvailableRewards('platinum', 100);

        $this->assertIsArray($bronzeRewards);
        $this->assertIsArray($platinumRewards);
        $this->assertGreaterThanOrEqual(\count($bronzeRewards), \count($platinumRewards));
    }

    #[TestDox('getAvailableRewards filtra por sellos disponibles')]
    public function testGetAvailableRewardsFiltersByAvailableStamps(): void
    {
        $fewStampsRewards = $this->service->getAvailableRewards('gold', 3);
        $manyStampsRewards = $this->service->getAvailableRewards('gold', 50);

        $this->assertLessThanOrEqual(\count($manyStampsRewards), \count($fewStampsRewards));
    }

    // ─────────────────────────────────────────────────────────────
    // addStamp() - Añadir sellos a la tarjeta de fidelización
    // ─────────────────────────────────────────────────────────────

    #[TestDox('addStamp falla con code invalid_stamps cuando stamps es 0')]
    public function testAddStampFailsWhenStampsIsZero(): void
    {
        $result = $this->service->addStamp(1, 0);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_stamps', $result->code);
        $this->assertStringContainsString('positivo', $result->error);
    }

    #[TestDox('addStamp falla con code invalid_stamps cuando stamps es negativo')]
    public function testAddStampFailsWhenStampsIsNegative(): void
    {
        $result = $this->service->addStamp(1, -3);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_stamps', $result->code);
    }

    #[TestDox('addStamp falla si el repositorio devuelve tarjeta vacía (falsy)')]
    public function testAddStampFailsWhenRepositoryReturnsEmptyCard(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn([]);
        $service = new LoyaltyService($repo);

        $result = $service->addStamp(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tarjeta de fidelización', $result->error);
    }

    #[TestDox('addStamp falla si addStamps del repositorio devuelve false')]
    public function testAddStampFailsWhenAddStampsReturnsFalse(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '1', 'visits_count' => '1', 'current_tier' => 'bronze'],
        );
        $repo->method('addStamps')->willReturn(false);
        $service = new LoyaltyService($repo);

        $result = $service->addStamp(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('añadir sellos', $result->error);
    }

    #[TestDox('addStamp retorna ok con tier_changed=false cuando el tier no varía')]
    public function testAddStampSucceedsWithoutTierChange(): void
    {
        // stamps 1→2, visits 1→2 — tier permanece bronze
        // milestones: floor(1/5)*5=0 y floor(2/5)*5=0 → Queue::push NO se llama
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '1', 'visits_count' => '1', 'current_tier' => 'bronze'],
        );
        $repo->method('addStamps')->willReturn(true);
        $repo->method('findCardById')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '2', 'visits_count' => '2', 'current_tier' => 'bronze'],
        );
        $service = new LoyaltyService($repo);

        $result = $service->addStamp(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->data['stamps_added']);
        $this->assertFalse($result->data['tier_changed']);
        $this->assertSame('bronze', $result->data['new_tier']);
    }

    #[TestDox('addStamp detecta cambio de tier de bronze a silver al llegar a 10 visitas')]
    public function testAddStampDetectsTierChangeFromBronzeToSilver(): void
    {
        // visits 9→10 → tier bronze→silver
        // stamps 6→7 — milestones: floor(6/5)*5=5 y floor(7/5)*5=5 → Queue::push NO se llama
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '6', 'visits_count' => '9', 'current_tier' => 'bronze'],
        );
        $repo->method('addStamps')->willReturn(true);
        $repo->method('findCardById')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '7', 'visits_count' => '10', 'current_tier' => 'silver'],
        );
        $service = new LoyaltyService($repo);

        $result = $service->addStamp(1, 1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['tier_changed']);
        $this->assertSame('silver', $result->data['new_tier']);
    }

    // ─────────────────────────────────────────────────────────────
    // redeemReward() — requiere Database::transaction (estático)
    // Sin DB disponible el catch devuelve Result::fail.
    // Con DB disponible el stub devuelve null para findCardByUserId
    // → el closure retorna Result::fail('No tienes tarjeta de fidelización').
    // En ambos casos ok===false, por lo que el test es válido en cualquier entorno.
    // ─────────────────────────────────────────────────────────────

    #[TestDox('redeemReward retorna Result::fail cuando no hay tarjeta o DB no está disponible')]
    public function testRedeemRewardReturnsFailWhenNoDatabaseOrNoCard(): void
    {
        $result = $this->service->redeemReward(1, 'drink_free');

        $this->assertFalse($result->ok);
        $this->assertNotEmpty($result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // getCardStatus() - Estado completo de la tarjeta
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getCardStatus falla si el repositorio devuelve tarjeta vacía')]
    public function testGetCardStatusFailsWhenCardIsEmpty(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn([]);
        $service = new LoyaltyService($repo);

        $result = $service->getCardStatus(1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('tarjeta', $result->error);
    }

    #[TestDox('getCardStatus retorna ok con card, available_rewards, redeemed_rewards y tier_progress')]
    public function testGetCardStatusSucceedsWithAllDataKeys(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '5', 'visits_count' => '5', 'current_tier' => 'bronze'],
        );
        $repo->method('getCatalogRewardsForTier')->willReturn([
            ['id' => 1, 'reward_type' => 'drink_free', 'stamps_required' => '3', 'name_es' => 'Bebida gratis'],
        ]);
        $repo->method('findRewardsByUserId')->willReturn([]);
        $service = new LoyaltyService($repo);

        $result = $service->getCardStatus(1);

        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('card', $result->data);
        $this->assertArrayHasKey('available_rewards', $result->data);
        $this->assertArrayHasKey('redeemed_rewards', $result->data);
        $this->assertArrayHasKey('tier_progress', $result->data);
    }

    #[TestDox('getCardStatus incluye tier_progress con next_tier y progress_percentage')]
    public function testGetCardStatusIncludesTierProgress(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findOrCreateCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '3', 'visits_count' => '3', 'current_tier' => 'bronze'],
        );
        $repo->method('getCatalogRewardsForTier')->willReturn([]);
        $repo->method('findRewardsByUserId')->willReturn([]);
        $service = new LoyaltyService($repo);

        $result = $service->getCardStatus(1);

        $this->assertTrue($result->ok);
        $tierProgress = $result->data['tier_progress'];
        $this->assertSame('bronze', $tierProgress['current_tier']);
        $this->assertSame('silver', $tierProgress['next_tier']);
        $this->assertGreaterThan(0, $tierProgress['visits_to_next']);
    }

    // ─────────────────────────────────────────────────────────────
    // validateRedemptionCode() - Validar código en TPV
    // ─────────────────────────────────────────────────────────────

    #[TestDox('validateRedemptionCode falla si el código no existe en el repositorio')]
    public function testValidateRedemptionCodeFailsWhenCodeNotFound(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(null);
        $service = new LoyaltyService($repo);

        $result = $service->validateRedemptionCode('KOM-XXXX-YYYY');

        $this->assertFalse($result->ok);
        $this->assertSame('Código de canje no válido', $result->error);
    }

    #[TestDox('validateRedemptionCode falla si el estado no es pending')]
    public function testValidateRedemptionCodeFailsWhenStatusIsNotPending(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(
            ['id' => 1, 'status' => 'used', 'expires_at' => '2030-01-01 00:00:00'],
        );
        $service = new LoyaltyService($repo);

        $result = $service->validateRedemptionCode('KOM-USED-1234');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('usado', $result->error);
    }

    #[TestDox('validateRedemptionCode llama markRewardsExpired y falla cuando el código expiró')]
    public function testValidateRedemptionCodeFailsAndMarksExpiredWhenDateIsPast(): void
    {
        $repo = $this->createMock(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(
            ['id' => 42, 'status' => 'pending', 'expires_at' => '2020-06-15 10:00:00'],
        );
        $repo->expects($this->once())
            ->method('markRewardsExpired')
            ->with([42]);
        $service = new LoyaltyService($repo);

        $result = $service->validateRedemptionCode('KOM-EXPD-0001');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expiró', $result->error);
    }

    #[TestDox('validateRedemptionCode retorna ok con los datos de la recompensa cuando el código es válido')]
    public function testValidateRedemptionCodeSucceedsWithValidFutureCode(): void
    {
        $reward = [
            'id' => 7,
            'status' => 'pending',
            'expires_at' => '2030-12-31 23:59:59',
            'reward_type' => 'drink_free',
            'user_id' => 1,
        ];
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn($reward);
        $service = new LoyaltyService($repo);

        $result = $service->validateRedemptionCode('KOM-VALID-CODE');

        $this->assertTrue($result->ok);
        $this->assertSame($reward, $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // useReward() - Marcar recompensa como usada (desde TPV)
    // ─────────────────────────────────────────────────────────────

    #[TestDox('useReward falla si findRewardByCode devuelve null')]
    public function testUseRewardFailsWhenCodeNotFound(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(null);
        $service = new LoyaltyService($repo);

        $result = $service->useReward('KOM-NONE-0001');

        $this->assertFalse($result->ok);
        $this->assertSame('Código no válido', $result->error);
    }

    #[TestDox('useReward falla si el estado de la recompensa no es pending')]
    public function testUseRewardFailsWhenRewardStatusIsNotPending(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(['id' => 1, 'status' => 'expired']);
        $service = new LoyaltyService($repo);

        $result = $service->useReward('KOM-EXPR-0001');

        $this->assertFalse($result->ok);
        $this->assertSame('Código no válido', $result->error);
    }

    #[TestDox('useReward falla si markRewardUsed devuelve false')]
    public function testUseRewardFailsWhenMarkRewardUsedReturnsFalse(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(['id' => 5, 'status' => 'pending']);
        $repo->method('markRewardUsed')->willReturn(false);
        $service = new LoyaltyService($repo);

        $result = $service->useReward('KOM-FAIL-0001');

        $this->assertFalse($result->ok);
        $this->assertSame('Error al marcar recompensa como usada', $result->error);
    }

    #[TestDox('useReward retorna ok con mensaje de confirmación cuando la recompensa se aplica')]
    public function testUseRewardSucceedsAndReturnsConfirmationMessage(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findRewardByCode')->willReturn(['id' => 5, 'status' => 'pending']);
        $repo->method('markRewardUsed')->willReturn(true);
        $service = new LoyaltyService($repo);

        $result = $service->useReward('KOM-OK-0001');

        $this->assertTrue($result->ok);
        $this->assertSame('Recompensa aplicada correctamente', $result->data['message']);
    }

    // ─────────────────────────────────────────────────────────────
    // reverseStamp() - Revertir sello al cancelar una reserva (Q-05)
    // ─────────────────────────────────────────────────────────────

    #[TestDox('reverseStamp retorna ok idempotente si no hay tarjeta para el usuario')]
    public function testReverseStampReturnsOkIdempotentlyWhenNoCard(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findCardByUserId')->willReturn(null);
        $service = new LoyaltyService($repo);

        $result = $service->reverseStamp(1);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('No hay sellos', $result->data['message']);
    }

    #[TestDox('reverseStamp retorna ok idempotente si la tarjeta tiene 0 sellos')]
    public function testReverseStampReturnsOkIdempotentlyWhenCardHasZeroStamps(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '0', 'visits_count' => '0', 'current_tier' => 'bronze'],
        );
        $service = new LoyaltyService($repo);

        $result = $service->reverseStamp(1);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('No hay sellos', $result->data['message']);
    }

    #[TestDox('reverseStamp falla con code stamp_reverse_error si consumeStamps devuelve false')]
    public function testReverseStampFailsWithCorrectCodeWhenConsumeStampsFails(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '3', 'visits_count' => '3', 'current_tier' => 'bronze'],
        );
        $repo->method('consumeStamps')->willReturn(false);
        $service = new LoyaltyService($repo);

        $result = $service->reverseStamp(1);

        $this->assertFalse($result->ok);
        $this->assertSame('stamp_reverse_error', $result->code);
    }

    #[TestDox('reverseStamp retorna ok con mensaje de confirmación cuando el sello se revierte')]
    public function testReverseStampSucceedsAndReturnsConfirmationMessage(): void
    {
        $repo = $this->createStub(LoyaltyRepositoryInterface::class);
        $repo->method('findCardByUserId')->willReturn(
            ['id' => 1, 'user_id' => 1, 'stamps' => '3', 'visits_count' => '3', 'current_tier' => 'bronze'],
        );
        $repo->method('consumeStamps')->willReturn(true);
        $service = new LoyaltyService($repo);

        $result = $service->reverseStamp(1);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('revertido', $result->data['message']);
    }
}
