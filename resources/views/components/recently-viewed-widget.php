<!-- Widget: Cafés Vistos Recientemente -->
<aside class="recently-viewed-widget"
    x-data="recentlyViewedWidget()"
    x-init="init()">

    <div class="widget__header">
        <h3 class="widget__title">Vistos recientemente</h3>
        <button
            class="widget__clear"
            x-show="cafes.length > 0"
            @click="clearAll()"
            type="button"
            title="Limpiar historial">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 4H14M6 2H10M6 7V12M10 7V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                <path d="M3 4L4 14H12L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>

    <!-- Loading state -->
    <div class="widget__loading" x-show="loading" x-cloak>
        <div class="spinner"></div>
        <span>Cargando...</span>
    </div>

    <!-- Empty state -->
    <div class="widget__empty" x-show="!loading && cafes.length === 0" x-cloak>
        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="2" fill="none" />
            <path d="M24 14V26M24 32V34" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <p>Aún no has visitado ningún café</p>
    </div>

    <!-- Grid de cafés -->
    <div class="widget__grid" x-show="!loading && cafes.length > 0" x-cloak>
        <template x-for="cafe in cafes" :key="cafe.id">
            <a :href="'/cafes/' + cafe.slug" class="cafe-card-mini">
                <div class="cafe-card-mini__image">
                    <img :src="cafe.image_url" :alt="cafe.name" loading="lazy">
                </div>
                <div class="cafe-card-mini__content">
                    <h4 class="cafe-card-mini__name" x-text="cafe.name"></h4>
                    <p class="cafe-card-mini__location" x-text="cafe.location"></p>
                    <div class="cafe-card-mini__rating">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                            <path d="M6 1L7.5 4.5L11 5L8.5 7.5L9 11L6 9L3 11L3.5 7.5L1 5L4.5 4.5L6 1Z" />
                        </svg>
                        <span x-text="parseFloat(cafe.price_per_hour).toFixed(1)">4.5</span>
                    </div>
                </div>
            </a>
        </template>
    </div>
</aside>

<style>
    .recently-viewed-widget {
        background: white;
        border-radius: var(--radio-md, 8px);
        padding: 1.25rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }

    .widget__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--color-acento, #c9a959);
    }

    .widget__title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--color-texto, #2c2c2c);
        margin: 0;
    }

    .widget__clear {
        display: flex;
        align-items: center;
        padding: 0.35rem;
        background: transparent;
        border: none;
        color: var(--color-texto-suave, #666);
        cursor: pointer;
        border-radius: var(--radio-sm, 4px);
        transition: all 0.2s;
    }

    .widget__clear:hover {
        background: #fff5f5;
        color: #dc3545;
    }

    .widget__loading,
    .widget__empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        text-align: center;
        color: var(--color-texto-suave, #666);
    }

    .widget__empty svg {
        color: var(--color-borde, #ddd);
        margin-bottom: 0.75rem;
    }

    .widget__empty p {
        font-size: 0.9rem;
        margin: 0;
    }

    .spinner {
        width: 24px;
        height: 24px;
        border: 3px solid var(--color-borde, #ddd);
        border-top-color: var(--color-primario, #8b4513);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-bottom: 0.5rem;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .widget__grid {
        display: grid;
        gap: 0.75rem;
    }

    .cafe-card-mini {
        display: flex;
        gap: 0.75rem;
        padding: 0.5rem;
        border-radius: var(--radio-sm, 4px);
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
        border: 1px solid transparent;
    }

    .cafe-card-mini:hover {
        background: var(--color-fondo-suave, #fafafa);
        border-color: var(--color-acento, #c9a959);
    }

    .cafe-card-mini__image {
        flex-shrink: 0;
        width: 60px;
        height: 60px;
        border-radius: var(--radio-sm, 4px);
        overflow: hidden;
        background: var(--color-fondo-suave, #f5f5f5);
    }

    .cafe-card-mini__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cafe-card-mini__content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .cafe-card-mini__name {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--color-texto, #2c2c2c);
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cafe-card-mini__location {
        font-size: 0.8rem;
        color: var(--color-texto-suave, #666);
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cafe-card-mini__rating {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        color: var(--color-acento, #c9a959);
    }

    .cafe-card-mini__rating svg {
        flex-shrink: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .recently-viewed-widget {
            padding: 1rem;
        }

        .widget__grid {
            gap: 0.5rem;
        }

        .cafe-card-mini__image {
            width: 50px;
            height: 50px;
        }
    }
</style>
