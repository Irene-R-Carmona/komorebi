<?php
// Umbrales de tiempo de estancia (minutos) para colorizar el anillo de estado
$warnThresholdMins = 50;   // Verde → Naranja
$dangerThresholdMins = 60; // Naranja → Rojo
?>
<div style="display: contents;" x-data="receptionApp" data-orderable-items='<?= $orderable_items_json ?>'>

    <!-- SIDEBAR: LLEGADAS -->
    <aside class="zen-sidebar">
        <div class="zen-brand">
            <div class="brand-logo">
                <img src="/images/logos/komorebi-logo-icon.svg" class="brand-icon-img" width="40" height="40" alt="">
                <div class="brand-text">
                    <h1>Komorebi</h1>
                    <p>Reception</p>
                </div>
            </div>
        </div>

        <div class="zen-list">
            <div class="list-section-title">
                <h3>Llegadas</h3>
                <span><?= date('d M') ?></span>
            </div>

            <?php if (empty($reservas)): ?>
                <div style="text-align:center; padding:2rem; opacity:0.5; font-style:italic;">
                    <p>No hay llegadas pendientes.</p>
                </div>
            <?php else: ?>
                <?php foreach (
                    $reservas as $r
                ): ?>
                    <div class="guest-card" @click="openCheckin(<?= $r['id'] ?>)">
                        <div class="guest-time">
                            <span class="time-val"><?= $r['ui_time'] ?></span>
                            <span class="time-kanji">時</span>
                        </div>
                        <div class="guest-info">
                            <div class="guest-avatar"
                                style="display:flex; align-items:center; justify-content:center; font-weight:bold; color:#666;">
                                <?= strtoupper(substr($r['user_name'], 0, 1)) ?>
                            </div>
                            <div class="guest-details">
                                <h4><?= e($r['user_name']) ?></h4>
                                <div class="guest-meta">
                                    <span><?= $r['guest_count'] ?> Pax</span>
                                    <?php if ($r['ui_state'] === 'late'): ?>
                                        <span style="color:#ef4444; font-weight:bold; margin-left:5px;">RETRASO</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- MAIN AREA: SALA VIVA -->
    <main class="zen-main">
        <header class="main-header">
            <div class="header-title">
                <h2>Sala Principal</h2>
                <div class="header-date">
                    <span class="material-symbols-outlined" style="font-size:16px;">storefront</span>
                    <?= e($_SESSION['user_cafe_name'] ?? 'Sede') ?>
                </div>
            </div>

            <div class="header-stats">
                <div class="stat-box">
                    <span class="stat-label">Aforo</span>
                    <span class="stat-val"><?= $ocupacion ?>/<?= $cap_max ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Grupos</span>
                    <span class="stat-val"><?= count($active_groups) ?></span>
                </div>
            </div>
        </header>

        <div class="floor-container no-scrollbar">
            <?php if (empty($active_groups)): ?>
                <div style="height:100%; display:flex; align-items:center; justify-content:center; opacity:0.3; flex-direction:column;">
                    <span class="material-symbols-outlined" style="font-size:4rem;">weekend</span>
                    <h3>Sala Vacía</h3>
                </div>
            <?php else: ?>
                <div class="tables-grid">
                    <?php foreach ($active_groups as $g):
                        // LÓGICA DE TIEMPO
                        $inicio = strtotime($g['check_in_at']);
                        $elapsed = (time() - $inicio) / 60;
                        $deg = min(360, ($elapsed / 60) * 360);

                        // Color: Verde (<warnThresholdMins) -> Naranja (<dangerThresholdMins) -> Rojo (>dangerThresholdMins)
                        $timeClass = 'time-ok';
                        if ($elapsed > $warnThresholdMins) {
                            $timeClass = 'time-warn';
                        }
                        if ($elapsed > $dangerThresholdMins) {
                            $timeClass = 'time-danger';
                        }
                        ?>
                        <div class="zen-table">
                            <!-- ANILLO CONIC-GRADIENT (Fix Visual) -->
                            <div class="table-ring table-ring--<?= $timeClass ?>"
                                style="background: conic-gradient(var(--_ring-color) <?= $deg ?>deg, #e5e7eb 0deg); border-radius:50%;">

                                <!-- Círculo interior para tapar el centro y crear anillo -->
                                <div style="position:absolute; inset:6px; background:var(--rec-bg); border-radius:50%;"></div>

                                <div class="table-surface">
                                    <span class="table-id">#<?= e($g['tracker_code'] ?? '?') ?></span>
                                    <span class="table-pax"><?= $g['guest_count'] ?></span>
                                    <span class="table-status text-<?= $timeClass ?>">
                                        <?= round($elapsed) ?> min
                                    </span>
                                </div>
                            </div>

                            <p class="table-label"><?= e($g['user_name']) ?></p>

                            <?php if (($g['items_count'] ?? 0) > 0): ?>
                                <p style="font-size:0.7rem; color:var(--rec-muted,#888); margin:2px 0 0;">
                                    <?= (int) $g['items_count'] ?> artículo<?= (int) $g['items_count'] !== 1 ? 's' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div style="display:flex; gap:4px; margin-top:5px;">
                                <button type="button" class="btn-edit"
                                    @click="openPos(<?= $g['id'] ?>)"
                                    :disabled="loading"
                                    style="font-size:0.72rem; padding:4px 8px;">
                                    Añadir pedido
                                </button>
                                <button type="button" class="btn-confirm"
                                    @click="openCobro(<?= $g['id'] ?>)"
                                    :disabled="loading"
                                    style="font-size:0.72rem; padding:4px 8px;">
                                    Cobrar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL WELCOME (Check-in) -->
    <div class="welcome-modal" x-show="checkinOpen" x-transition style="display:none;">
        <div class="modal-backdrop" @click="closeCheckin()"></div>

        <div class="welcome-card">
            <div class="stamp-seal">
                <div class="seal-circle">
                    <div class="seal-inner"><span class="material-symbols-outlined">spa</span></div>
                </div>
            </div>

            <div class="welcome-header">
                <p class="welcome-subtitle">Nuevo Ingreso</p>
                <h2 class="welcome-title">Bienvenido</h2>
                <p class="welcome-desc">Confirma la asignación del localizador.</p>
            </div>

            <form @submit.prevent="submitCheckin()">
                <div class="minimal-field">
                    <label class="minimal-label">Tracker / Ficha</label>
                    <select name="tracker_id" class="minimal-input" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($free_trackers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= $t['code'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading">Confirmar Entrada</button>
                    <button type="button" class="btn-edit" @click="closeCheckin()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POS (Añadir pedido) -->
    <div class="welcome-modal" x-show="posOpen" x-transition style="display:none;">
        <div class="modal-backdrop" @click="closePos()"></div>

        <div class="welcome-card">
            <div class="welcome-header">
                <p class="welcome-subtitle">Sala</p>
                <h2 class="welcome-title">Añadir pedido</h2>
                <p class="welcome-desc">Selecciona los artículos y cantidades.</p>
            </div>

            <form @submit.prevent="submitPos()">

                <!-- Líneas de pedido -->
                <template x-for="(line, idx) in posLines" :key="idx">
                    <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                        <select class="minimal-input" x-model.number="line.productId" required style="flex:1; min-width:0;">
                            <option value="">Seleccionar...</option>
                            <?php foreach (json_decode($orderable_items_json ?? '[]', true) as $item): ?>
                                <option value="<?= (int) $item['id'] ?>">
                                    <?= e($item['name']) ?>
                                    &nbsp;·&nbsp;¥<?= number_format((float) ($item['price'] ?? 0), 0, '.', ',') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" class="minimal-input" x-model.number="line.qty"
                            min="1" max="20" required style="width:60px; text-align:center; flex-shrink:0;">
                        <span style="min-width:72px; text-align:right; font-size:0.82rem; color:var(--rec-text-secondary,#6b7280); flex-shrink:0;"
                            x-text="'¥' + posLineSubtotal(line).toLocaleString('ja-JP')"></span>
                        <button type="button" @click="removePosLine(idx)"
                            x-show="posLines.length > 1"
                            style="background:none; border:none; cursor:pointer; color:var(--rec-danger,#ef4444); font-size:1.2rem; line-height:1; padding:0 2px; flex-shrink:0;"
                            title="Eliminar">×</button>
                    </div>
                </template>

                <!-- Añadir línea -->
                <button type="button" @click="addPosLine()"
                    style="background:none; border:1px dashed var(--rec-border,#d1d5db); border-radius:6px; width:100%; padding:7px; cursor:pointer; font-size:0.82rem; color:var(--rec-text-secondary,#6b7280); margin-bottom:12px;">
                    + Añadir artículo
                </button>

                <!-- Total -->
                <div style="display:flex; justify-content:space-between; align-items:baseline; padding:8px 0; border-top:1px solid var(--rec-border,#e5e7eb); margin-bottom:4px;">
                    <span style="font-size:0.85rem; font-weight:600; color:var(--rec-text,#374151);">Total</span>
                    <span style="font-size:1.05rem; font-weight:700; color:var(--rec-text,#111827);"
                        x-text="'¥' + posTotal().toLocaleString('ja-JP')"></span>
                </div>

                <p x-show="posError" x-text="posError"
                    style="color:var(--rec-danger,#ef4444); font-size:0.8rem; margin:8px 0 0;"></p>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading || !posValid()">
                        Confirmar pedido
                    </button>
                    <button type="button" class="btn-edit" @click="closePos()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL COBRO -->
    <div class="welcome-modal" x-show="cobroOpen" x-transition style="display:none;">
        <div class="modal-backdrop" @click="closeCobro()"></div>

        <div class="welcome-card">
            <div class="welcome-header">
                <p class="welcome-subtitle">Cierre de visita</p>
                <h2 class="welcome-title">Confirmar cobro</h2>
                <p class="welcome-desc">Selecciona el método de pago para cerrar la visita.</p>
            </div>

            <form @submit.prevent="submitCobro()">
                <div class="minimal-field">
                    <label class="minimal-label">Método de pago</label>
                    <select class="minimal-input" x-model="cobroMethod" required>
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                </div>

                <div class="minimal-field">
                    <label class="minimal-label">Notas (opcional)</label>
                    <input type="text" class="minimal-input" x-model="cobroNotes"
                        placeholder="p. ej. descuento aplicado" maxlength="200">
                </div>

                <p x-show="cobroError" x-text="cobroError"
                    style="color:var(--rec-danger,#ef4444); font-size:0.8rem; margin:8px 0 0;"></p>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading">
                        Confirmar cobro
                    </button>
                    <button type="button" class="btn-edit" @click="closeCobro()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

</div>
