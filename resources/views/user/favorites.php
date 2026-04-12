<?php

/**
 * Vista: Mis Favoritos
 * Consume GET /api/favorites y POST /api/favorites/toggle vía Alpine.js.
 *
 * @var string $csrfToken  Token CSRF para peticiones POST
 */
?>
<div class="container py-4" x-data="userFavorites('<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>')" x-init="loadFavorites()">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-heart-fill text-danger"></i> Mis Favoritos</h1>
        <span class="text-muted" x-text="favorites.length + ' cafés guardados'"></span>
    </div>

    <!-- Loading -->
    <div class="text-center py-5" x-show="loading" x-cloak>
        <div class="spinner-border text-primary"></div>
        <p class="mt-2 text-muted">Cargando favoritos...</p>
    </div>

    <!-- Empty state -->
    <div class="text-center py-5" x-show="!loading && favorites.length === 0" x-cloak>
        <i class="bi bi-heart display-1 text-muted"></i>
        <p class="mt-3 text-muted">Todavía no tienes cafés favoritos.</p>
        <a href="/cafes" class="btn btn-primary">Explorar cafés</a>
    </div>

    <!-- Grid de favoritos -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" x-show="!loading && favorites.length > 0" x-cloak>
        <template x-for="cafe in favorites" :key="cafe.id">
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title" x-text="cafe.name"></h5>
                        <p class="card-text text-muted small" x-text="cafe.description ?? ''"></p>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <a :href="'/cafes/' + cafe.slug" class="btn btn-sm btn-outline-primary">Ver café</a>
                        <button class="btn btn-sm btn-outline-danger" @click="removeFavorite(cafe.id)">
                            <i class="bi bi-heart-fill"></i> Quitar
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Toast feedback -->
    <div x-show="toast.visible" x-transition
        class="alert position-fixed bottom-0 end-0 m-3"
        :class="toast.success ? 'alert-success' : 'alert-danger'"
        style="z-index:9999" x-cloak>
        <span x-text="toast.message"></span>
    </div>
</div>

<script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
    window.userFavorites = function(csrfToken) {
        return {
            favorites: [],
            loading: true,
            toast: {
                visible: false,
                message: '',
                success: true
            },

            async loadFavorites() {
                try {
                    const res = await fetch('/api/favorites', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const json = await res.json();
                    this.favorites = json.data ?? [];
                } catch {
                    this.showToast('Error al cargar favoritos', false);
                } finally {
                    this.loading = false;
                }
            },

            async removeFavorite(cafeId) {
                const body = new FormData();
                body.append('csrf_token', csrfToken);
                body.append('cafe_id', cafeId);
                try {
                    const res = await fetch('/api/favorites/toggle', {
                        method: 'POST',
                        body
                    });
                    const json = await res.json();
                    if (json.ok) {
                        this.favorites = this.favorites.filter(f => f.id !== cafeId);
                        this.showToast('Favorito eliminado', true);
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
