<?php

/**
 * Partial: Estadísticas de usuarios
 *
 * Muestra contadores directos desde el controlador
 */

$stats ??= ['total_users' => 0, 'active_users' => 0, 'admin_users' => 0, 'inactive_users' => 0];
?>

<div class="stats-grid animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-people"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Total</div>
            <div class="stat-card__value"><?= $stats['total_users'] ?></div>
            <div class="stat-card__subtitle">Usuarios en el sistema</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Activos</div>
            <div class="stat-card__value"><?= $stats['active_users'] ?></div>
            <div class="stat-card__subtitle">Cuentas habilitadas</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-shield-lock"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Administradores</div>
            <div class="stat-card__value"><?= $stats['admin_users'] ?></div>
            <div class="stat-card__subtitle">Con acceso completo</div>
        </div>
    </div>

    <div class="stat-card stat-card--danger animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-x-circle"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Inactivos</div>
            <div class="stat-card__value"><?= $stats['inactive_users'] ?></div>
            <div class="stat-card__subtitle">Cuentas deshabilitadas</div>
        </div>
    </div>
</div>
