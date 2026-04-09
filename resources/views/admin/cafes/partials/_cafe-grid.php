<?php

/**
 * Partial: Grid de cafés
 */

use App\Core\View;

?>

<!-- Empty State -->
<template x-if="filteredCafes.length === 0">
    <div class="card-admin">
        <div class="card-admin__body">
            <?= View::componentToString('components/admin/empty-state', [
                'icon' => 'shop',
                'title' => 'No encontramos cafés',
                'message' => 'Prueba ajustando los filtros o añade una nueva sede',
                'actionLabel' => 'Crear Café',
                'actionClick' => 'openCreateModal()',
            ]) ?>
        </div>
    </div>
</template>

<!-- Grid de Cards -->
<div class="cafe-grid" x-show="filteredCafes.length > 0">
    <template x-for="cafe in filteredCafes" :key="cafe.id">
        <article class="cafe-card">
            <!-- Imagen -->
            <div class="cafe-card__image-wrapper">
                <template x-if="cafe.image_url">
                    <img
                        :src="cafe.image_url"
                        :alt="cafe.name"
                        class="cafe-card__image"
                        loading="lazy"
                        @error="handleImageError($event)">
                </template>
                <template x-if="!cafe.image_url">
                    <div class="cafe-card__image-placeholder">
                        <span x-text="getAnimalIcon(cafe.animal_type)"></span>
                    </div>
                </template>

                <!-- Badges sobre imagen -->
                <div class="cafe-card__badges">
                    <span
                        class="category-badge"
                        :class="getCategoryBadgeClass(cafe.category)">
                        <span x-text="getCategoryIcon(cafe.category)"></span>
                        <span x-text="getCategoryLabel(cafe.category)"></span>
                    </span>
                    <span
                        class="cafe-status-badge"
                        :class="cafe.is_active ? 'cafe-status-badge--active' : 'cafe-status-badge--inactive'"
                        x-text="cafe.is_active ? 'Activo' : 'Inactivo'"></span>
                </div>
            </div>

            <!-- Body -->
            <div class="cafe-card__body">
                <!-- Nombre -->
                <h3 class="cafe-card__name" x-text="cafe.name"></h3>
                <template x-if="cafe.japanese_name">
                    <p class="cafe-card__name-jp" x-text="cafe.japanese_name"></p>
                </template>

                <!-- Ubicación -->
                <p class="cafe-card__location">
                    <i class="bi bi-geo-alt"></i>
                    <span x-text="cafe.location"></span>
                </p>

                <!-- Tipo de animal -->
                <div class="mb-2">
                    <span class="animal-badge">
                        <span class="animal-badge__icon" x-text="getAnimalIcon(cafe.animal_type)"></span>
                        <span x-text="getAnimalLabel(cafe.animal_type)"></span>
                    </span>
                </div>

                <!-- Rating -->
                <div class="rating-display mb-2">
                    <template x-for="(filled, index) in getRatingStars(cafe.rating)" :key="index">
                        <i
                            class="bi rating-display__star"
                            :class="filled ? 'bi-star-fill rating-display__star--filled' : 'bi-star'"></i>
                    </template>
                    <span class="rating-display__value" x-text="'(' + (cafe.rating || 0) + ')'"></span>
                </div>

                <!-- Indicador de reservas -->
                <div
                    class="reservation-indicator"
                    :class="cafe.has_reservations ? 'reservation-indicator--enabled' : 'reservation-indicator--disabled'">
                    <i class="bi" :class="cafe.has_reservations ? 'bi-check-circle' : 'bi-x-circle'"></i>
                    <span x-text="cafe.has_reservations ? 'Acepta reservas' : 'Solo walk-ins'"></span>
                </div>

                <!-- Meta info -->
                <div class="cafe-card__meta">
                    <div class="cafe-card__meta-item">
                        <i class="bi bi-people"></i>
                        <span x-text="cafe.capacity_max + ' personas'"></span>
                    </div>
                    <div class="cafe-card__meta-item">
                        <i class="bi bi-currency-yen"></i>
                        <span>Desde <strong x-text="cafe.price_per_hour"></strong>¥/h</span>
                    </div>
                </div>
            </div>

            <!-- Footer (acciones) -->
            <div class="cafe-card__footer">
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="openEditModal(cafe)"
                    title="Editar">
                    <i class="bi bi-pencil me-1"></i>
                    Editar
                </button>
                <button
                    type="button"
                    class="btn"
                    :class="cafe.is_active ? 'btn-outline-warning' : 'btn-outline-success'"
                    @click="toggleCafeStatus(cafe.id)"
                    :title="cafe.is_active ? 'Desactivar' : 'Activar'">
                    <i class="bi" :class="cafe.is_active ? 'bi-pause' : 'bi-play'"></i>
                    <span x-text="cafe.is_active ? 'Pausar' : 'Activar'"></span>
                </button>
                <button
                    type="button"
                    class="btn btn-outline-danger"
                    @click="deleteCafe(cafe.id)"
                    title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </article>
    </template>
</div>
