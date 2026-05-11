<?php

/**
 * Partial: SOP Modal
 * Modal que muestra Standard Operating Procedures para preparar productos.
 * Usa el scope de kdsApp (x-data en el layout) — NO tiene x-data propio.
 * Se activa mediante evento Alpine: $dispatch('show-sop', { title, steps, ingred, check, allergens })
 * Clases CSS: public/css/workspaces/kds-sop.css (Blueprint split-view)
 */
?>
<div class="sop-modal-overlay"
    @show-sop.window="openSop($event.detail)"
    @keydown.escape.window="closeSop()"
    x-show="sopOpen"
    x-cloak
    style="display:none;">

    <div class="sop-container">

        <!-- Header -->
        <div class="sop-header">
            <div class="sop-title-group">
                <div class="sop-icon-box">
                    <span class="material-symbols-outlined" aria-hidden="true">menu_book</span>
                </div>
                <div class="sop-title">
                    <h1 x-text="sopData.title || 'Procedimiento'"></h1>
                    <div class="sop-meta">
                        <span class="sop-badge" x-text="sopData.station || 'General'"></span>
                        <span x-show="sopData.ingred.length > 0"
                            x-text="sopData.ingred.length + ' ingredientes'"></span>
                        <span x-show="sopData.steps.length > 0"
                            x-text="sopData.steps.length + ' pasos'"></span>
                    </div>
                </div>
            </div>
            <button class="btn-close-sop"
                @click="closeSop()"
                type="button"
                aria-label="Cerrar procedimiento">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <!-- Body split-view -->
        <div class="sop-body">

            <!-- LEFT: Mise en Place (ingredientes con checklist) -->
            <div class="sop-mise">
                <div class="panel-header">
                    <span class="panel-title">
                        <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>
                        Mise en Place
                    </span>
                    <span class="panel-count"
                        x-text="sopData.ingred.filter(i => i.checked).length + '/' + sopData.ingred.length"></span>
                </div>
                <div class="mise-list">
                    <template x-for="(item, idx) in sopData.ingred" :key="idx">
                        <div class="mise-item"
                            :class="{ checked: item.checked }"
                            @click="toggleMise(idx)"
                            role="checkbox"
                            :aria-checked="item.checked"
                            tabindex="0"
                            @keydown.space.prevent="toggleMise(idx)">
                            <div class="mise-check" aria-hidden="true">
                                <span class="material-symbols-outlined" style="font-size:16px">check</span>
                            </div>
                            <div class="mise-text">
                                <h4 x-text="item.name"></h4>
                            </div>
                        </div>
                    </template>
                    <div x-show="sopData.ingred.length === 0"
                        style="color:var(--sop-text-muted);padding:1rem;text-align:center;font-size:.9rem;">
                        Sin ingredientes documentados
                    </div>
                </div>
            </div>

            <!-- RIGHT: Ejecución (pasos secuenciales) -->
            <div class="sop-execution">
                <div class="panel-header">
                    <span class="panel-title">
                        <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                        Ejecución
                    </span>
                    <span class="panel-count"
                        x-text="(sopData.steps.findIndex(s => s.active) + 1) + '/' + sopData.steps.length"></span>
                </div>
                <div class="exec-list">
                    <template x-for="(step, idx) in sopData.steps" :key="idx">
                        <div class="step-card"
                            :class="{ active: step.active }"
                            @click="activateStep(idx)"
                            role="button"
                            :aria-current="step.active ? 'step' : false"
                            tabindex="0"
                            @keydown.enter="activateStep(idx)">
                            <div class="step-num" x-text="idx + 1" aria-hidden="true"></div>
                            <p class="step-title" x-text="step.text"></p>
                        </div>
                    </template>
                    <div x-show="sopData.steps.length === 0"
                        style="color:var(--sop-text-muted);padding:1rem;text-align:center;font-size:.9rem;">
                        Sin pasos documentados
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer: HACCP + Alérgenos -->
        <div class="sop-footer">
            <div class="haccp-stripe" aria-hidden="true"></div>
            <div class="haccp-content">
                <div class="haccp-alert">
                    <div class="haccp-icon" aria-hidden="true">
                        <span class="material-symbols-outlined" style="font-size:32px">warning</span>
                    </div>
                    <div class="haccp-text">
                        <h4>Punto Crítico HACCP</h4>
                        <p x-show="sopData.check" x-text="sopData.check"></p>
                        <p x-show="!sopData.check" style="opacity:.5">Sin alertas HACCP para este producto.</p>
                    </div>
                </div>
                <div class="haccp-allergens" x-show="sopData.allergens && sopData.allergens.length > 0">
                    <template x-for="a in sopData.allergens" :key="a.code">
                        <span class="allergen-tag"
                            :style="'background:' + (a.color || '#1f2937') + '; color:' + getContrastColor(a.color || '#1f2937')"
                            x-text="a.name"></span>
                    </template>
                </div>
            </div>
        </div>

    </div>
</div>
