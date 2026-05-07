<?php

/**
 * Partial: SOP Modal
 * Modal que muestra Standard Operating Procedures para preparar productos.
 * Usa el scope de kdsApp (x-data en el layout) — NO tiene x-data propio.
 * Se activa mediante evento Alpine: $dispatch('show-sop', { title, steps, ingred, check, allergens })
 */
?>
<div class="kds-modal"
    @show-sop.window="openSop($event.detail)"
    @keydown.escape.window="closeSop()"
    x-show="sopOpen"
    x-cloak
    style="display: none;">

    <!-- Overlay -->
    <div class="kds-modal__overlay"
        @click="closeSop()"
        x-show="sopOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>

    <!-- Modal Content -->
    <div class="kds-modal__content"
        x-show="sopOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        @click.away="closeSop()">

        <!-- Header -->
        <div class="kds-modal__header">
            <h2 class="kds-modal__title">
                <span class="material-symbols-outlined">menu_book</span>
                <span x-text="sopData.title || 'Procedimiento'"></span>
            </h2>
            <button class="kds-modal__close" @click="closeSop()" type="button">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="kds-modal__body">

            <!-- Recipe Steps -->
            <div x-show="sopData.steps && sopData.steps.length > 0" class="sop-section">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">task_alt</span>
                    Pasos de Preparación
                </h3>
                <ul class="sop-steps">
                    <template x-for="(s, i) in sopData.steps" :key="i">
                        <li x-text="s"></li>
                    </template>
                </ul>
            </div>

            <!-- Ingredients -->
            <div x-show="sopData.ingred && sopData.ingred.length > 0" class="sop-section">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">inventory_2</span>
                    Ingredientes
                </h3>
                <ul class="sop-ingredients">
                    <template x-for="ingr in sopData.ingred" :key="ingr">
                        <li x-text="ingr"></li>
                    </template>
                </ul>
            </div>

            <!-- Critical Check (HACCP) -->
            <div x-show="sopData.check" class="sop-section sop-section--critical">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">warning</span>
                    Punto Crítico de Control (HACCP)
                </h3>
                <div class="sop-section__content sop-section__content--alert" x-text="sopData.check"></div>
            </div>

            <!-- Allergens -->
            <div x-show="sopData.allergens && sopData.allergens.length > 0" class="sop-section sop-section--allergens">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">emergency</span>
                    Alérgenos
                </h3>
                <div class="sop-allergens">
                    <template x-for="a in sopData.allergens" :key="a.code">
                        <span class="sop-allergen-badge"
                            :style="'background-color:' + (a.color || '#ccc')"
                            x-text="a.name"></span>
                    </template>
                </div>
            </div>

            <!-- Empty State -->
            <div x-show="(!sopData.steps || sopData.steps.length === 0) && (!sopData.ingred || sopData.ingred.length === 0) && !sopData.check && (!sopData.allergens || sopData.allergens.length === 0)"
                class="sop-empty">
                <span class="material-symbols-outlined">info</span>
                <p>No hay procedimientos documentados para este producto.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="kds-modal__footer">
            <button class="kds-btn kds-btn--primary" @click="closeSop()" type="button">
                Entendido
            </button>
        </div>
    </div>
</div>
