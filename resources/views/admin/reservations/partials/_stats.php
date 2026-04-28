<?php

/**
 * Partial: Estadísticas de reservas (PHP-rendered)
 *
 * @var array $stats - ['total', 'confirmed', 'pending', 'cancelled']
 */

$stats ??= ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
?>

<div class="stats-grid mb-4 animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-calendar"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Total</div>
            <div class="stat-card__value"><?= (int) ($stats['total'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Visitas programadas</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-check-circle"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Confirmadas</div>
            <div class="stat-card__value"><?= (int) ($stats['confirmed'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Listas para disfrutar</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-hourglass-split"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Pendientes</div>
            <div class="stat-card__value"><?= (int) ($stats['pending'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Por confirmar</div>
        </div>
    </div>

    <div class="stat-card stat-card--danger animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-x-circle"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Canceladas</div>
            <div class="stat-card__value"><?= (int) ($stats['cancelled'] ?? 0) ?></div>
            <div class="stat-card__subtitle">No realizadas</div>
        </div>
    </div>
</div>
