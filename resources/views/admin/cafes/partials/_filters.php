<?php

/**
 * Partial: Filtros de cafés (HDA — GET form, zero fetch de datos)
 */

$currentParams ??= [];
$currentSearch = (string) ($currentParams['search'] ?? '');
$currentCategory = (string) ($currentParams['category'] ?? '');
$currentStatus = (string) ($currentParams['status'] ?? '');

$mkUrl = static function (string $key, string $val) use ($currentParams): string {
    $p = array_merge($currentParams, ['page' => '1']);
    if ($val === '') {
        unset($p[$key]);
    } else {
        $p[$key] = $val;
    }

    return '/admin/cafes?' . http_build_query($p);
};

$active = ' category-filter-btn--active';
$statusActive = ' filter-btn-group__btn--active';
?>

<form method="GET" action="/admin/cafes" x-data class="filter-bar">
    <div class="filter-bar__row">

        <!-- Búsqueda -->
        <div class="filter-bar__search">
            <div class="search-input">
                <i class="bi bi-search search-input__icon"></i>
                <input
                    type="text"
                    name="search"
                    class="form-control search-input__field"
                    placeholder="Buscar por nombre, ubicación..."
                    value="<?= htmlspecialchars($currentSearch, ENT_QUOTES, 'UTF-8') ?>"
                    @input.debounce.500ms="$el.form.requestSubmit()">
            </div>
        </div>

        <div class="filter-bar__filters">

            <!-- Filtro por categoría -->
            <div class="category-filters">
                <a href="<?= htmlspecialchars($mkUrl('category', ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="category-filter-btn<?= $currentCategory === '' ? $active : '' ?>">Todos</a>
                <a href="<?= htmlspecialchars($mkUrl('category', 'lounge'), ENT_QUOTES, 'UTF-8') ?>"
                   class="category-filter-btn<?= $currentCategory === 'lounge' ? $active : '' ?>">
                    <span class="category-filter-btn__icon">🛋️</span>Lounge
                </a>
                <a href="<?= htmlspecialchars($mkUrl('category', 'playroom'), ENT_QUOTES, 'UTF-8') ?>"
                   class="category-filter-btn<?= $currentCategory === 'playroom' ? $active : '' ?>">
                    <span class="category-filter-btn__icon">🎮</span>Playroom
                </a>
                <a href="<?= htmlspecialchars($mkUrl('category', 'farm'), ENT_QUOTES, 'UTF-8') ?>"
                   class="category-filter-btn<?= $currentCategory === 'farm' ? $active : '' ?>">
                    <span class="category-filter-btn__icon">🌾</span>Farm
                </a>
                <a href="<?= htmlspecialchars($mkUrl('category', 'zen'), ENT_QUOTES, 'UTF-8') ?>"
                   class="category-filter-btn<?= $currentCategory === 'zen' ? $active : '' ?>">
                    <span class="category-filter-btn__icon">🧘</span>Zen
                </a>
            </div>

            <!-- Filtro por estado -->
            <div class="filter-btn-group">
                <a href="<?= htmlspecialchars($mkUrl('status', ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === '' ? $statusActive : '' ?>">Todos</a>
                <a href="<?= htmlspecialchars($mkUrl('status', 'active'), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === 'active' ? $statusActive : '' ?>">Activos</a>
                <a href="<?= htmlspecialchars($mkUrl('status', 'inactive'), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === 'inactive' ? $statusActive : '' ?>">Inactivos</a>
            </div>

            <!-- Preservar sort/dir como hidden inputs -->
            <?php if (!empty($currentParams['sort'])): ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($currentParams['sort'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="dir"  value="<?= htmlspecialchars($currentParams['dir'] ?? 'asc', ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        </div>

        <!-- Limpiar (zero JS) -->
        <div class="filter-bar__actions">
            <?php if ($currentSearch !== '' || $currentCategory !== '' || $currentStatus !== ''): ?>
            <a href="/admin/cafes" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>
                Limpiar
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>
