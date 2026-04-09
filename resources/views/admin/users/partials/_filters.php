<?php
/**
 * Partial: Filtros de usuarios
 *
 * Incluye búsqueda, filtro por estado y filtro por rol
 */

$roles ??= [];
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
                    placeholder="Buscar por nombre, email o rol..."
                    x-model.debounce.300ms="searchQuery"
                    @input="currentPage = 1"
                >
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-bar__filters">
            <!-- Filtro por estado -->
            <div class="filter-btn-group">
                <button
                    type="button"
                    class="filter-btn-group__btn"
                    :class="{ 'filter-btn-group__btn--active': filterStatus === 'all' }"
                    @click="filterStatus = 'all'; currentPage = 1"
                >
                    Todos
                </button>
                <button
                    type="button"
                    class="filter-btn-group__btn"
                    :class="{ 'filter-btn-group__btn--active': filterStatus === 'active' }"
                    @click="filterStatus = 'active'; currentPage = 1"
                >
                    Activos
                </button>
                <button
                    type="button"
                    class="filter-btn-group__btn"
                    :class="{ 'filter-btn-group__btn--active': filterStatus === 'inactive' }"
                    @click="filterStatus = 'inactive'; currentPage = 1"
                >
                    Inactivos
                </button>
            </div>

            <!-- Filtro por rol -->
            <select
                class="form-select"
                style="min-width: 150px;"
                x-model="filterRole"
                @change="currentPage = 1"
            >
                <option value="">Todos los roles</option>
                <template x-for="role in availableRoles" :key="role.id">
                    <option :value="role.code" x-text="role.name"></option>
                </template>
            </select>
        </div>

        <!-- Acciones -->
        <div class="filter-bar__actions">
            <button
                type="button"
                class="btn btn-outline-secondary"
                x-show="searchQuery || filterStatus !== 'all' || filterRole"
                @click="searchQuery = ''; filterStatus = 'all'; filterRole = ''; currentPage = 1"
            >
                <i class="bi bi-x-lg me-1"></i>
                Limpiar
            </button>
        </div>
    </div>
</div>