<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\Result;
use App\Core\WideEvent;
use App\Domain\Loyalty\LoyaltyRewardStatus;
use App\Jobs\RewardUnlockedJob;
use App\Repositories\Contracts\LoyaltyRepositoryInterface;
use App\Services\Contracts\LoyaltyServiceInterface;
use Exception;
use Override;
use RuntimeException;

/**
 * Servicio de Fidelización - Sistema de sellos y recompensas
 *
 * Lógica de negocio:
 * - 1 sello = 1 visita completada
 * - Tiers: Bronze (0-9), Silver (10-29), Gold (30-49), Platinum (50+)
 * - Recompensas: 3-10 sellos según tipo
 * - Expiración: 30 días desde canje
 */
final class LoyaltyService implements LoyaltyServiceInterface
{
    private const int TIER_SILVER_MIN = 10;
    private const int TIER_GOLD_MIN = 30;
    private const int TIER_PLATINUM_MIN = 50;
    private const int DEFAULT_VALIDITY_DAYS = 365;

    private const array TIER_ORDER = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];

    private LoyaltyRepositoryInterface $loyaltyRepo;

    public function __construct(?LoyaltyRepositoryInterface $loyaltyRepo = null)
    {
        $this->loyaltyRepo = $loyaltyRepo ?? Container::make(LoyaltyRepositoryInterface::class);
    }

    #[Override]
    public function addStamp(int $userId, int $stamps = 1, ?int $reservationId = null): Result
    {
        // S3-05: Guard — stamps debe ser positivo
        if ($stamps <= 0) {
            return Result::fail('El número de sellos debe ser positivo', 'invalid_stamps');
        }

        try {
            return $this->loyaltyRepo->withTransaction(function () use ($userId, $stamps) {
                // SELECT ... FOR UPDATE previene race condition en sellos concurrentes
                $this->loyaltyRepo->lockCardForUpdate($userId);

                $card = $this->loyaltyRepo->findOrCreateCardByUserId($userId);

                $added = $this->loyaltyRepo->addStamps($card->id, $stamps);
                if (!$added) {
                    throw new RuntimeException('Error al añadir sellos');
                }

                $newVisitsCount = $card->visits_count + $stamps;
                $newTier = $this->calculateTier($newVisitsCount);
                if ($newTier !== $card->current_tier) {
                    $this->loyaltyRepo->updateTier($card->id, $newTier);
                }

                $updatedCard = $this->loyaltyRepo->findCardById($card->id);

                // Verificar si desbloquó nueva recompensa (cada 5 sellos)
                $prevStamps = $card->stamps;
                $newStamps = $updatedCard !== null ? $updatedCard->stamps : 0;

                $prevMilestone = (int) \floor($prevStamps / 5) * 5;
                $newMilestone = (int) \floor($newStamps / 5) * 5;

                if ($newMilestone > $prevMilestone && $newMilestone > 0) {
                    Queue::push(RewardUnlockedJob::class, [
                        'user_id' => $userId,
                        'stamps' => $newStamps,
                        'tier' => $newTier,
                        'milestone' => $newMilestone,
                        '_correlation_id' => WideEvent::get('request_id') ?? '',
                    ]);
                }

                WideEvent::setSection('loyalty', [
                    'user_id' => $userId,
                    'stamps' => $newStamps,
                    'tier' => $newTier,
                ]);

                return Result::ok([
                    'card' => $updatedCard,
                    'stamps_added' => $stamps,
                    'new_tier' => $newTier,
                    'tier_changed' => $newTier !== $card->current_tier,
                ]);
            });
        } catch (Exception $e) {
            Logger::warning('[LoyaltyService::addStamp] unexpected failure', ['exception' => $e->getMessage(), 'user_id' => $userId]);

            return Result::fail('Error al añadir sello: ' . $e->getMessage());
        }
    }

    /**
     * @param int $visitsCount Total de visitas completadas
     * @return string 'bronze', 'silver', 'gold', 'platinum'
     */
    #[Override]
    public function calculateTier(int $visitsCount): string
    {
        if ($visitsCount >= self::TIER_PLATINUM_MIN) {
            return 'platinum';
        }
        if ($visitsCount >= self::TIER_GOLD_MIN) {
            return 'gold';
        }
        if ($visitsCount >= self::TIER_SILVER_MIN) {
            return 'silver';
        }

        return 'bronze';
    }

    #[Override]
    public function redeemReward(int $userId, string $rewardType): Result
    {
        try {
            return $this->loyaltyRepo->withTransaction(function () use ($userId, $rewardType) {
                // SELECT ... FOR UPDATE previene race conditions en recanjes concurrentes
                $this->loyaltyRepo->lockCardForUpdate($userId);

                $card = $this->loyaltyRepo->findCardByUserId($userId);
                if (!$card) {
                    return Result::fail('No tienes tarjeta de fidelización');
                }

                $rewardInfo = $this->loyaltyRepo->findCatalogByType($rewardType);
                if (!$rewardInfo) {
                    return Result::fail('Recompensa no encontrada');
                }

                if ($card->stamps < (int) $rewardInfo['stamps_required']) {
                    return Result::fail(
                        \sprintf(
                            'Necesitas %d sellos. Tienes: %d',
                            $rewardInfo['stamps_required'],
                            $card->stamps
                        )
                    );
                }

                $userTierLevel = self::TIER_ORDER[$card->current_tier] ?? 1;
                $requiredTierLevel = self::TIER_ORDER[$rewardInfo['tier_required']] ?? 1;

                if ($userTierLevel < $requiredTierLevel) {
                    return Result::fail(
                        \sprintf(
                            'Requiere tier %s. Tu tier actual: %s',
                            \ucfirst($rewardInfo['tier_required']),
                            \ucfirst($card->current_tier)
                        )
                    );
                }

                $consumed = $this->loyaltyRepo->consumeStamps(
                    $card->id,
                    (int) $rewardInfo['stamps_required']
                );

                if (!$consumed) {
                    return Result::fail('Error al consumir sellos');
                }

                $redemptionCode = $this->generateRedemptionCode();

                $validityDays = (int) $rewardInfo['validity_days'];
                if ($validityDays <= 0) {
                    $validityDays = self::DEFAULT_VALIDITY_DAYS;
                }
                $expiresAt = \date('Y-m-d H:i:s', \strtotime("+{$validityDays} days"));

                $rewardId = $this->loyaltyRepo->createReward([
                    'user_id' => $userId,
                    'loyalty_card_id' => $card->id,
                    'reward_type' => $rewardType,
                    'stamps_cost' => $rewardInfo['stamps_required'],
                    'redemption_code' => $redemptionCode,
                    'expires_at' => $expiresAt,
                    'notes' => null,
                ]);

                return Result::ok([
                    'reward_id' => $rewardId,
                    'redemption_code' => $redemptionCode,
                    'reward_name' => $rewardInfo['name_es'],
                    'expires_at' => $expiresAt,
                    'stamps_remaining' => $card->stamps - (int) $rewardInfo['stamps_required'],
                ]);
            });
        } catch (Exception $e) {
            return Result::fail('Error al canjear recompensa: ' . $e->getMessage());
        }
    }

    #[Override]
    public function getCardStatus(int $userId): Result
    {
        try {
            $card = $this->loyaltyRepo->findOrCreateCardByUserId($userId);

            $availableRewards = $this->getAvailableRewards($card->current_tier, $card->stamps);

            $redeemedRewards = $this->loyaltyRepo->findRewardsByUserId($userId);

            $tierProgress = $this->getTierProgress($card->visits_count);

            return Result::ok([
                'card' => $card,
                'available_rewards' => $availableRewards,
                'redeemed_rewards' => $redeemedRewards,
                'tier_progress' => $tierProgress,
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al obtener estado: ' . $e->getMessage());
        }
    }

    #[Override]
    public function getAvailableRewards(string $tier, int $currentStamps): array
    {
        // Defensive: coerce empty/unknown tier to 'bronze' before repo call
        $safeTier = ($tier !== '') ? $tier : 'bronze';
        $allRewards = $this->loyaltyRepo->getCatalogRewardsForTier($safeTier);

        return \array_map(function ($reward) use ($currentStamps) {
            $reward['can_redeem'] = $currentStamps >= (int) $reward['stamps_required'];
            $reward['stamps_needed'] = \max(0, (int) $reward['stamps_required'] - $currentStamps);

            return $reward;
        }, $allRewards);
    }

    private function getTierProgress(int $visitsCount): array
    {
        $tiers = [
            'bronze' => ['min' => 0, 'max' => self::TIER_SILVER_MIN - 1, 'next' => 'silver'],
            'silver' => ['min' => self::TIER_SILVER_MIN, 'max' => self::TIER_GOLD_MIN - 1, 'next' => 'gold'],
            'gold' => ['min' => self::TIER_GOLD_MIN, 'max' => self::TIER_PLATINUM_MIN - 1, 'next' => 'platinum'],
            'platinum' => ['min' => self::TIER_PLATINUM_MIN, 'max' => PHP_INT_MAX, 'next' => null],
        ];

        $currentTier = $this->calculateTier($visitsCount);
        $tierData = $tiers[$currentTier];

        if ($tierData['next'] === null) {
            return [
                'current_tier' => $currentTier,
                'next_tier' => null,
                'visits_to_next' => 0,
                'progress_percentage' => 100,
            ];
        }

        $visitsToNext = $tierData['max'] - $visitsCount + 1;
        $tierRange = $tierData['max'] - $tierData['min'] + 1;
        $progressInTier = $visitsCount - $tierData['min'];

        // Evitar división por cero (nunca debería suceder con tiers bien configurados)
        $progressPercentage = $tierRange > 0
            ? (int) (($progressInTier / $tierRange) * 100)
            : 0;

        return [
            'current_tier' => $currentTier,
            'next_tier' => $tierData['next'],
            'visits_to_next' => $visitsToNext,
            'progress_percentage' => $progressPercentage,
        ];
    }

    /**
     * Generar código único de canje (formato: KOM-XXXX-YYYY)
     */
    private function generateRedemptionCode(): string
    {
        $prefix = 'KOM';
        $part1 = \strtoupper(\substr(\bin2hex(\random_bytes(2)), 0, 4));
        $part2 = \strtoupper(\substr(\bin2hex(\random_bytes(2)), 0, 4));

        return \sprintf('%s-%s-%s', $prefix, $part1, $part2);
    }

    /**
     * Validar código de canje en TPV
     *
     * @param string $code Código de canje
     * @return Result
     */
    #[Override]
    public function validateRedemptionCode(string $code): Result
    {
        try {
            $reward = $this->loyaltyRepo->findRewardByCode($code);

            if (!$reward) {
                return Result::fail('Código de canje no válido');
            }

            if ($reward->status !== LoyaltyRewardStatus::Pending->value) {
                return Result::fail('Este código ya fue usado o expiró');
            }

            // Verificar expiración
            if ($reward->expires_at !== null && \strtotime($reward->expires_at) < \time()) {
                $this->loyaltyRepo->markRewardsExpired([$reward->id]);

                return Result::fail('Este código expiró el ' . \date('d/m/Y', \strtotime($reward->expires_at)));
            }

            return Result::ok($reward);
        } catch (Exception $e) {
            return Result::fail('Error al validar código: ' . $e->getMessage());
        }
    }

    /**
     * Marcar recompensa como usada (llamado desde TPV)
     *
     * @param string $code Código de canje
     * @return Result
     */
    #[Override]
    public function useReward(string $code): Result
    {
        try {
            $reward = $this->loyaltyRepo->findRewardByCode($code);

            if (!$reward || $reward->status !== LoyaltyRewardStatus::Pending->value) {
                return Result::fail('Código no válido');
            }

            $used = $this->loyaltyRepo->markRewardUsed($reward->id);

            if (!$used) {
                return Result::fail('Error al marcar recompensa como usada');
            }

            return Result::ok(['message' => 'Recompensa aplicada correctamente']);
        } catch (Exception $e) {
            return Result::fail('Error: ' . $e->getMessage());
        }
    }

    /**
     * Revierte 1 sello del usuario al cancelar una reserva (Q-05).
     * Solo decrementa si el usuario tiene sellos disponibles.
     *
     * @param int $userId ID del usuario cuya reserva fue cancelada
     * @return Result
     */
    #[Override]
    public function reverseStamp(int $userId): Result
    {
        try {
            $card = $this->loyaltyRepo->findCardByUserId($userId);

            if (!$card || $card->stamps <= 0) {
                // No hay sellos que revertir — operación idempotente
                return Result::ok(['message' => 'No hay sellos que revertir']);
            }

            $consumed = $this->loyaltyRepo->consumeStamps($card->id, 1);

            if (!$consumed) {
                return Result::fail('Error al revertir sello', 'stamp_reverse_error');
            }

            Logger::info('[LoyaltyService] Sello revertido por cancelación de reserva', [
                'user_id' => $userId,
                'card_id' => $card->id,
            ]);

            return Result::ok(['message' => 'Sello revertido correctamente']);
        } catch (Exception $e) {
            Logger::warning('[LoyaltyService::reverseStamp] unexpected failure', [
                'exception' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return Result::fail('Error al revertir sello: ' . $e->getMessage());
        }
    }
}
