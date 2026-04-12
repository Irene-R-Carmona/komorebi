<?php

/**
 * Gestión de Productos — Vista Manager
 *
 * Variables:
 * @var \App\Core\Raw $alpineConfig  JSON config para Alpine: {products, categories, cafeId, csrfToken}
 * @var int $total                   Total de productos
 */
?>
<div class="container-fluid" x-data='managerProducts(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-bag text-primary"></i> Gestión de Productos
            </h1>
            <p class="text-muted mb-0">Catálogo de productos de tu café</p>
        </div>
        <button class="btn btn-primary" @click="openCreate()">
            <i class="bi bi-plus-circle"></i> Nuevo Producto
        </button>
    </div>

    <!-- Toast feedback -->
    <div x-show="toast.visible" x-transition
        :class="'alert alert-' + (toast.success ? 'success' : 'danger') + ' position-fixed top-0 end-0 m-3'"
        style="z-index:9999; min-width:280px" x-cloak>
        <span x-text="toast.message"></span>
    </div>

    <!-- Tabla de productos -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Productos (<span x-text="products.length"></span>)</h5>
            <input type="search" class="form-control form-control-sm w-auto"
                placeholder="Buscar..." x-model="search">
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
                        <template x-for="p in filteredProducts" :key="p.id">
                            <tr>
                                <td class="fw-semibold" x-text="p.name"></td>
                                <td x-text="p.category_name ?? '—'"></td>
                                <td x-text="'€' + parseFloat(p.price).toFixed(2)"></td>
                                <td>
                                    <span :class="p.is_available ? 'badge bg-success' : 'badge bg-secondary'"
                                        x-text="p.is_available ? 'Disponible' : 'No disponible'">
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(p)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button :class="p.is_available ? 'btn btn-sm btn-outline-warning me-1' : 'btn btn-sm btn-outline-success me-1'"
                                        @click="toggle(p)">
                                        <i :class="p.is_available ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" @click="confirmDelete(p)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredProducts.length === 0">
                            <td colspan="5" class="text-center text-muted py-4">No hay productos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal crear/editar (Bootstrap modal) -->
    <div class="modal fade" id="productModal" tabindex="-1" x-ref="productModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="form.id ? 'Editar Producto' : 'Nuevo Producto'"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-control" x-model="form.name" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Precio (€) *</label>
                            <input type="number" step="0.01" min="0" class="form-control" x-model="form.price" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" x-model="form.category_id">
                                <option value="">Sin categoría</option>
                                <template x-for="cat in categories" :key="cat.id">
                                    <option :value="cat.id" x-text="cat.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" rows="3" x-model="form.description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" @click="saveProduct()" :disabled="saving">
                        <span x-show="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <span x-text="form.id ? 'Guardar cambios' : 'Crear producto'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal confirmación borrado -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">¿Eliminar producto?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ¿Seguro que quieres eliminar "<strong x-text="deleteTarget?.name"></strong>"?
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

<script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
    window.managerProducts = function(config) {
        return {
            products: config.products ?? [],
            categories: config.categories ?? [],
            csrfToken: config.csrfToken,
            search: '',
            saving: false,
            deleteTarget: null,
            form: {
                id: null,
                name: '',
                price: '',
                category_id: '',
                description: ''
            },
            toast: {
                visible: false,
                message: '',
                success: true
            },

            get filteredProducts() {
                const q = this.search.toLowerCase();
                return q ?
                    this.products.filter(p => p.name.toLowerCase().includes(q)) :
                    this.products;
            },

            openCreate() {
                this.form = {
                    id: null,
                    name: '',
                    price: '',
                    category_id: '',
                    description: ''
                };
                new bootstrap.Modal(document.getElementById('productModal')).show();
            },

            openEdit(p) {
                this.form = {
                    id: p.id,
                    name: p.name,
                    price: p.price,
                    category_id: p.category_id ?? '',
                    description: p.description ?? ''
                };
                new bootstrap.Modal(document.getElementById('productModal')).show();
            },

            async saveProduct() {
                this.saving = true;
                const url = this.form.id ?
                    `/manager/products/${this.form.id}/update` :
                    '/manager/products/create';

                const body = new FormData();
                body.append('csrf_token', this.csrfToken);
                Object.entries(this.form).forEach(([k, v]) => body.append(k, v ?? ''));

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        body
                    });
                    const json = await res.json();
                    if (json.ok) {
                        this.showToast(json.data.message, true);
                        bootstrap.Modal.getInstance(document.getElementById('productModal'))?.hide();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        this.showToast(json.error ?? 'Error desconocido', false);
                    }
                } catch {
                    this.showToast('Error de red', false);
                } finally {
                    this.saving = false;
                }
            },

            async toggle(p) {
                const body = new FormData();
                body.append('csrf_token', this.csrfToken);
                try {
                    const res = await fetch(`/manager/products/${p.id}/toggle`, {
                        method: 'POST',
                        body
                    });
                    const json = await res.json();
                    if (json.ok) {
                        p.is_available = !p.is_available;
                        this.showToast(json.data.message, true);
                    } else {
                        this.showToast(json.error ?? 'Error', false);
                    }
                } catch {
                    this.showToast('Error de red', false);
                }
            },

            confirmDelete(p) {
                this.deleteTarget = p;
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            },

            async doDelete() {
                if (!this.deleteTarget) return;
                const body = new FormData();
                body.append('csrf_token', this.csrfToken);
                try {
                    const res = await fetch(`/manager/products/${this.deleteTarget.id}/delete`, {
                        method: 'POST',
                        body
                    });
                    const json = await res.json();
                    if (json.ok) {
                        this.products = this.products.filter(p => p.id !== this.deleteTarget.id);
                        this.showToast(json.data.message, true);
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal'))?.hide();
                    } else {
                        this.showToast(json.error ?? 'Error', false);
                    }
                } catch {
                    this.showToast('Error de red', false);
                }
            },

            showToast(msg, ok) {
                this.toast = {
                    visible: true,
                    message: msg,
                    success: ok
                };
                setTimeout(() => {
                    this.toast.visible = false;
                }, 3000);
            },
        };
    };
</script>
