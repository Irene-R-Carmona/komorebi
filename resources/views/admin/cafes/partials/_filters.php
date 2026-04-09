<?php

/**
 * Partial: Filtros de cafés
 */
?>

<div class="filter-bar">
    <div class="filter-bar__row">
        <!-- Búsqueda -->
        <div class="filter-bar__search">
            <div class="search-input">
                <i class="bi bi-search search-input__icon"></i>
                <input
                    type="text"
                    class="form-control search-input__field"
                    placeholder="Buscar por nombre, ubicación o tipo..."
                    x-model.debounce.300ms="searchQuery">
            </div>
        </div>

        <!-- Filtros por categoría -->
        <div class="filter-bar__filters">
            <div class="category-filters">
                <button
                    type="button"
                    class="category-filter-btn"
                    :class="{ 'category-filter-btn--active': filterCategory === '' }"
                    @click="filterCategory = ''">
                    Todos
                </button>
                <button
                    type="button"
                    class="category-filter-btn"
                    :class="{ 'category-filter-btn--active': filterCategory === 'lounge' }"
                    @click="filterCategory = 'lounge'">
                    <span class="category-filter-btn__icon">🛋️</span>
                    Lounge
                </button>
                <button
                    type="button"
                    class="category-filter-btn"
                    :class="{ 'category-filter-btn--active': filterCategory === 'playroom' }"
                    @click="filterCategory = 'playroom'">
                    <span class="category-filter-btn__icon">🎮</span>
                    Playroom
                </button>
                <button
                    type="button"
                    class="category-filter-btn"
                    :class="{ 'category-filter-btn--active': filterCategory === 'farm' }"
                    @click="filterCategory = 'farm'">
                    <span class="category-filter-btn__icon">🌾</span>
                    Farm
                </button>
                <button
                    type="button"
                    class="category-filter-btn"
                    :class="{ 'category-filter-btn--active': filterCategory === 'zen' }"
                    @click="filterCategory = 'zen'">
                    <span class="category-filter-btn__icon">🧘</span>
                    Zen
                </button>
            </div>

            <!-- Filtro de Estado -->
            <div class="filter-bar__select">
                <select
                    class="form-select"
                    x-model="filterStatus"
                    aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
        </div>

        <!-- Limpiar -->
        <div class="filter-bar__actions">
            <button
                type="button"
                class="btn btn-outline-secondary"
                x-show="searchQuery || filterCategory || filterStatus"
                @click="searchQuery = ''; filterCategory = ''; filterStatus = ''">
                <i class="bi bi-x-lg me-1"></i>
                Limpiar
            </button>
        </div>
    </div>
</div>
