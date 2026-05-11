<?php

declare(strict_types=1);

/**
 * Modal de normas de visita — Alpine.js
 *
 * Se activa con el evento personalizado 'open-visit-rules-modal'.
 * Incluir globalmente en layouts/main.php.
 */
?>
<div
    x-data="{ open: false, checked: [] }"
    @open-visit-rules-modal.window="open = true; checked = []"
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-visit-rules-title"
    class="visit-rules-modal-overlay"
    @keydown.escape.window="open = false"
    style="display: none;">
    <div
        class="visit-rules-modal-panel"
        @click.stop
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100">
        <!-- Header -->
        <div class="visit-rules-modal__header">
            <h2 id="modal-visit-rules-title" class="visit-rules-modal__title">
                <span aria-hidden="true">📋</span> Normas de visita
            </h2>
            <button
                type="button"
                class="visit-rules-modal__close"
                @click="open = false"
                aria-label="Cerrar normas de visita">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Checklist -->
        <p class="visit-rules-modal__intro">
            Marca cada punto para confirmar que has leído las normas antes de reservar.
        </p>

        <ul class="visit-rules-modal__checklist" role="list">
            <li>
                <label class="visit-rules-modal__check-label">
                    <input type="checkbox" x-model="checked" value="age" class="visit-rules-modal__checkbox">
                    <span>He verificado que todos los visitantes cumplen con la edad mínima indicada</span>
                </label>
            </li>
            <li>
                <label class="visit-rules-modal__check-label">
                    <input type="checkbox" x-model="checked" value="food" class="visit-rules-modal__checkbox">
                    <span>No traeré comida ni bebida del exterior</span>
                </label>
            </li>
            <li>
                <label class="visit-rules-modal__check-label">
                    <input type="checkbox" x-model="checked" value="gentle" class="visit-rules-modal__checkbox">
                    <span>Trataré a los animales con calma y respeto — sin perseguirlos ni despertarlos</span>
                </label>
            </li>
            <li>
                <label class="visit-rules-modal__check-label">
                    <input type="checkbox" x-model="checked" value="reservation" class="visit-rules-modal__checkbox">
                    <span>Entiendo que la reserva es obligatoria y me comprometo a llegar puntual</span>
                </label>
            </li>
        </ul>

        <!-- Indicador de progreso -->
        <p class="visit-rules-modal__progress" aria-live="polite">
            <span x-text="checked.length"></span> de 4 normas confirmadas
        </p>

        <!-- Acciones -->
        <div class="visit-rules-modal__actions">
            <button
                type="button"
                class="visit-rules-modal__btn-accept"
                :disabled="checked.length < 4"
                :aria-disabled="checked.length < 4 ? 'true' : 'false'"
                @click="if(checked.length === 4) { open = false; $dispatch('visit-rules-accepted') }">
                He leído y acepto las normas
            </button>
            <button
                type="button"
                class="visit-rules-modal__btn-cancel"
                @click="open = false">
                Cancelar
            </button>
        </div>
    </div>

    <!-- Overlay backdrop -->
    <div class="visit-rules-modal__backdrop" @click="open = false" aria-hidden="true"></div>
</div>
