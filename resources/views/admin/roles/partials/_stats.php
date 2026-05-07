<?php

/**
 * Partial: Estadísticas de roles (PHP-rendered)
 *
 * @var array $stats - ['total_roles', 'total_permissions', 'total_modules', 'users_with_roles']
 */

$stats ??= ['total_roles' => 0, 'total_permissions' => 0, 'total_modules' => 0, 'users_with_roles' => 0];
?>

<div class="stats-grid animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-people"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Roles</div>
            <div class="stat-card__value"><?= (int) ($stats['total_roles'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Perfiles definidos</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-shield-check"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Permisos</div>
            <div class="stat-card__value"><?= (int) ($stats['total_permissions'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Acciones disponibles</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-diagram-3"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Módulos</div>
            <div class="stat-card__value"><?= (int) ($stats['total_modules'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Áreas del sistema</div>
        </div>
    </div>

    <div class="stat-card stat-card--info animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon"><i class="bi bi-person-check"></i></div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Asignados</div>
            <div class="stat-card__value"><?= (int) ($stats['users_with_roles'] ?? 0) ?></div>
            <div class="stat-card__subtitle">Usuarios con rol</div>
        </div>
    </div>
</div>
