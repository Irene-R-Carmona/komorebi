<?php

/**
 * Partial: Estadísticas de moderación
 *
 * Muestra contador de pendientes y aprobadas recientemente si aplica
 */

$pendingCount = count($pending ?? []);
?>

<div class="stats-grid mb-4 animate-fade-in">
    <div class="stat-card stat-card--warning animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Pendientes</div>
            <div class="stat-card__value"><?= $pendingCount ?></div>
            <div class="stat-card__subtitle">Por revisar</div>
        </div>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="stat-card stat-card--info animate-stagger-2">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-info-circle"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Atención</div>
                <div class="stat-card__value"><i class="bi bi-pencil-square" aria-hidden="true"></i></div>
                <div class="stat-card__subtitle">Requiere moderación</div>
            </div>
        </div>
    <?php endif; ?>
</div>
