<?php

declare(strict_types=1);

use App\Support\DateFormatting;

/**
 * Vista: Tarjeta de Fidelización
 *
 * Variables disponibles:
 * @var array $card - Datos de la tarjeta
 * @var array $available_rewards - Recompensas disponibles
 * @var array $redeemed_rewards - Historial de canjes
 * @var array $tier_progress - Progreso al siguiente tier
 */

// Extraer datos
$stamps = (int) ($card['stamps'] ?? 0);
$visits = (int) ($card['visits_count'] ?? 0);
$currentTier = (string) ($card['current_tier'] ?? 'bronze');
$redeemedCount = count($redeemed_rewards ?? []);

// Nombres de tiers
$tierNames = [
    'bronze' => 'Bronce',
    'silver' => 'Plata',
    'gold' => 'Oro',
    'platinum' => 'Platino',
];

$tierIcons = [
    'bronze' => 'bi-award',
    'silver' => 'bi-award-fill',
    'gold' => 'bi-trophy',
    'platinum' => 'bi-gem',
];

// Progreso
$nextTier = $tier_progress['next_tier'] ?? 'silver';
$visitsNeeded = (int) ($tier_progress['visits_to_next'] ?? 10);
$progressPercent = (int) ($tier_progress['progress_percentage'] ?? 0);

// Siguiente hito de recompensa (cada 5 sellos)
$nextMilestone = (int) (ceil($stamps / 5) * 5);
if ($nextMilestone === 0 || $nextMilestone === $stamps) {
    $nextMilestone += 5;
}
?>

<div class="loyalty-container">

    <!-- Encabezado de sección -->
    <div class="loyalty-header">
        <div class="loyalty-header__content">
            <h1 class="loyalty-header__title"><i class="bi bi-card-list" aria-hidden="true"></i> Mi Tarjeta de Fidelización</h1>
            <p class="loyalty-header__description">Acumula sellos con cada visita y canjea recompensas exclusivas</p>
        </div>
        <div class="loyalty-header__actions">
            <button type="button" class="btn-komorebi btn-komorebi-ghost loyalty-header__print-btn" @click="window.print()">
                <i class="bi bi-printer" aria-hidden="true"></i>
                Imprimir tarjeta
            </button>
        </div>
    </div>

    <!-- Tarjeta de Fidelización -->
    <section class="loyalty-section">
        <div class="loyalty-card">
            <!-- Header de tarjeta -->
            <div class="loyalty-card__header">
                <div class="loyalty-card__branding">
                    <h2><i class="bi bi-card-list" aria-hidden="true"></i> Mi Tarjeta</h2>
                    <p>Komorebi Café</p>
                </div>
                <div class="tier-badge">
                    <?php $tierIcon = $tierIcons[$currentTier] ?? 'bi-award'; ?>
                    <i class="bi <?= $tierIcon ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($tierNames[$currentTier] ?? 'Bronce', ENT_QUOTES, 'UTF-8') ?>
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
                <p class="sr-only">Tienes <?= $stamps ?> de 20 sellos obtenidos</p>
                <div class="stamps-grid">
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <div class="stamp <?= $i <= $stamps ? 'stamp--filled' : '' ?>" aria-hidden="true">
                            <span class="stamp__number"><?= $i ?></span>
                            <?php if ($i <= $stamps): ?>
                                <span class="stamp__icon"><i class="bi bi-paw-fill"></i></span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Tier Progress -->
            <div class="tier-progress">
                <h4>Progreso a
                    <?php $nextTierIcon = $tierIcons[$nextTier] ?? 'bi-award'; ?>
                    <i class="bi <?= $nextTierIcon ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($tierNames[$nextTier] ?? 'Plata', ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <progress
                    class="sr-only"
                    value="<?= $progressPercent ?>"
                    max="100"
                    aria-label="Progreso hacia <?= htmlspecialchars($tierNames[$nextTier] ?? 'Plata', ENT_QUOTES, 'UTF-8') ?>: <?= $progressPercent ?>%">
                </progress>
                <div class="tier-progress__bar" aria-hidden="true">
                    <div class="tier-progress__fill" style="width: <?= $progressPercent ?>%"></div>
                </div>
                <p class="tier-progress__text"><?= $visitsNeeded ?> visitas más para subir de nivel</p>
            </div>
        </div>
    </section>

    <!-- Recompensas Disponibles -->
    <section class="loyalty-section" x-data="loyaltyRewards()">
        <h2 class="section-title"><i class="bi bi-gift" aria-hidden="true"></i> Recompensas Disponibles</h2>
        <p class="section-subtitle">Canjea tus sellos por estas increíbles recompensas. Cada tier desbloquea nuevos beneficios.</p>

        <?php if (empty($available_rewards)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state__icon"><i class="bi bi-gift" aria-hidden="true"></i></div>
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
                    $canRedeem = (int) ($reward['stamps_required'] ?? 99) <= $stamps;
                    $tierRequired = (string) ($reward['tier_required'] ?? 'bronze');
                    $tierMet = array_search($currentTier, array_keys($tierNames), true) >= array_search($tierRequired, array_keys($tierNames), true);
                    $isLocked = !$tierMet;
                    ?>
                    <div class="reward-card <?= $isLocked ? 'reward-card--locked' : '' ?>">
                        <div class="reward-card__header">
                            <?php $icon = $reward['icon'] ?? 'bi-gift'; ?>
                            <span class="reward-card__icon">
                                <?php if (str_starts_with($icon, 'bi-')): ?>
                                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                                <?php else: ?>
                                    <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($isLocked): ?>
                                <span class="reward-badge reward-badge--locked"><i class="bi bi-lock-fill" aria-hidden="true"></i> <?= htmlspecialchars($tierNames[$tierRequired] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php elseif ($canRedeem): ?>
                                <span class="reward-badge reward-badge--available"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Disponible</span>
                            <?php else: ?>
                                <span class="reward-badge"><i class="bi bi-clock" aria-hidden="true"></i> <?= (int) ($reward['stamps_required'] ?? 0) - $stamps ?> sellos más</span>
                            <?php endif; ?>
                        </div>

                        <h3 class="reward-card__title"><?= htmlspecialchars($reward['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="reward-card__description"><?= htmlspecialchars($reward['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                        <div class="reward-card__footer">
                            <span class="reward-card__cost"><?= (int) ($reward['stamps_required'] ?? 0) ?> sellos</span>
                            <?php if (!$isLocked && $canRedeem): ?>
                                <button
                                    type="button"
                                    class="btn-reward"
                                    aria-label="Canjear recompensa: <?= htmlspecialchars($reward['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    @click="redeemReward('<?= htmlspecialchars($reward['reward_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>')"
                                    :disabled="loading">
                                    <span x-show="!loading">Canjear</span>
                                    <span x-show="loading" aria-hidden="true">...</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Toast de canje exitoso -->
        <div
            x-show="redemption"
            x-transition:enter="loyalty-toast-enter"
            x-transition:leave="loyalty-toast-leave"
            class="loyalty-redemption-toast"
            role="status"
            aria-live="polite"
            x-cloak>
            <div class="loyalty-redemption-toast__inner">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div class="loyalty-redemption-toast__body">
                    <strong>¡Recompensa canjeada!</strong>
                    <span x-text="redemption ? 'Código: ' + redemption.code : ''"></span>
                    <span x-text="redemption ? 'Expira: ' + redemption.expires : ''"></span>
                </div>
                <button type="button" class="loyalty-redemption-toast__close" @click="redemption = null" aria-label="Cerrar notificación">
                    <i class="bi bi-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Recompensas Canjeadas (si existen) -->
    <?php if (!empty($redeemed_rewards)): ?>
        <section class="loyalty-section">
            <h2 class="section-title"><i class="bi bi-list-ul" aria-hidden="true"></i> Historial de Canjes</h2>
            <div class="history-grid">
                <?php foreach (array_slice($redeemed_rewards, 0, 6) as $item): ?>
                    <div class="history-card">
                        <?php $historyIcon = $item['reward_icon'] ?? 'bi-gift'; ?>
                        <span class="history-card__icon">
                            <?php if (str_starts_with($historyIcon, 'bi-')): ?>
                                <i class="bi <?= e($historyIcon) ?>" aria-hidden="true"></i>
                            <?php else: ?>
                                <?= htmlspecialchars($historyIcon, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                        <div class="history-card__details">
                            <h4><?= htmlspecialchars($item['reward_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="history-card__date"><?= e(DateFormatting::toSpanishDate((string) ($item['redeemed_at'] ?? ''))) ?></p>
                        </div>
                        <?php if (!empty($item['is_used'])): ?>
                            <span class="history-card__status history-card__status--used"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Usado</span>
                        <?php else: ?>
                            <span class="history-card__status"><i class="bi bi-hourglass-split" aria-hidden="true"></i> Pendiente</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div><!-- /.loyalty-container -->

<!-- Component JS cargado desde /js/init/alpine-components.js -->
