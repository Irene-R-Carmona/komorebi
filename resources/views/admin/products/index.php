<?php

/**
 * Vista: Lista de Productos
 * Ruta: GET /admin/productos
 *
 * @var array $products - Lista de productos
 * @var array $categories - Categorías disponibles
 * @var int $total - Total de productos
 * @var array $filters - Filtros aplicados
 */

use App\Core\Csrf;
use App\Core\View;

$products ??= [];
$categories ??= [];
$total ??= count($products);
$filters ??= [];

// Calcular estadísticas
$available = count(array_filter($products, static fn($p) => !empty($p['is_active'])));
$withAllergens = count(array_filter($products, static fn($p) => !empty($p['allergens_list'])));
$csrfToken = Csrf::token();

// Preparar configuración para Alpine.js
$alpineConfig = json_encode([
    'products' => $products,
    'categories' => $categories,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='productManagement(<?= $alpineConfig ?>)' x-cloak>
    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'cup-hot',
        'title' => 'Carta y Productos',
        'subtitle' => 'Gestiona el menú con información de alérgenos',
        'actionLabel' => 'Añadir Producto',
        'actionUrl' => '/admin/productos/crear',
        'actionIcon' => 'plus-lg',
    ]) ?>

    <!-- Estadísticas -->
    <div class="stats-grid animate-fade-in">
        <div class="stat-card stat-card--primary animate-stagger-1">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-menu-button-wide"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Productos</div>
                <div class="stat-card__value"><?= $total ?></div>
                <div class="stat-card__subtitle">En la carta</div>
            </div>
        </div>

        <div class="stat-card stat-card--success animate-stagger-2">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Disponibles</div>
                <div class="stat-card__value"><?= $available ?></div>
                <div class="stat-card__subtitle">Listos para servir</div>
            </div>
        </div>

        <div class="stat-card stat-card--info animate-stagger-3">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-tag"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Categorías</div>
                <div class="stat-card__value"><?= count($categories) ?></div>
                <div class="stat-card__subtitle">Tipos de producto</div>
            </div>
        </div>

        <div class="stat-card stat-card--warning animate-stagger-4">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Con Alérgenos</div>
                <div class="stat-card__value"><?= $withAllergens ?></div>
                <div class="stat-card__subtitle">Requieren atención</div>
            </div>
        </div>
    </div>

    <!-- Filtros Instantáneos (Alpine.js - Jakob's Law) -->
    <div class="filter-bar">
        <div class="filter-bar__row">
            <div class="filter-bar__search">
                <div class="search-input">
                    <i class="bi bi-search search-input__icon"></i>
                    <input
                        type="text"
                        class="form-control search-input__field"
                        placeholder="Buscar por nombre..."
                        x-model.debounce.300ms="searchQuery"
                        @input="currentPage = 1">
                </div>
            </div>

            <div class="filter-bar__filters">
                <select class="form-select select-filter-lg" x-model="filterCategory" @change="currentPage = 1">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="form-select select-filter-md" x-model="filterAvailable" @change="currentPage = 1">
                    <option value="">Todos los estados</option>
                    <option value="1">Disponibles</option>
                    <option value="0">No disponibles</option>
                </select>

                <select class="form-select select-filter-lg" x-model="filterAllergen" @change="currentPage = 1">
                    <option value="">Todos los alérgenos</option>
                    <option value="with">Con alérgenos</option>
                    <option value="without">Sin alérgenos</option>
                </select>

                <select class="form-select select-filter-md" x-model="filterPriceRange" @change="currentPage = 1">
                    <option value="">Cualquier precio</option>
                    <option value="0-500">¥0 - ¥500</option>
                    <option value="500-1000">¥500 - ¥1000</option>
                    <option value="1000-1500">¥1000 - ¥1500</option>
                    <option value="1500+">¥1500+</option>
                </select>
            </div>

            <div class="filter-bar__actions">
                <span class="badge bg-primary me-2" x-show="activeFilterCount > 0">
                    <span x-text="activeFilterCount"></span> filtro<span x-show="activeFilterCount > 1">s</span>
                </span>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="clearAllFilters()"
                    x-show="activeFilterCount > 0"
                    title="Limpiar todos los filtros">
                    <i class="bi bi-x-circle me-1"></i>
                    Limpiar
                </button>
                <span class="text-muted ms-3" x-show="filteredProducts.length > 0">
                    <span x-text="filteredProducts.length"></span>
                    <span x-text="filteredProducts.length === 1 ? 'producto' : 'productos'"></span>
                </span>
            </div>
        </div>
    </div>

    <!-- Tabla de Productos -->
    <div class="card-admin">
        <div class="table-responsive">
            <table class="table table-admin table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">Imagen</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Disponibilidad</th>
                        <th class="text-end">Precio</th>
                        <th>Alérgenos</th>
                        <th style="width: 100px;">Estado</th>
                        <th style="width: 120px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loading state -->
                    <template x-if="isLoading">
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="text-muted mt-2 mb-0">Cargando productos...</p>
                            </td>
                        </tr>
                    </template>

                    <!-- Empty state -->
                    <template x-if="!isLoading && filteredProducts.length === 0">
                        <tr>
                            <td colspan="8">
                                <?= \App\Core\View::componentToString('components/admin/empty-state', [
                                    'icon' => 'inbox',
                                    'title' => 'No hay productos',
                                    'message' => 'Prueba ajustando los filtros o comienza agregando tu primer producto al menú',
                                    'actionLabel' => 'Crear Producto',
                                    'actionUrl' => '/admin/productos/crear',
                                    'compact' => true,
                                ]) ?>
                            </td>
                        </tr>
                    </template>

                    <!-- Product rows -->
                    <template x-for="product in paginatedProducts" :key="product.id">
                        <tr :data-product-id="product.id" class="product-row" :class="{ 'product-row--inactive': !product.is_active }">
                            <!-- Imagen -->
                            <td>
                                <template x-if="product.image_url">
                                    <img
                                        :src="product.image_url"
                                        :alt="product.name"
                                        class="product-thumbnail"
                                        loading="lazy"
                                        @error="$event.target.onerror=null; $event.target.src='/images/products/default.jpg';">
                                </template>
                                <template x-if="!product.image_url">
                                    <div class="product-thumbnail-placeholder">
                                        <i class="bi bi-image"></i>
                                    </div>
                                </template>
                            </td>

                            <!-- Nombre -->
                            <td>
                                <div class="product-info">
                                    <span class="product-name fw-semibold" x-text="product.name"></span>
                                    <span class="product-name-jp text-muted" x-show="product.japanese_name" x-text="product.japanese_name"></span>
                                </div>
                            </td>

                            <!-- Categoría -->
                            <td>
                                <span class="badge" :class="getCategoryBadgeClass(product.category_name)" x-text="product.category_name || 'Sin categoría'"></span>
                            </td>

                            <!-- Disponibilidad -->
                            <td>
                                <template x-if="product.cafe_types_display && product.cafe_types_display.length > 0">
                                    <div>
                                        <template x-for="(type, index) in product.cafe_types_display.slice(0, 2)" :key="index">
                                            <span class="badge bg-secondary text-white me-1 mb-1" x-text="type"></span>
                                        </template>
                                        <span x-show="product.cafe_types_display.length > 2" class="badge bg-secondary text-white" x-text="'+' + (product.cafe_types_display.length - 2)"></span>
                                    </div>
                                </template>
                                <template x-if="!product.cafe_types_display || product.cafe_types_display.length === 0">
                                    <span class="text-muted small">Todos</span>
                                </template>
                            </td>

                            <!-- Precio -->
                            <td class="text-end">
                                <span class="product-price fw-semibold">
                                    ¥<span x-text="formatPrice(product.price)"></span>
                                </span>
                            </td>

                            <!-- Alérgenos (Von Restorff Effect - destacar info crítica) -->
                            <td>
                                <div class="allergens-display">
                                    <template x-if="product.allergens_list && product.allergens_list.length > 0">
                                        <div class="allergen-badges">
                                            <template x-for="allergen in product.allergens_list.slice(0, 3)" :key="allergen.id">
                                                <span
                                                    class="badge"
                                                    :class="{
                                                        'bg-danger': allergen.severity === 'high',
                                                        'bg-warning text-dark': allergen.severity === 'medium',
                                                        'bg-info': allergen.severity === 'low'
                                                    }"
                                                    :title="`${allergen.name} - Severidad: ${allergen.severity}`">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                    <span x-text="allergen.code || allergen.name"></span>
                                                </span>
                                            </template>
                                            <span x-show="product.allergens_list.length > 3"
                                                class="badge bg-secondary"
                                                :title="`Y ${product.allergens_list.length - 3} más`">
                                                +<span x-text="product.allergens_list.length - 3"></span>
                                            </span>
                                        </div>
                                    </template>
                                    <template x-if="!product.allergens_list || product.allergens_list.length === 0">
                                        <span class="text-muted small">Sin alérgenos</span>
                                    </template>
                                </div>
                            </td>

                            <!-- Estado (Fitts's Law - botón grande, fácil de clickear) -->
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm w-100"
                                    :class="product.is_active ? 'btn-success' : 'btn-outline-secondary'"
                                    @click="toggleProductStatus(product.id)"
                                    :disabled="isToggling[product.id]"
                                    :aria-label="product.is_active ? 'Desactivar producto' : 'Activar producto'">
                                    <span x-show="!isToggling[product.id]" x-text="product.is_active ? 'Activo' : 'Inactivo'"></span>
                                    <span x-show="isToggling[product.id]">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </span>
                                </button>
                            </td>

                            <!-- Acciones (Aesthetic-Usability Effect - tooltips claros) -->
                            <td>
                                <div class="table-actions">
                                    <a
                                        :href="'/admin/productos/' + product.id + '/editar'"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Editar producto"
                                        :aria-label="'Editar ' + product.name">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        @click="deleteProduct(product.id, product.name)"
                                        title="Eliminar producto"
                                        :aria-label="'Eliminar ' + product.name">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="card-admin__footer" x-show="totalPages > 1">
            <nav aria-label="Paginación de productos">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <!-- Primera página -->
                    <li class="page-item" :class="{ 'disabled': currentPage === 1 }">
                        <button
                            class="page-link"
                            @click="currentPage = 1"
                            :disabled="currentPage === 1"
                            aria-label="Primera página">
                            <i class="bi bi-chevron-double-left"></i>
                        </button>
                    </li>

                    <!-- Página anterior -->
                    <li class="page-item" :class="{ 'disabled': currentPage === 1 }">
                        <button
                            class="page-link"
                            @click="currentPage--"
                            :disabled="currentPage === 1"
                            aria-label="Página anterior">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                    </li>

                    <!-- Números de página -->
                    <template x-for="page in totalPages" :key="page">
                        <li class="page-item" :class="{ 'active': currentPage === page }" x-show="Math.abs(currentPage - page) <= 2 || page === 1 || page === totalPages">
                            <button
                                class="page-link"
                                @click="currentPage = page"
                                x-text="page">
                            </button>
                        </li>
                    </template>

                    <!-- Página siguiente -->
                    <li class="page-item" :class="{ 'disabled': currentPage === totalPages }">
                        <button
                            class="page-link"
                            @click="currentPage++"
                            :disabled="currentPage === totalPages"
                            aria-label="Página siguiente">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </li>

                    <!-- Última página -->
                    <li class="page-item" :class="{ 'disabled': currentPage === totalPages }">
                        <button
                            class="page-link"
                            @click="currentPage = totalPages"
                            :disabled="currentPage === totalPages"
                            aria-label="Última página">
                            <i class="bi bi-chevron-double-right"></i>
                        </button>
                    </li>
                </ul>

                <!-- Info de paginación -->
                <div class="text-center text-muted small mt-2">
                    Página <span x-text="currentPage"></span> de <span x-text="totalPages"></span>
                    (<span x-text="filteredProducts.length"></span> productos)
                </div>
            </nav>
        </div>
    </div>
</div>
