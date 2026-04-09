<?php

/**
 * Partial: Estadísticas de cafés
 */

?>

<div class="stats-grid animate-fade-in">
    <div class="stat-card stat-card--primary animate-stagger-1">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-shop"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Total</div>
            <div class="stat-card__value" x-text="totalCafes"></div>
            <div class="stat-card__subtitle">Cafeterías Komorebi</div>
        </div>
    </div>

    <div class="stat-card stat-card--success animate-stagger-2">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Operativos</div>
            <div class="stat-card__value" x-text="activeCafesCount"></div>
            <div class="stat-card__subtitle">Abiertos al público</div>
        </div>
    </div>

    <div class="stat-card stat-card--info animate-stagger-3">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-calendar-check"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Con Reservas</div>
            <div class="stat-card__value" x-text="cafesWithReservationsCount"></div>
            <div class="stat-card__subtitle">Visitas programadas</div>
        </div>
    </div>

    <div class="stat-card stat-card--warning animate-stagger-4">
        <div class="stat-card__header">
            <div class="stat-card__icon">
                <i class="bi bi-star-fill"></i>
            </div>
        </div>
        <div class="stat-card__content">
            <div class="stat-card__label">Valoración</div>
            <div class="stat-card__value" x-text="averageRating"></div>
            <div class="stat-card__subtitle">Rating promedio</div>
        </div>
    </div>
</div>
