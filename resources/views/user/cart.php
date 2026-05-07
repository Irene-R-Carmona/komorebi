<?php

/**
 * Vista: Mi Carrito
 * Consume GET /api/cart, PATCH /api/cart/items/{id}, DELETE /api/cart/items/{id}, DELETE /api/cart/items vía Alpine.js.
 *
 * @var string $csrfToken  Token CSRF para peticiones POST
 */
?>
<div class="container py-4" x-data="userCart('<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>')" x-init="loadCart()">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-cart-fill text-primary"></i> Mi Carrito</h1>
        <span class="badge bg-primary" x-text="items.length + ' artículos'" x-show="items.length > 0" x-cloak></span>
    </div>

    <!-- Loading -->
    <div class="text-center py-5" x-show="loading" x-cloak>
        <div class="spinner-border text-primary"></div>
        <p class="mt-2 text-muted">Cargando carrito...</p>
    </div>

    <!-- Empty state -->
    <div class="text-center py-5" x-show="!loading && items.length === 0" x-cloak>
        <i class="bi bi-cart display-1 text-muted"></i>
        <p class="mt-3 text-muted">Tu carrito está vacío.</p>
        <a href="/cafes" class="btn btn-primary">Explorar cafés</a>
    </div>

    <!-- Carrito con contenido -->
    <div class="row g-4" x-show="!loading && items.length > 0" x-cloak>
        <!-- Tabla de productos -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in items" :key="item.id">
                                <tr>
                                    <td>
                                        <div class="fw-semibold" x-text="item.name"></div>
                                        <div class="text-muted small" x-text="item.cafe_name ?? ''"></div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                @click="updateQty(item, item.quantity - 1)"
                                                :disabled="item.quantity <= 1">−</button>
                                            <span class="fw-bold" x-text="item.quantity"></span>
                                            <button class="btn btn-sm btn-outline-secondary"
                                                @click="updateQty(item, item.quantity + 1)">+</button>
                                        </div>
                                    </td>
                                    <td class="text-end" x-text="'¥' + parseFloat(item.price).toFixed(0)"></td>
                                    <td class="text-end fw-bold"
                                        x-text="'¥' + (item.price * item.quantity).toFixed(0)">
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger"
                                            @click="removeItem(item.product_id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-sm btn-outline-danger" @click="clearCart()">
                        <i class="bi bi-trash"></i> Vaciar carrito
                    </button>
                </div>
            </div>
        </div>

        <!-- Resumen del carrito -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Resumen del pedido</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Productos (<span x-text="items.length"></span>)</span>
                        <span x-text="'¥' + total.toFixed(0)"></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span x-text="'¥' + total.toFixed(0)"></span>
                    </div>
                    <button class="btn btn-primary w-100 mt-3">
                        Proceder al pago
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast feedback -->
    <div x-show="toast.visible" x-transition
        class="alert position-fixed bottom-0 end-0 m-3"
        :class="toast.success ? 'alert-success' : 'alert-danger'"
        style="z-index:9999" x-cloak>
        <span x-text="toast.message"></span>
    </div>
</div>

<script nonce="<?= $cspNonce ?? '' ?>">
    window.userCart = function(csrfToken) {
        return {
            items: [],
            loading: true,
            toast: {
                visible: false,
                message: '',
                success: true
            },

            get total() {
                return this.items.reduce((sum, i) => sum + (parseFloat(i.price) * i.quantity), 0);
            },

            async loadCart() {
                try {
                    const res = await fetch('/api/v1/cart', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json();
                    this.items = json.data?.items ?? [];
                } catch {
                    this.showToast('Error al cargar el carrito', false);
                } finally {
                    this.loading = false;
                }
            },

            async updateQty(item, newQty) {
                if (newQty < 1) return;
                const delta = newQty - item.quantity;
                try {
                    const res = await fetch(`/api/v1/cart/items/${item.product_id}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            change: delta
                        })
                    });
                    const json = await res.json();
                    if (json.ok) {
                        item.quantity = newQty;
                    } else {
                        this.showToast(json.error ?? 'Error', false);
                    }
                } catch {
                    this.showToast('Error de red', false);
                }
            },

            async removeItem(itemId) {
                try {
                    const res = await fetch(`/api/v1/cart/items/${itemId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-Token': csrfToken
                        }
                    });
                    const json = await res.json();
                    if (json.ok) {
                        this.items = this.items.filter(i => i.product_id !== itemId);
                        this.showToast('Artículo eliminado', true);
                    } else {
                        this.showToast(json.error ?? 'Error', false);
                    }
                } catch {
                    this.showToast('Error de red', false);
                }
            },

            async clearCart() {
                try {
                    const res = await fetch('/api/v1/cart/items', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-Token': csrfToken
                        }
                    });
                    const json = await res.json();
                    if (json.ok) {
                        this.items = [];
                        this.showToast('Carrito vaciado', true);
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
