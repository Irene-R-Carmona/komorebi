<?php

/**
 * Partial: Estadísticas de roles
 */

?>

<div class="stats-grid animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-people"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Roles</div>
            <div class="stat-card__value" x-text="totalRoles"></div>
            <div class="stat-card__subtitle">Perfiles definidos</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-shield-check"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Permisos</div>
            <div class="stat-card__value" x-text="totalPermissions"></div>
            <div class="stat-card__subtitle">Acciones disponibles</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-diagram-3"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Módulos</div>
            <div class="stat-card__value" x-text="totalModules"></div>
            <div class="stat-card__subtitle">Áreas del sistema</div>
        </div>
    </div>

    <div class="stat-card stat-card--info animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-person-check"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Asignados</div>
            <div class="stat-card__value" x-text="stats.users_with_roles || 0"></div>
            <div class="stat-card__subtitle">Usuarios con rol</div>
        </div>
    </div>
</div>
