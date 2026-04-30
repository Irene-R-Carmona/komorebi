<?php

/**
 * Partial: Filtros de usuarios (HDA — GET form, cero fetch de datos)
 */

$roles ??= [];
$currentParams ??= [];

$currentSearch = (string) ($currentParams['search'] ?? '');
$currentStatus = (string) ($currentParams['status'] ?? '');
$currentRole = (string) ($currentParams['role'] ?? '');
?>

<form method="GET" action="/admin/users" x-data class="filter-bar">
    <div class="filter-bar__row">

        <!-- Búsqueda -->
        <div class="filter-bar__search">
            <div class="search-input">
                <i class="bi bi-search search-input__icon"></i>
                <input
                    type="text"
                    name="search"
                    class="form-control search-input__field"
                    placeholder="Buscar por nombre o email..."
                    value="<?= htmlspecialchars($currentSearch, ENT_QUOTES, 'UTF-8') ?>"
                    @input.debounce.500ms="$el.form.requestSubmit()">
            </div>
        </div>

        <!-- Filtro estado (preserva search + role) -->
        <div class="filter-bar__filters">
            <?php
            $mkStatusUrl = static function (string $val) use ($currentParams): string {
                $p = array_merge($currentParams, ['page' => '1']);
                if ($val === '') {
                    unset($p['status']);
                } else {
                    $p['status'] = $val;
                }

                return '/admin/users?' . http_build_query($p);
            };
?>
            <?php $active = ' filter-btn-group__btn--active'; ?>
            <div class="filter-btn-group">
                <a href="<?= htmlspecialchars($mkStatusUrl(''), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === '' ? $active : '' ?>">Todos</a>
                <a href="<?= htmlspecialchars($mkStatusUrl('active'), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === 'active' ? $active : '' ?>">Activos</a>
                <a href="<?= htmlspecialchars($mkStatusUrl('inactive'), ENT_QUOTES, 'UTF-8') ?>"
                   class="filter-btn-group__btn<?= $currentStatus === 'inactive' ? $active : '' ?>">Inactivos</a>
            </div>

            <!-- Filtro por rol -->
            <select
                name="role"
                class="form-select"
                style="min-width: 150px;"
                @change="$el.form.requestSubmit()">
                <option value="">Todos los roles</option>
                <?php foreach ($roles as $r): ?>
                <option
                    value="<?= htmlspecialchars((string) $r['code'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $currentRole === $r['code'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Preservar sort/dir como hidden inputs -->
            <?php if (!empty($currentParams['sort'])): ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($currentParams['sort'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="dir"  value="<?= htmlspecialchars($currentParams['dir'] ?? 'asc', ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        </div>

        <!-- Limpiar (zero JS) -->
        <div class="filter-bar__actions">
            <?php if ($currentSearch !== '' || $currentStatus !== '' || $currentRole !== ''): ?>
            <a href="/admin/users" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>
                Limpiar
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>
