<?php

/**
 * Partial: SOP Modal
 * Modal que muestra Standard Operating Procedures para preparar productos
 * Se activa mediante evento Alpine: $dispatch('show-sop', { sop: {...} })
 */
?>
<div class="kds-modal"
    x-data="{
         show: false,
         sop: { title: '', steps: '', ingred: [], check: '' },
         open(data) {
             this.sop = data.sop || {};
             this.show = true;
         },
         close() {
             this.show = false;
         }
     }"
    @show-sop.window="open($event.detail)"
    @keydown.escape.window="close()"
    x-show="show"
    x-cloak
    style="display: none;">

    <!-- Overlay -->
    <div class="kds-modal__overlay"
        @click="close()"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>

    <!-- Modal Content -->
    <div class="kds-modal__content"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95"
        @click.away="close()">

        <!-- Header -->
        <div class="kds-modal__header">
            <h2 class="kds-modal__title">
                <span class="material-symbols-outlined">menu_book</span>
                <span x-text="sop.title || 'Procedimiento'"></span>
            </h2>
            <button class="kds-modal__close" @click="close()" type="button">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="kds-modal__body">

            <!-- Recipe Steps -->
            <div x-show="sop.steps" class="sop-section">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">task_alt</span>
                    Pasos de Preparación
                </h3>
                <div class="sop-section__content" x-html="sop.steps || ''"></div>
            </div>

            <!-- Ingredients -->
            <div x-show="sop.ingred && sop.ingred.length > 0" class="sop-section">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">inventory_2</span>
                    Ingredientes
                </h3>
                <ul class="sop-ingredients">
                    <template x-for="ingr in sop.ingred" :key="ingr">
                        <li x-text="ingr"></li>
                    </template>
                </ul>
            </div>

            <!-- Critical Check (HACCP) -->
            <div x-show="sop.check" class="sop-section sop-section--critical">
                <h3 class="sop-section__title">
                    <span class="material-symbols-outlined">warning</span>
                    Punto Crítico de Control (HACCP)
                </h3>
                <div class="sop-section__content sop-section__content--alert" x-text="sop.check"></div>
            </div>

            <!-- Empty State -->
            <div x-show="!sop.steps && (!sop.ingred || sop.ingred.length === 0) && !sop.check"
                class="sop-empty">
                <span class="material-symbols-outlined">info</span>
                <p>No hay procedimientos documentados para este producto.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="kds-modal__footer">
            <button class="kds-btn kds-btn--primary" @click="close()" type="button">
                Entendido
            </button>
        </div>
    </div>
</div>
