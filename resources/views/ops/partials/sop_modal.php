<div id="sopModal" class="sop-modal-overlay" style="display:none;" x-show="sopOpen" x-transition.opacity.duration.200ms>
    <div class="sop-container" @click.outside="sopOpen = false">

        <!-- HEADER -->
        <header class="sop-header">
            <div class="sop-title-group">
                <div class="sop-icon-box">
                    <span class="material-symbols-outlined" style="font-size:32px">restaurant_menu</span>
                </div>
                <div>
                    <h1 x-text="sopData.title"></h1>
                    <div class="sop-meta">
                        <span class="sop-badge">SOP #<?= date('Y') ?></span>
                        <span>•</span>
                        <span>Station: <span x-text="sopData.station"></span></span>
                    </div>
                </div>
            </div>

            <div class="flex" style="align-items:center; gap:1.5rem;">
                <!-- Timer Simulado (Visual) -->
                <div class="sop-timer">
                    <div style="text-align:center;">
                        <span class="timer-digit">05</span>
                        <span class="timer-lbl">MIN</span>
                    </div>
                    <span style="font-size:1.5rem; font-weight:700; color:#666">:</span>
                    <div style="text-align:center;">
                        <span class="timer-digit">00</span>
                        <span class="timer-lbl">SEC</span>
                    </div>
                </div>
                <button class="btn-close-sop" @click="sopOpen = false">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </header>

        <!-- BODY -->
        <div class="sop-body">

            <!-- LEFT: INGREDIENTES -->
            <section class="sop-mise">
                <div class="panel-header">
                    <div class="panel-title" style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined" style="color:var(--sop-primary)">inventory_2</span>
                        Mise-en-place
                    </div>
                    <span class="panel-count" x-text="sopData.ingred.length + ' Items'"></span>
                </div>

                <div class="mise-list">
                    <template x-for="(ing, idx) in sopData.ingred">
                        <div class="mise-item" @click="$el.classList.toggle('checked')">
                            <div class="mise-check">
                                <span class="material-symbols-outlined" style="font-size:16px">check</span>
                            </div>
                            <div class="mise-text">
                                <h4 x-text="ing"></h4>
                                <p>Check quality / quantity</p>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- RIGHT: EXECUTION -->
            <section class="sop-execution">
                <div class="panel-header">
                    <div class="panel-title" style="display:flex; align-items:center; gap:8px;">
                        <span class="material-symbols-outlined" style="color:var(--sop-primary)">play_circle</span>
                        Execution Steps
                    </div>
                    <span class="sop-badge">METHOD: STANDARD</span>
                </div>

                <div class="exec-list">
                    <template x-for="(step, idx) in sopData.steps">
                        <div class="step-card" :class="{ 'active': idx === 0 }">
                            <div class="step-num" x-text="idx + 1"></div>
                            <div class="step-content">
                                <h4 class="step-title">Step <span x-text="idx + 1"></span></h4>
                                <p class="step-desc" x-text="step"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

        </div>

        <!-- FOOTER: HACCP -->
        <footer class="sop-footer">
            <div class="haccp-stripe"></div>
            <div class="haccp-content">
                <div class="haccp-alert">
                    <div class="haccp-icon">
                        <span class="material-symbols-outlined" style="font-size:36px">warning</span>
                    </div>
                    <div class="haccp-text">
                        <h4>Critical Control Point</h4>
                        <p x-text="sopData.check || 'Standard hygiene protocols apply.'"></p>
                    </div>
                </div>

                <div class="haccp-allergens">
                    <span style="color:#666; font-size:0.7rem; font-weight:700; text-transform:uppercase; margin-right:10px; align-self:center;">ALLERGENS</span>
                    <template x-for="alg in sopData.allergens">
                        <span class="allergen-tag" x-text="alg"></span>
                    </template>
                </div>
            </div>
            <div class="haccp-stripe"></div>
        </footer>

    </div>
</div>