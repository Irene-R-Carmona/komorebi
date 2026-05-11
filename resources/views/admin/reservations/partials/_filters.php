<?php

/**
 * Partial: Filtros de reservas (GET form — URL encodes state)
 *
 * @var array $cafeNames     - Nombres de cafés para el dropdown
 * @var array $currentParams - Parámetros activos
 */

$cafeNames ??= [];
$currentParams ??= [];
?>

<form method="GET" action="/admin/reservations" class="filter-bar mb-4" x-data>
    <div class="reservation-filters">

        <!-- Búsqueda -->
        <div class="flex-grow-1" style="max-width: 300px;">
            <label for="filter-search" class="form-label">Buscar</label>
            <div class="search-input">
                <i class="bi bi-search search-input__icon"></i>
                <input
                    id="filter-search"
                    type="text"
                    name="search"
                    class="form-control search-input__field"
                    placeholder="Cliente, café o ID..."
                    value="<?= htmlspecialchars((string) ($currentParams['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    @input.debounce.500ms="$el.form.requestSubmit()">
            </div>
        </div>

        <!-- Estado -->
        <div style="min-width: 150px;">
            <label for="filter-status" class="form-label">Estado</label>
            <select id="filter-status" name="status" class="form-select" @change="$el.form.requestSubmit()">
                <option value="">Todos</option>
                <option value="confirmed" <?= ($currentParams['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmada</option>
                <option value="pending" <?= ($currentParams['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                <option value="cancelled" <?= ($currentParams['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                <option value="completed" <?= ($currentParams['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completada</option>
            </select>
        </div>

        <!-- Café -->
        <div style="min-width: 200px;">
            <label for="filter-cafe" class="form-label">Café</label>
            <select id="filter-cafe" name="cafe" class="form-select" @change="$el.form.requestSubmit()">
                <option value="">Todos los cafés</option>
                <?php foreach ($cafeNames as $cafeName): ?>
                    <option
                        value="<?= htmlspecialchars((string) $cafeName, ENT_QUOTES, 'UTF-8') ?>"
                        <?= ($currentParams['cafe'] ?? '') === $cafeName ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $cafeName, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Fecha Desde -->
        <div class="reservation-filters__date">
            <label for="filter-date-from" class="form-label">Desde</label>
            <input
                id="filter-date-from"
                type="date"
                name="date_from"
                class="form-control"
                value="<?= htmlspecialchars((string) ($currentParams['date_from'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                @change="$el.form.requestSubmit()">
        </div>

        <!-- Fecha Hasta -->
        <div class="reservation-filters__date">
            <label for="filter-date-to" class="form-label">Hasta</label>
            <input
                id="filter-date-to"
                type="date"
                name="date_to"
                class="form-control"
                value="<?= htmlspecialchars((string) ($currentParams['date_to'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                @change="$el.form.requestSubmit()">
        </div>

        <!-- Limpiar -->
        <?php if ($currentParams !== []): ?>
            <div>
                <a href="/admin/reservations" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        <?php endif; ?>

    </div>
</form>
