<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Result;
use App\Core\Queue;
use App\Core\WideEvent;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRewardCatalog;
use App\Jobs\RewardUnlockedJob;
use App\Services\Contracts\LoyaltyServiceInterface;
use PDO;

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
    private ?PDO $db = null;
    private ?LoyaltyCard $loyaltyCardModel = null;
    private ?LoyaltyReward $loyaltyRewardModel = null;
    private ?LoyaltyRewardCatalog $catalogModel = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    private function getCardModel(): LoyaltyCard
    {
        return $this->loyaltyCardModel ??= new LoyaltyCard($this->getDb());
    }

    private function getRewardModel(): LoyaltyReward
    {
        return $this->loyaltyRewardModel ??= new LoyaltyReward($this->getDb());
    }

    private function getCatalogModel(): LoyaltyRewardCatalog
    {
        return $this->catalogModel ??= new LoyaltyRewardCatalog($this->getDb());
    }

    /**
     * Añadir sello(s) a la tarjeta del usuario
     *
     * @param int $userId ID del usuario
     * @param int $stamps Cantidad de sellos a añadir (default: 1)
     * @param int|null $reservationId ID de la reserva que genera el sello
     * @return Result
     */
    #[\Override]
    public function addStamp(int $userId, int $stamps = 1, ?int $reservationId = null): Result
    {
        try {
            // Obtener o crear tarjeta
            $card = $this->getCardModel()->findOrCreateByUserId($userId);
            if (!$card) {
                return Result::fail('No se pudo obtener la tarjeta de fidelización');
            }

            // Añadir sellos
            $added = $this->getCardModel()->addStamps((int)$card['id'], $stamps);
            if (!$added) {
                return Result::fail('Error al añadir sellos');
            }

            // Actualizar tier si es necesario
            $newVisitsCount = (int)$card['visits_count'] + $stamps;
            $newTier = $this->calculateTier($newVisitsCount);
            if ($newTier !== $card['current_tier']) {
                $this->getCardModel()->updateTier((int)$card['id'], $newTier);
            }

            // Obtener tarjeta actualizada
            $updatedCard = $this->getCardModel()->findById((int)$card['id']);

            // Verificar si desbloqueó nueva recompensa (cada 5 sellos)
            $prevStamps = (int)$card['stamps'];
            $newStamps = (int)$updatedCard['stamps'];

            $prevMilestone = (int)floor($prevStamps / 5) * 5;
            $newMilestone = (int)floor($newStamps / 5) * 5;

            if ($newMilestone > $prevMilestone && $newMilestone > 0) {
                // Encolar notificación de recompensa desbloqueada
                Queue::push(RewardUnlockedJob::class, [
                    'user_id' => $userId,
                    'stamps' => $newStamps,
                    'tier' => $newTier,
                    'milestone' => $newMilestone,
                    '_correlation_id' => WideEvent::get('request_id') ?? '',
                ]);
            }

            return Result::ok([
                'card' => $updatedCard,
                'stamps_added' => $stamps,
                'new_tier' => $newTier,
                'tier_changed' => $newTier !== $card['current_tier']
            ]);
        } catch (\Exception $e) {
            return Result::fail('Error al añadir sello: ' . $e->getMessage());
        }
    }

    /**
     * Calcular tier según cantidad de visitas
     *
     * @param int $visitsCount Total de visitas completadas
     * @return string 'bronze', 'silver', 'gold', 'platinum'
     */
    #[\Override]
    public function calculateTier(int $visitsCount): string
    {
        if ($visitsCount >= 50) {
            return 'platinum';
        }
        if ($visitsCount >= 30) {
            return 'gold';
        }
        if ($visitsCount >= 10) {
            return 'silver';
        }
        return 'bronze';
    }

    /**
     * Canjear recompensa
     *
     * @param int $userId ID del usuario
     * @param string $rewardType Tipo de recompensa (ej: 'drink_free')
     * @return Result
     */
    #[\Override]
    public function redeemReward(int $userId, string $rewardType): Result
    {
        try {
            return Database::transaction(function () use ($userId, $rewardType) {
                // Obtener tarjeta
                $card = $this->getCardModel()->findByUserId($userId);
                if (!$card) {
                    return Result::fail('No tienes tarjeta de fidelización');
                }

                // Obtener información de la recompensa del catálogo
                $rewardInfo = $this->getCatalogModel()->findByType($rewardType);
                if (!$rewardInfo) {
                    return Result::fail('Recompensa no encontrada');
                }

                // Verificar sellos suficientes
                if ((int)$card['stamps'] < (int)$rewardInfo['stamps_required']) {
                    return Result::fail(
                        sprintf(
                            'Necesitas %d sellos. Tienes: %d',
                            $rewardInfo['stamps_required'],
                            $card['stamps']
                        )
                    );
                }

                // Verificar tier requerido
                $tierOrder = ['bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4];
                $userTierLevel = $tierOrder[$card['current_tier']] ?? 1;
                $requiredTierLevel = $tierOrder[$rewardInfo['tier_required']] ?? 1;

                if ($userTierLevel < $requiredTierLevel) {
                    return Result::fail(
                        sprintf(
                            'Requiere tier %s. Tu tier actual: %s',
                            ucfirst($rewardInfo['tier_required']),
                            ucfirst($card['current_tier'])
                        )
                    );
                }

                // Consumir sellos
                $consumed = $this->getCardModel()->consumeStamps(
                    (int)$card['id'],
                    (int)$rewardInfo['stamps_required']
                );

                if (!$consumed) {
                    return Result::fail('Error al consumir sellos');
                }

                // Generar código de canje único
                $redemptionCode = $this->generateRedemptionCode();

                // Calcular fecha de expiración
                $validityDays = (int)$rewardInfo['validity_days'];
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));

                // Crear registro de recompensa canjeada
                $rewardId = $this->getRewardModel()->create([
                    'user_id' => $userId,
                    'loyalty_card_id' => $card['id'],
                    'reward_type' => $rewardType,
                    'stamps_cost' => $rewardInfo['stamps_required'],
                    'redemption_code' => $redemptionCode,
                    'expires_at' => $expiresAt,
                    'notes' => null
                ]);

                return Result::ok([
                    'reward_id' => $rewardId,
                    'redemption_code' => $redemptionCode,
                    'reward_name' => $rewardInfo['name_es'],
                    'expires_at' => $expiresAt,
                    'stamps_remaining' => (int)$card['stamps'] - (int)$rewardInfo['stamps_required']
                ]);
            });
        } catch (\Exception $e) {
            return Result::fail('Error al canjear recompensa: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estado completo de la tarjeta del usuario
     *
     * @param int $userId ID del usuario
     * @return Result
     */
    #[\Override]
    public function getCardStatus(int $userId): Result
    {
        try {
            $card = $this->getCardModel()->findOrCreateByUserId($userId);
            if (!$card) {
                return Result::fail('Error al obtener tarjeta');
            }

            // Obtener recompensas disponibles para el tier del usuario
            $availableRewards = $this->getAvailableRewards($card['current_tier'], (int)$card['stamps']);

            // Obtener historial de recompensas canjeadas
            $redeemedRewards = $this->getRewardModel()->findByUserId($userId);

            // Calcular progreso al siguiente tier
            $tierProgress = $this->getTierProgress((int)$card['visits_count']);

            return Result::ok([
                'card' => $card,
                'available_rewards' => $availableRewards,
                'redeemed_rewards' => $redeemedRewards,
                'tier_progress' => $tierProgress
            ]);
        } catch (\Exception $e) {
            return Result::fail('Error al obtener estado: ' . $e->getMessage());
        }
    }

    /**
     * Obtener recompensas disponibles para canjear
     *
     * @param string $tier Tier actual del usuario
     * @param int $currentStamps Sellos actuales
     * @return array
     */
    #[\Override]
    public function getAvailableRewards(string $tier, int $currentStamps): array
    {
        $allRewards = $this->getCatalogModel()->getRewardsForTier($tier);

        // Añadir información de disponibilidad
        return array_map(function ($reward) use ($currentStamps) {
            $reward['can_redeem'] = $currentStamps >= (int)$reward['stamps_required'];
            $reward['stamps_needed'] = max(0, (int)$reward['stamps_required'] - $currentStamps);
            return $reward;
        }, $allRewards);
    }

    /**
     * Calcular progreso al siguiente tier
     *
     * @param int $visitsCount Visitas completadas
     * @return array
     */
    private function getTierProgress(int $visitsCount): array
    {
        $tiers = [
            'bronze' => ['min' => 0, 'max' => 9, 'next' => 'silver'],
            'silver' => ['min' => 10, 'max' => 29, 'next' => 'gold'],
            'gold' => ['min' => 30, 'max' => 49, 'next' => 'platinum'],
            'platinum' => ['min' => 50, 'max' => PHP_INT_MAX, 'next' => null]
        ];

        $currentTier = $this->calculateTier($visitsCount);
        $tierData = $tiers[$currentTier];

        if ($tierData['next'] === null) {
            return [
                'current_tier' => $currentTier,
                'next_tier' => null,
                'visits_to_next' => 0,
                'progress_percentage' => 100
            ];
        }

        $visitsToNext = $tierData['max'] - $visitsCount + 1;
        $tierRange = $tierData['max'] - $tierData['min'] + 1;
        $progressInTier = $visitsCount - $tierData['min'];

        // Evitar división por cero (nunca debería suceder con tiers bien configurados)
        $progressPercentage = $tierRange > 0
            ? (int)(($progressInTier / $tierRange) * 100)
            : 0;

        return [
            'current_tier' => $currentTier,
            'next_tier' => $tierData['next'],
            'visits_to_next' => $visitsToNext,
            'progress_percentage' => $progressPercentage
        ];
    }

    /**
     * Generar código único de canje (formato: KOM-XXXX-YYYY)
     */
    private function generateRedemptionCode(): string
    {
        $prefix = 'KOM';
        $part1 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $part2 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        return sprintf('%s-%s-%s', $prefix, $part1, $part2);
    }

    /**
     * Validar código de canje en TPV
     *
     * @param string $code Código de canje
     * @return Result
     */
    #[\Override]
    public function validateRedemptionCode(string $code): Result
    {
        try {
            $reward = $this->getRewardModel()->findByRedemptionCode($code);

            if (!$reward) {
                return Result::fail('Código de canje no válido');
            }

            if ($reward['status'] !== 'pending') {
                return Result::fail('Este código ya fue usado o expiró');
            }

            // Verificar expiración
            if (strtotime($reward['expires_at']) < time()) {
                $this->getRewardModel()->markExpired([(int)$reward['id']]);
                return Result::fail('Este código expiró el ' . date('d/m/Y', strtotime($reward['expires_at'])));
            }

            return Result::ok($reward);
        } catch (\Exception $e) {
            return Result::fail('Error al validar código: ' . $e->getMessage());
        }
    }

    /**
     * Marcar recompensa como usada (llamado desde TPV)
     *
     * @param string $code Código de canje
     * @return Result
     */
    #[\Override]
    public function useReward(string $code): Result
    {
        try {
            $reward = $this->getRewardModel()->findByRedemptionCode($code);

            if (!$reward || $reward['status'] !== 'pending') {
                return Result::fail('Código no válido');
            }

            $used = $this->getRewardModel()->markAsUsed((int)$reward['id']);

            if (!$used) {
                return Result::fail('Error al marcar recompensa como usada');
            }

            return Result::ok(['message' => 'Recompensa aplicada correctamente']);
        } catch (\Exception $e) {
            return Result::fail('Error: ' . $e->getMessage());
        }
    }
}
