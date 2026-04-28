<?php

/**
 * Gestión de Productos — Vista Manager (HDA)
 *
 * @var array  $products   - Lista de productos (PHP-rendered)
 * @var array  $categories - Categorías (PHP-rendered en modal select)
 * @var int    $cafeId
 * @var string $search     - Búsqueda activa
 */

use App\Core\Csrf;

$products   ??= [];
$categories ??= [];
$cafeId     ??= 0;
$search     ??= '';

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='managerProducts(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-bag text-primary" aria-hidden="true"></i> Gestión de Productos
            </h1>
            <p class="text-muted mb-0">Catálogo de productos de tu café</p>
        </div>
        <button type="button" class="btn btn-primary" @click="openCreate()">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> Nuevo Producto
        </button>
    </div>

    <!-- Filtro búsqueda (GET form) -->
    <form method="GET" action="/manager/products" class="mb-4 d-flex gap-2">
        <div class="input-group" style="max-width:400px">
            <label for="mp-search" class="input-group-text">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="visually-hidden">Buscar</span>
            </label>
            <input
                type="text"
                id="mp-search"
                name="search"
                class="form-control"
                placeholder="Buscar por nombre o categoría..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                @input.debounce.500ms="$el.form.requestSubmit()">
        </div>
        <?php if ($search !== ''): ?>
        <a href="/manager/products" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg" aria-hidden="true"></i> Limpiar
        </a>
        <?php endif; ?>
    </form>

    <!-- Tabla de productos -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Productos (<?= count($products) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No hay productos.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $p): ?>
                        <?php
                            $pid      = (int)   ($p['id'] ?? 0);
                            $isAvail  = !empty($p['is_available']);
                            $pName    = htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $editData = htmlspecialchars(\json_encode([
                                'id'          => $pid,
                                'name'        => (string) ($p['name']          ?? ''),
                                'price'       => $p['price']                   ?? 0,
                                'category_id' => $p['category_id']             ?? '',
                                'description' => (string) ($p['description']   ?? ''),
                            ], \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR), \ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= $pName ?></td>
                            <td><?= htmlspecialchars((string) ($p['category_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>€<?= number_format((float) ($p['price'] ?? 0), 2) ?></td>
                            <td>
                                <span class="badge <?= $isAvail ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $isAvail ? 'Disponible' : 'No disponible' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary me-1"
                                    @click="openEdit(<?= $editData ?>)"
                                    aria-label="Editar <?= $pName ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm <?= $isAvail ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1"
                                    @click="toggle(<?= $pid ?>)"
                                    aria-label="<?= $isAvail ? 'Desactivar' : 'Activar' ?> <?= $pName ?>">
                                    <i class="bi <?= $isAvail ? 'bi-eye-slash' : 'bi-eye' ?>" aria-hidden="true"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="confirmDelete(<?= $pid ?>, '<?= $pName ?>')"
                                    aria-label="Eliminar <?= $pName ?>">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal crear/editar -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel" x-text="form.id ? 'Editar Producto' : 'Nuevo Producto'">Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <template x-if="formErrors.length > 0">
                        <div class="alert alert-danger py-2">
                            <ul class="mb-0 ps-3">
                                <template x-for="e in formErrors" :key="e">
                                    <li x-text="e"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <div class="mb-3">
                        <label for="pm-name" class="form-label">Nombre *</label>
                        <input type="text" id="pm-name" class="form-control" x-model="form.name" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="pm-price" class="form-label">Precio (€) *</label>
                            <input type="number" id="pm-price" step="0.01" min="0" class="form-control" x-model="form.price" required>
                        </div>
                        <div class="col-md-6">
                            <label for="pm-category" class="form-label">Categoría</label>
                            <select id="pm-category" class="form-select" x-model="form.category_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>">
                                    <?= htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="pm-desc" class="form-label">Descripción</label>
                        <textarea id="pm-desc" class="form-control" rows="3" x-model="form.description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" @click="saveProduct()" :disabled="saving">
                        <span x-show="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        <span x-text="form.id ? 'Guardar cambios' : 'Crear producto'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal confirmación borrado -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteModalLabel">¿Eliminar producto?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    ¿Seguro que quieres eliminar "<strong x-text="deleteTarget.name"></strong>"?
                    Esta acción no se puede deshacer.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" @click="doDelete()">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

</div>
