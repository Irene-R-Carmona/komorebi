<?php

/**
 * Vista: Gestión de Productos
 * Ruta: GET /admin/menu
 *
 * @var array $products      - Productos paginados (transformer + allergens_list)
 * @var array $categories    - Categorías para filtros
 * @var array $stats         - Estadísticas del panel
 * @var array $meta          - Metadatos de paginación
 * @var array $currentParams - Parámetros de filtro actuales
 */

use App\Core\Csrf;
use App\Core\View;
use App\Support\ViewHelpers;

$products ??= [];
$categories ??= [];
$stats ??= ['total_products' => 0, 'active_products' => 0, 'category_count' => 0, 'with_allergens' => 0];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='productManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'cup-hot',
        'title' => 'Carta y Productos',
        'subtitle' => 'Gestiona el menú con información de alérgenos',
        'actionLabel' => 'Añadir Producto',
        'actionUrl' => '/admin/menu/create',
        'actionIcon' => 'plus-lg',
    ]) ?>

    <!-- Estadísticas -->
    <div class="stats-grid animate-fade-in">
        <div class="stat-card stat-card--primary animate-stagger-1">
            <div class="stat-card__header">
                <div class="stat-card__icon"><i class="bi bi-menu-button-wide"></i></div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Productos</div>
                <div class="stat-card__value"><?= (int) ($stats['total_products'] ?? 0) ?></div>
                <div class="stat-card__subtitle">En la carta</div>
            </div>
        </div>

        <div class="stat-card stat-card--success animate-stagger-2">
            <div class="stat-card__header">
                <div class="stat-card__icon"><i class="bi bi-check-circle"></i></div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Disponibles</div>
                <div class="stat-card__value"><?= (int) ($stats['active_products'] ?? 0) ?></div>
                <div class="stat-card__subtitle">Listos para servir</div>
            </div>
        </div>

        <div class="stat-card stat-card--info animate-stagger-3">
            <div class="stat-card__header">
                <div class="stat-card__icon"><i class="bi bi-tag"></i></div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Categorías</div>
                <div class="stat-card__value"><?= (int) ($stats['category_count'] ?? 0) ?></div>
                <div class="stat-card__subtitle">Tipos de producto</div>
            </div>
        </div>

        <div class="stat-card stat-card--warning animate-stagger-4">
            <div class="stat-card__header">
                <div class="stat-card__icon"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Con Alérgenos</div>
                <div class="stat-card__value"><?= (int) ($stats['with_allergens'] ?? 0) ?></div>
                <div class="stat-card__subtitle">Requieren atención</div>
            </div>
        </div>
    </div>

    <!-- Filtros (GET form — URL encodes state) -->
    <form method="GET" action="/admin/menu" class="filter-bar" x-data>
        <div class="filter-bar__row">
            <div class="filter-bar__search">
                <div class="search-input">
                    <i class="bi bi-search search-input__icon"></i>
                    <input
                        type="text"
                        name="search"
                        class="form-control search-input__field"
                        placeholder="Buscar por nombre..."
                        value="<?= htmlspecialchars((string) ($currentParams['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        @input.debounce.500ms="$el.form.requestSubmit()">
                </div>
            </div>

            <div class="filter-bar__filters">
                <select
                    name="category"
                    class="form-select select-filter-lg"
                    @change="$el.form.requestSubmit()">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= (int) $cat['id'] ?>"
                            <?= ((int) ($currentParams['category'] ?? 0)) === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select
                    name="status"
                    class="form-select select-filter-md"
                    @change="$el.form.requestSubmit()">
                    <option value="">Todos los estados</option>
                    <option value="1" <?= ($currentParams['status'] ?? '') === '1' ? 'selected' : '' ?>>Disponibles</option>
                    <option value="0" <?= ($currentParams['status'] ?? '') === '0' ? 'selected' : '' ?>>No disponibles</option>
                </select>
            </div>

            <?php if ($currentParams !== []): ?>
                <div class="filter-bar__actions">
                    <a href="/admin/menu" class="btn btn-outline-secondary" title="Limpiar filtros">
                        <i class="bi bi-x-circle me-1"></i>Limpiar
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabla -->
    <div class="card-admin">
        <?php if ($products === []): ?>
            <div class="card-admin__body">
                <?= View::componentToString('components/admin/empty-state', [
                    'icon' => 'inbox',
                    'title' => 'No hay productos',
                    'message' => 'Prueba ajustando los filtros o comienza agregando tu primer producto al menú',
                    'actionLabel' => 'Crear Producto',
                    'actionUrl' => '/admin/menu/create',
                    'compact' => true,
                ]) ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-admin table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Imagen</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-end">Precio</th>
                            <th>Alérgenos</th>
                            <th style="width: 100px;">Estado</th>
                            <th style="width: 120px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $productId = (int) $product['id'];
                            $isActive = !empty($product['is_active']);
                            $productName = htmlspecialchars((string) ($product['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $allergens = is_array($product['allergens_list'] ?? null) ? $product['allergens_list'] : [];
                            ?>
                            <tr data-product-id="<?= $productId ?>" class="<?= $isActive ? '' : 'product-row--inactive' ?>">
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img
                                            src="<?= htmlspecialchars((string) $product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= $productName ?>"
                                            class="product-thumbnail"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="product-thumbnail-placeholder">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="product-info">
                                        <span class="product-name fw-semibold"><?= $productName ?></span>
                                        <?php if (!empty($product['japanese_name'])): ?>
                                            <span class="product-name-jp text-muted">
                                                <?= htmlspecialchars((string) $product['japanese_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars((string) ($product['category_name'] ?? 'Sin categoría'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="product-price fw-semibold">
                                        ¥<?= number_format((float) ($product['price'] ?? 0)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($allergens !== []): ?>
                                        <div class="allergen-badges">
                                            <?php foreach (array_slice($allergens, 0, 3) as $allergen): ?>
                                                <?php
                                                $severity = (string) ($allergen['severity'] ?? 'low');
                                                $badgeClass = match ($severity) {
                                                    'high' => 'bg-danger',
                                                    'medium' => 'bg-warning text-dark',
                                                    default => 'bg-info',
                                                };
                                                $allergenName = htmlspecialchars((string) ($allergen['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                                $allergenCode = htmlspecialchars((string) ($allergen['code'] ?? $allergen['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                                ?>
                                                <span class="badge <?= $badgeClass ?>" title="<?= $allergenName ?>">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= $allergenCode ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($allergens) > 3): ?>
                                                <span class="badge bg-secondary">+<?= count($allergens) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin alérgenos</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm w-100 <?= $isActive ? 'btn-success' : 'btn-outline-secondary' ?>"
                                        @click="toggleProduct(<?= $productId ?>, <?= $isActive ? 'true' : 'false' ?>)"
                                        aria-label="<?= $isActive ? 'Desactivar' : 'Activar' ?> <?= $productName ?>">
                                        <?= $isActive ? 'Activo' : 'Inactivo' ?>
                                    </button>
                                </td>

                                <td>
                                    <div class="table-actions justify-content-end">
                                        <a
                                            href="/admin/menu/<?= $productId ?>/edit"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Editar"
                                            aria-label="Editar <?= $productName ?>">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="deleteProduct(<?= $productId ?>, '<?= $productName ?>')"
                                            title="Eliminar"
                                            aria-label="Eliminar <?= $productName ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center p-3 border-top mt-2">
                <div class="text-muted small">Página <?= (int) $meta['page'] ?></div>
                <?= ViewHelpers::paginationLinks($meta, $currentParams) ?>
            </div>
        <?php endif; ?>
    </div>

</div>
