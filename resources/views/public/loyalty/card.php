<?php

declare(strict_types=1);

echo "<!--VISTA CARD.PHP EJECUTADA-->";

/**
 * Vista: Tarjeta de Fidelización
 *
 * Variables disponibles:
 * @var array $card - Datos de la tarjeta
 * @var array $available_rewards - Recompensas disponibles
 * @var array $redeemed_rewards - Historial de canjes
 * @var array $tier_progress - Progreso al siguiente tier
 */

use App\Core\Csrf;

// Extraer datos
$stamps = (int) ($card['stamps'] ?? 0);
$visits = (int) ($card['visits_count'] ?? 0);
$currentTier = (string) ($card['current_tier'] ?? 'bronze');
$redeemedCount = count($redeemed_rewards ?? []);

// Nombres de tiers
$tierNames = [
    'bronze' => '🥉 Bronce',
    'silver' => '🥈 Plata',
    'gold' => '🥇 Oro',
    'platinum' => '💎 Platino'
];

$tierEmojis = [
    'bronze' => '🥉',
    'silver' => '🥈',
    'gold' => '🥇',
    'platinum' => '💎'
];

// Progreso
$nextTier = $tier_progress['next_tier'] ?? 'silver';
$visitsNeeded = (int) ($tier_progress['visits_needed'] ?? 10);
$progressPercent = (int) ($tier_progress['progress_percent'] ?? 0);

// Siguiente hito de recompensa (cada 5 sellos)
$nextMilestone = (int) (ceil($stamps / 5) * 5);
if ($nextMilestone === $stamps && $stamps > 0) {
    $nextMilestone += 5;
}
?>

<!-- Encabezado de sección -->
<div class="loyalty-header">
    <div class="loyalty-header__content">
        <h1 class="loyalty-header__title">🎴 Mi Tarjeta de Fidelización</h1>
        <p class="loyalty-header__description">Acumula sellos con cada visita y canjea recompensas exclusivas</p>
    </div>
</div>

<!-- Tarjeta de Fidelización -->
<section class="loyalty-section">
    <div class="loyalty-card">
        <!-- Header de tarjeta -->
        <div class="loyalty-card__header">
            <div class="loyalty-card__branding">
                <h2>🎴 Mi Tarjeta</h2>
                <p>Komorebi Café</p>
            </div>
            <div class="tier-badge">
                <?= htmlspecialchars($tierNames[$currentTier] ?? '🥉 Bronce', ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-card__value"><?= $stamps ?></span>
                <span class="stat-card__label">Sellos</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value"><?= $visits ?></span>
                <span class="stat-card__label">Visitas</span>
            </div>
            <div class="stat-card">
                <span class="stat-card__value"><?= $redeemedCount ?></span>
                <span class="stat-card__label">Canjeadas</span>
            </div>
        </div>

        <!-- Stamps Grid -->
        <div class="stamps-section">
            <h3>Tus Sellos <span class="stamps-section__hint">(próxima recompensa a los <?= $nextMilestone ?> sellos)</span></h3>
            <div class="stamps-grid">
                <?php for ($i = 1; $i <= 20; $i++): ?>
                    <div class="stamp <?= $i <= $stamps ? 'stamp--filled' : '' ?>">
                        <span class="stamp__number"><?= $i ?></span>
                        <?php if ($i <= $stamps): ?>
                            <span class="stamp__icon">🐾</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Tier Progress -->
        <div class="tier-progress">
            <h4>Progreso a <?= htmlspecialchars($tierNames[$nextTier] ?? '🥈 Plata', ENT_QUOTES, 'UTF-8') ?></h4>
            <div class="tier-progress__bar">
                <div class="tier-progress__fill" style="width: <?= $progressPercent ?>%"></div>
            </div>
            <p class="tier-progress__text"><?= $visitsNeeded ?> visitas más para subir de nivel</p>
        </div>
    </div>
</section>

<!-- Recompensas Disponibles -->
<section class="loyalty-section" x-data="loyaltyRewards()">
    <h2 class="section-title">🎁 Recompensas Disponibles</h2>
    <p class="section-subtitle">Canjea tus sellos por estas increíbles recompensas. Cada tier desbloquea nuevos beneficios.</p>

    <?php if (empty($available_rewards)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state__icon">🎁</div>
            <h3 class="empty-state__title">No hay recompensas disponibles todavía</h3>
            <p class="empty-state__description">
                Acumula más sellos visitando Komorebi Café para desbloquear increíbles recompensas.
            </p>
        </div>
    <?php else: ?>
        <!-- Rewards Grid -->
        <div class="rewards-grid">
            <?php foreach ($available_rewards as $reward): ?>
                <?php
                $canRedeem = (int)($reward['stamps_required'] ?? 99) <= $stamps;
                $tierRequired = (string)($reward['tier_required'] ?? 'bronze');
                $tierMet = array_search($currentTier, array_keys($tierNames), true) >= array_search($tierRequired, array_keys($tierNames), true);
                $isLocked = !$tierMet;
                ?>
                <div class="reward-card <?= $isLocked ? 'reward-card--locked' : '' ?>">
                    <div class="reward-card__header">
                        <span class="reward-card__icon"><?= htmlspecialchars($reward['icon'] ?? '🎁', ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($isLocked): ?>
                            <span class="reward-badge reward-badge--locked">🔒 <?= htmlspecialchars($tierNames[$tierRequired] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        <?php elseif ($canRedeem): ?>
                            <span class="reward-badge reward-badge--available">✅ Disponible</span>
                        <?php else: ?>
                            <span class="reward-badge">🕒 <?= (int)($reward['stamps_required'] ?? 0) - $stamps ?> sellos más</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="reward-card__title"><?= htmlspecialchars($reward['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="reward-card__description"><?= htmlspecialchars($reward['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                    <div class="reward-card__footer">
                        <span class="reward-card__cost"><?= (int)($reward['stamps_required'] ?? 0) ?> sellos</span>
                        <?php if (!$isLocked && $canRedeem): ?>
                            <button
                                class="btn-reward"
                                @click="redeemReward('<?= htmlspecialchars($reward['reward_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>')">
                                Canjear
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Recompensas Canjeadas (si existen) -->
<?php if (!empty($redeemed_rewards)): ?>
    <section class="loyalty-section">
        <h2 class="section-title">📜 Historial de Canjes</h2>
        <div class="history-grid">
            <?php foreach (array_slice($redeemed_rewards, 0, 6) as $item): ?>
                <div class="history-card">
                    <span class="history-card__icon"><?= htmlspecialchars($item['reward_icon'] ?? '🎁', ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="history-card__details">
                        <h4><?= htmlspecialchars($item['reward_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
                        <p class="history-card__date"><?= date('d/m/Y', strtotime((string)($item['redeemed_at'] ?? ''))) ?></p>
                    </div>
                    <?php if (!empty($item['is_used'])): ?>
                        <span class="history-card__status history-card__status--used">✅ Usado</span>
                    <?php else: ?>
                        <span class="history-card__status">⏳ Pendiente</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- Component JS cargado desde /js/init/alpine-components.js -->
