<?php

use App\Core\Csrf;

?>
<div style="display: contents;" x-data="receptionApp()">

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

                        // Color: Verde (<50m) -> Naranja (50-60m) -> Rojo (>60m)
                        $color = '#87a77b'; // Verde
                        if ($elapsed > 50) {
                            $color = '#f59e0b';
                        } // Naranja
                        if ($elapsed > 60) {
                            $color = '#ef4444';
                        } // Rojo
                    ?>
                        <div class="zen-table">
                            <!-- ANILLO CONIC-GRADIENT (Fix Visual) -->
                            <div class="table-ring"
                                style="background: conic-gradient(<?= $color ?> <?= $deg ?>deg, #e5e7eb 0deg); border-radius:50%;">

                                <!-- Círculo interior para tapar el centro y crear anillo -->
                                <div style="position:absolute; inset:6px; background:var(--rec-bg); border-radius:50%;"></div>

                                <div class="table-surface">
                                    <span class="table-id">#<?= e($g['tracker_code'] ?? '?') ?></span>
                                    <span class="table-pax"><?= $g['guest_count'] ?></span>
                                    <span class="table-status" style="color:<?= $color ?>">
                                        <?= round($elapsed) ?> min
                                    </span>
                                </div>
                            </div>

                            <p class="table-label"><?= e($g['user_name']) ?></p>

                            <button type="button" class="btn-edit"
                                @click="submitCheckout(<?= $g['id'] ?>)"
                                :disabled="loading"
                                style="font-size:0.75rem; padding:4px 8px; margin-top:5px;">
                                Checkout
                            </button>
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

</div>
