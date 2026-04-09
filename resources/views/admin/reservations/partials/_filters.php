<?php

/**
 * Partial: Filtros de reservas
 */
?>

<div class="filter-bar mb-4">
    <div class="reservation-filters">
        <!-- Búsqueda -->
        <div class="flex-grow-1" style="max-width: 300px;">
            <label class="form-label">Buscar</label>
            <div class="search-input">
                <i class="bi bi-search search-input__icon"></i>
                <input
                    type="text"
                    class="form-control search-input__field"
                    placeholder="Cliente, café o ID..."
                    x-model.debounce.300ms="searchQuery">
            </div>
        </div>

        <!-- Filtro Estado -->
        <div style="min-width: 150px;">
            <label class="form-label">Estado</label>
            <select class="form-select" x-model="filterStatus">
                <option value="">Todos</option>
                <option value="confirmed">Confirmada</option>
                <option value="pending">Pendiente</option>
                <option value="cancelled">Cancelada</option>
                <option value="completed">Completada</option>
            </select>
        </div>

        <!-- Filtro Café -->
        <div style="min-width: 200px;">
            <label class="form-label">Café</label>
            <select class="form-select" x-model="filterCafe">
                <option value="">Todos los cafés</option>
                <template x-for="cafe in uniqueCafes" :key="cafe">
                    <option :value="cafe" x-text="cafe"></option>
                </template>
            </select>
        </div>

        <!-- Fecha Desde -->
        <div class="reservation-filters__date">
            <label class="form-label">Desde</label>
            <input
                type="date"
                class="form-control"
                x-model="filterDateFrom">
        </div>

        <!-- Fecha Hasta -->
        <div class="reservation-filters__date">
            <label class="form-label">Hasta</label>
            <input
                type="date"
                class="form-control"
                x-model="filterDateTo">
        </div>

        <!-- Limpiar -->
        <div>
            <label class="form-label">&nbsp;</label>
            <button
                type="button"
                class="btn btn-outline-secondary"
                x-show="searchQuery || filterStatus || filterCafe || filterDateFrom || filterDateTo"
                @click="searchQuery = ''; filterStatus = ''; filterCafe = ''; filterDateFrom = ''; filterDateTo = ''">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</div>
