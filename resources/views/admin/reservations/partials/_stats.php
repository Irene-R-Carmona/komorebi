<?php

/**
 * Partial: Estadísticas de reservas
 *
 * Calcula estadísticas básicas de las reservas
 */

// Calcular estadísticas si no vienen del controlador
$total = count($reservations ?? []);
$confirmed = count(array_filter($reservations ?? [], fn ($r) => ($r['status'] ?? '') === 'confirmed'));
$pending = count(array_filter($reservations ?? [], fn ($r) => ($r['status'] ?? '') === 'pending'));
$cancelled = count(array_filter($reservations ?? [], fn ($r) => ($r['status'] ?? '') === 'cancelled'));
?>

<div class="stats-grid mb-4 animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-calendar"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Total</div>
            <div class="stat-card__value"><?= $total ?></div>
            <div class="stat-card__subtitle">Visitas programadas</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Confirmadas</div>
            <div class="stat-card__value"><?= $confirmed ?></div>
            <div class="stat-card__subtitle">Listas para disfrutar</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Pendientes</div>
            <div class="stat-card__value"><?= $pending ?></div>
            <div class="stat-card__subtitle">Por confirmar</div>
        </div>
    </div>

    <div class="stat-card stat-card--danger animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-x-circle"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Canceladas</div>
            <div class="stat-card__value"><?= $cancelled ?></div>
            <div class="stat-card__subtitle">No realizadas</div>
        </div>
    </div>
</div>
