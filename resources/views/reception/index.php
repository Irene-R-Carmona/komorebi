<?php

declare(strict_types=1);

/**
 * Vista: Recepción (Dashboard de Check-in y Gestión de Sala)
 *
 * @var array $reservas
 * @var array $active_groups
 * @var array $free_trackers
 * @var string $orderable_items_json
 * @var int $ocupacion
 * @var int $cap_max
 */

$capacityAvailable = max(0, $cap_max - $ocupacion);
$isFull = $capacityAvailable <= 0;
?>

<div style="display: contents;" x-data="receptionApp"
    data-orderable-items='<?= $orderable_items_json ?>'
    data-ready-by-res='<?= $ready_by_res_json ?>'
    data-ready-items='<?= $ready_items_json ?>'>
    <!-- ────────────────────────────────────────────────────────────────
        SIDEBAR: LLEGADAS (Guestbook)
        ──────────────────────────────────────────────────────────────── -->
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
                <div class="sidebar-empty">
                    <p>No hay llegadas pendientes.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reservas as $r): ?>
                    <div class="guest-card <?= $r['ui_state'] === 'late' ? 'guest-card--late' : '' ?>"
                        @click="openCheckin(<?= $r['id'] ?>)"
                        tabindex="0"
                        @keydown.enter="openCheckin(<?= $r['id'] ?>)"
                        role="button"
                        aria-label="Check-in para <?= $r['user_name'] ?>, <?= $r['guest_count'] ?> personas">
                        <div class="guest-time">
                            <span class="time-val"><?= $r['ui_time'] ?></span>
                            <span class="time-kanji">時</span>
                        </div>
                        <div class="guest-info">
                            <div class="guest-avatar">
                                <?= strtoupper(substr($r['user_name'], 0, 1)) ?>
                            </div>
                            <div class="guest-details">
                                <h4><?= $r['user_name'] ?></h4>
                                <div class="guest-meta">
                                    <span><?= (int) $r['guest_count'] ?> Pax</span>
                                    <?php if ($r['ui_state'] === 'late'): ?>
                                        <span class="badge-late">RETRASO</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer: Botón deshabilitado (walk-in manual por ahora) -->
        <div class="sidebar-footer">
            <button
                class="btn-new-res"
                type="button"
                disabled
                title="Walk-in temporalmente en papel">
                <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                <span>Walk-in (papel)</span>
            </button>
        </div>
    </aside>

    <!-- ────────────────────────────────────────────────────────────────
        MAIN AREA: SALA VIVA (Floor Plan)
        ──────────────────────────────────────────────────────────────── -->
    <main class="zen-main">
        <header class="main-header">
            <div class="header-title">
                <h2>Sala Principal</h2>
                <div class="header-date">
                    <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
                    <?= htmlspecialchars($_SESSION['user_cafe_name'] ?? 'Sede', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div class="header-stats">
                <div class="stat-box">
                    <span class="stat-label">Aforo</span>
                    <span class="stat-val"><?= (int) $ocupacion ?>/<?= (int) $cap_max ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Grupos</span>
                    <span class="stat-val"><?= count($active_groups) ?></span>
                </div>
                <form method="POST" action="/logout" style="margin:0;">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit"
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión"
                        style="background:none;border:none;cursor:pointer;padding:0.25rem;display:flex;align-items:center;color:inherit;opacity:0.6;transition:opacity 0.2s;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                        <span class="material-symbols-outlined" style="font-size:1.4rem;">power_settings_new</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="floor-container no-scrollbar">
            <?php if (empty($active_groups)): ?>
                <div class="floor-empty">
                    <span class="material-symbols-outlined floor-empty-icon" aria-hidden="true">weekend</span>
                    <h3>Sala Vacía</h3>
                    <p>No hay mesas ocupadas en este momento</p>
                </div>
            <?php else: ?>
                <div class="tables-grid">
                    <?php
                    $warnThresholdMins = 50;
                $dangerThresholdMins = 60;

                foreach ($active_groups as $g):
                    $inicio = strtotime($g['check_in_at'] ?? 'now');
                    $elapsed = (time() - $inicio) / 60;
                    $deg = min(360, ($elapsed / 60) * 360);

                    $timeClass = 'time-ok';
                    if ($elapsed > $warnThresholdMins) {
                        $timeClass = 'time-warn';
                    }
                    if ($elapsed > $dangerThresholdMins) {
                        $timeClass = 'time-danger';
                    }
                    ?>
                        <div class="zen-table"
                            tabindex="0"
                            @keydown.enter="openCobro(<?= (int) $g['id'] ?>)"
                            role="button"
                            aria-label="Mesa <?= $g['tracker_code'] ?? '?' ?>, <?= (int) $g['guest_count'] ?> personas, <?= round($elapsed) ?> minutos">

                            <div class="table-ring table-ring--<?= $timeClass ?>"
                                style="--_deg: <?= (int) $deg ?>deg;">
                                <div class="ring-inner"></div>
                                <div class="table-surface">
                                    <span class="table-id">#<?= $g['tracker_code'] ?? '?' ?></span>
                                    <span class="table-pax"><?= (int) $g['guest_count'] ?></span>
                                    <span class="table-status table-status--<?= $timeClass ?>">
                                        <?= (int) round($elapsed) ?> min
                                    </span>
                                </div>
                            </div>

                            <p class="table-label"><?= $g['user_name'] ?? 'Sin nombre' ?></p>

                            <?php if (!empty($g['items_count'])): ?>
                                <p class="table-items">
                                    <?= (int) $g['items_count'] ?> artículo<?= (int) $g['items_count'] !== 1 ? 's' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div class="table-actions">
                                <button type="button" class="btn-table btn-table--comanda"
                                    @click="openComanda(<?= (int) $g['id'] ?>)"
                                    :disabled="loading"
                                    aria-label="Ver comanda de <?= $g['user_name'] ?? '' ?>">
                                    Comanda
                                    <span class="ready-badge"
                                        x-show="readyByRes[<?= (int) $g['id'] ?>] > 0"
                                        x-text="readyByRes[<?= (int) $g['id'] ?>]"
                                        @click.stop="toggleReadyPanel(<?= (int) $g['id'] ?>)"
                                        role="button"
                                        tabindex="0"
                                        @keydown.enter.stop="toggleReadyPanel(<?= (int) $g['id'] ?>)"
                                        aria-label="Platos listos — ver para servir"></span>
                                </button>
                                <button type="button" class="btn-table"
                                    @click="openPos(<?= (int) $g['id'] ?>)"
                                    :disabled="loading"
                                    aria-label="Añadir pedido para <?= $g['user_name'] ?? '' ?>">
                                    Pedido
                                </button>
                                <button type="button" class="btn-table btn-table--primary"
                                    @click="openCobro(<?= (int) $g['id'] ?>)"
                                    :disabled="loading"
                                    aria-label="Cobrar a <?= $g['user_name'] ?? '' ?>">
                                    Cobrar
                                </button>
                            </div>

                            <div class="ready-items-panel"
                                x-show="activeReadyPanel === <?= (int) $g['id'] ?>"
                                x-cloak>
                                <template x-for="item in (readyItemsByRes[<?= (int) $g['id'] ?>] || [])" :key="item.id">
                                    <div class="ready-item-row">
                                        <span class="ready-item-name" x-text="item.quantity + '× ' + item.product_name"></span>
                                        <button type="button" class="btn-serve"
                                            @click="serveItem(<?= (int) $g['id'] ?>, item.id)"
                                            :disabled="loading">
                                            Servir
                                        </button>
                                    </div>
                                </template>
                                <p class="ready-panel-empty"
                                    x-show="(readyItemsByRes[<?= (int) $g['id'] ?>] || []).length === 0">
                                    Cargando…
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ────────────────────────────────────────────────────────────────
        MODAL: CHECK-IN (Welcome)
        ──────────────────────────────────────────────────────────────── -->
    <div class="welcome-modal" x-show="checkinOpen" x-cloak>
        <div class="modal-backdrop" @click="closeCheckin()"></div>
        <div class="welcome-card">
            <div class="stamp-seal" aria-hidden="true">
                <div class="seal-circle">
                    <span class="material-symbols-outlined">spa</span>
                </div>
            </div>

            <div class="welcome-header">
                <p class="welcome-subtitle">Nuevo Ingreso</p>
                <h2 class="welcome-title">Bienvenido</h2>
                <p class="welcome-desc">Confirma la asignación del localizador.</p>
            </div>

            <form @submit.prevent="submitCheckin()">
                <div class="minimal-field">
                    <label class="minimal-label" for="tracker_id">Tracker / Brazalete</label>
                    <select name="tracker_id" id="tracker_id" class="minimal-input" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($free_trackers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= $t['code'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <template x-if="checkinPreOrder.length > 0">
                    <div class="pre-order-box">
                        <p class="pre-order-box__title">Pre-comanda del cliente:</p>
                        <template x-for="item in checkinPreOrder" :key="item.id">
                            <div class="pre-order-item">
                                <span x-text="item.quantity + '× ' + item.name"></span>
                                <span x-text="item.category_name"></span>
                            </div>
                        </template>
                        <div style="margin-top:10px;">
                            <template x-if="preOrderResult === null">
                                <button type="button" class="btn-confirm"
                                    :disabled="activatingPreOrder"
                                    @click.prevent="activatePreOrder()"
                                    style="width:100%; font-size:0.82rem;">
                                    <span x-show="!activatingPreOrder">Enviar pre-comanda a cocina</span>
                                    <span x-show="activatingPreOrder">Enviando&hellip;</span>
                                </button>
                            </template>
                            <template x-if="preOrderResult !== null && preOrderResult.ok">
                                <p class="message-success">
                                    ✓ <span x-text="preOrderResult.activated"></span> ítem(s) enviado(s) a cocina.
                                    <template x-if="preOrderResult.unavailable.length > 0">
                                        <span class="message-warning">
                                            &nbsp;(<span x-text="preOrderResult.unavailable.length"></span> agotado(s))
                                        </span>
                                    </template>
                                </p>
                            </template>
                            <template x-if="preOrderResult !== null && !preOrderResult.ok">
                                <p class="message-error" x-text="preOrderResult.message"></p>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="preOrdersActivated && checkinPreOrder.length === 0">
                    <div class="pre-order-box">
                        <p class="message-success">✓ Pre-comanda ya enviada a cocina.</p>
                    </div>
                </template>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading">Confirmar Entrada</button>
                    <button type="button" class="btn-edit" @click="closeCheckin()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ────────────────────────────────────────────────────────────────
        MODAL: POS (Añadir Pedido)
        ──────────────────────────────────────────────────────────────── -->
    <div class="welcome-modal" x-show="posOpen" x-cloak>
        <div class="modal-backdrop" @click="closePos()"></div>
        <div class="welcome-card">
            <div class="welcome-header">
                <p class="welcome-subtitle">Sala</p>
                <h2 class="welcome-title">Añadir Pedido</h2>
                <p class="welcome-desc">Selecciona los artículos y cantidades.</p>
            </div>

            <form @submit.prevent="submitPos()">

                <!-- Tabs de categoría -->
                <div class="pos-filter-tabs" x-show="posCatTabs().length > 0">
                    <button type="button"
                        class="pos-cat-tab"
                        :class="{ active: posActiveCat === 'all' }"
                        @click="posActiveCat = 'all'">Todos</button>
                    <template x-for="cat in posCatTabs()" :key="cat">
                        <button type="button"
                            class="pos-cat-tab"
                            :class="{ active: posActiveCat === cat }"
                            @click="posActiveCat = cat"
                            x-text="cat"></button>
                    </template>
                </div>

                <!-- Filtro alérgenos -->
                <div class="pos-allergen-filter" x-show="posAllAllergens().length > 0">
                    <span class="pos-allergen-filter__label">Excluir:</span>
                    <template x-for="a in posAllAllergens()" :key="a.code">
                        <button type="button"
                            class="pos-allergen-badge"
                            :class="{ excluded: posExcludedAllergens.includes(a.code) }"
                            @click="togglePosAllergen(a.code)"
                            x-text="a.name"></button>
                    </template>
                </div>

                <!-- Grid de productos filtrados -->
                <div class="pos-product-grid">
                    <template x-for="item in posFilteredProducts()" :key="item.id">
                        <button type="button"
                            class="pos-product-card"
                            :class="{ selected: posLineForProduct(parseInt(item.id, 10)) }"
                            @click="posToggleProduct(item.id)">
                            <span class="pos-product-card__name" x-text="item.name"></span>
                            <span class="pos-product-card__price" x-text="formatEuro(parseInt(item.price)||0)"></span>
                        </button>
                    </template>
                    <p x-show="posFilteredProducts().length === 0" class="pos-empty">
                        Sin productos para este filtro.
                    </p>
                </div>

                <!-- Líneas del pedido -->
                <template x-if="posLines.length > 0">
                    <div>
                        <div class="pos-divider">Pedido</div>
                        <template x-for="(line, idx) in posLines" :key="idx">
                            <div class="pos-line">
                                <span class="pos-line__name"
                                    x-text="posProducts.find(p => parseInt(p.id,10) === line.productId)?.name || ''"></span>
                                <button type="button" class="pos-line__qty-btn"
                                    @click="posChangeQty(line.productId, -1)"
                                    aria-label="Menos">−</button>
                                <span class="pos-line__qty-val" x-text="line.qty"></span>
                                <button type="button" class="pos-line__qty-btn"
                                    @click="posChangeQty(line.productId, 1)"
                                    aria-label="Más">+</button>
                                <span class="pos-line__subtotal"
                                    x-text="formatEuro(posLineSubtotal(line))"></span>
                                <button type="button"
                                    class="pos-line__remove"
                                    @click="posToggleProduct(line.productId)"
                                    title="Quitar"
                                    aria-label="Quitar artículo">×</button>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="pos-total">
                    <span class="pos-total__label">Total</span>
                    <span class="pos-total__value" x-text="formatEuro(posTotal())"></span>
                </div>

                <p x-show="posError" x-text="posError" class="message-error" role="alert"></p>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading || !posValid()">
                        Confirmar Pedido
                    </button>
                    <button type="button" class="btn-edit" @click="closePos()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ────────────────────────────────────────────────────────────────        MODAL: COMANDA
        ──────────────────────────────────────────────────────────────────── -->
    <div class="welcome-modal" x-show="comandaOpen" x-cloak>
        <div class="modal-backdrop" @click="closeComanda()"></div>
        <div class="welcome-card comanda-card">
            <div class="welcome-header">
                <p class="welcome-subtitle">Comanda</p>
                <h2 class="welcome-title" x-text="comandaResInfo ? comandaResInfo.user_name : 'Cargando...'">&nbsp;</h2>
                <p class="welcome-desc" x-show="comandaResInfo">
                    <span x-text="comandaResInfo?.pass_name"></span>
                    &middot;
                    <span x-text="comandaResInfo?.guest_count"></span> pax
                </p>
            </div>

            <div x-show="comandaLoading" class="comanda-loading">
                Cargando comanda…
            </div>

            <template x-if="!comandaLoading && comandaItems.length === 0">
                <p class="comanda-empty">Sin artículos en la comanda.</p>
            </template>

            <template x-if="!comandaLoading && comandaItems.length > 0">
                <div class="comanda-items">
                    <template x-for="item in comandaItems" :key="item.id">
                        <div class="comanda-item">
                            <span class="comanda-item__qty" x-text="item.quantity + '×'"></span>
                            <span class="comanda-item__name" x-text="item.product_name"></span>
                            <span class="comanda-status-badge"
                                :class="comandaStatusClass(item.status)"
                                x-text="comandaStatusLabel(item.status)"></span>
                            <span class="comanda-item__price"
                                x-text="formatEuro(item.unit_price * item.quantity)"></span>
                        </div>
                    </template>

                    <template x-if="comandaTotals">
                        <div class="comanda-totals">
                            <div class="comanda-total-row">
                                <span>Entrada (<span x-text="comandaResInfo?.pass_name"></span>)</span>
                                <span x-text="formatEuro(comandaTotals.pass_subtotal)"></span>
                            </div>
                            <div class="comanda-total-row">
                                <span>Pedidos</span>
                                <span x-text="formatEuro(comandaTotals.items_amount)"></span>
                            </div>
                            <div class="comanda-total-row comanda-total-row--grand">
                                <strong>Total</strong>
                                <strong x-text="formatEuro(comandaTotals.total)"></strong>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div class="welcome-actions" style="margin-top:1.5rem;">
                <button type="button" class="btn-confirm"
                    @click="let _id = comandaResId; closeComanda(); openCobro(_id)"
                    x-show="!comandaLoading && comandaItems.length >= 0">
                    Ir a Cobro
                </button>
                <button type="button" class="btn-edit" @click="closeComanda()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- ────────────────────────────────────────────────────────────────────        MODAL: COBRO (Cierre de Visita)
        ──────────────────────────────────────────────────────────────── -->
    <div class="welcome-modal" x-show="cobroOpen" x-cloak>
        <div class="modal-backdrop" @click="closeCobro()"></div>
        <div class="welcome-card">
            <div class="welcome-header">
                <p class="welcome-subtitle">Cierre de Visita</p>
                <h2 class="welcome-title">Confirmar Cobro</h2>
                <p class="welcome-desc">Selecciona el método de pago para cerrar la visita.</p>
            </div>

            <form @submit.prevent="submitCobro()">

                <!-- Recibo de comanda -->
                <template x-if="comandaItems.length > 0">
                    <div class="cobro-receipt">
                        <p class="cobro-receipt__title">Resumen de comanda</p>
                        <template x-for="item in comandaItems" :key="item.id">
                            <div class="cobro-receipt__line">
                                <span x-text="item.quantity + '× ' + item.product_name"></span>
                                <span x-text="formatEuro(item.unit_price * item.quantity)"></span>
                            </div>
                        </template>
                        <template x-if="comandaTotals">
                            <div>
                                <div class="cobro-receipt__line cobro-receipt__line--pass">
                                    <span x-text="'Entrada: ' + (comandaResInfo?.pass_name ?? '')"></span>
                                    <span x-text="formatEuro(comandaTotals.pass_subtotal)"></span>
                                </div>
                                <div class="cobro-receipt__total">
                                    <span>Total</span>
                                    <span x-text="formatEuro(comandaTotals.total)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="minimal-field">
                    <label class="minimal-label" for="cobroMethod">Método de Pago</label>
                    <select class="minimal-input" id="cobroMethod" x-model="cobroMethod" required>
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="bizum">Bizum</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                </div>

                <div class="minimal-field">
                    <label class="minimal-label" for="cobroNotes">Notas (opcional)</label>
                    <input type="text"
                        class="minimal-input"
                        id="cobroNotes"
                        x-model="cobroNotes"
                        placeholder="p. ej. descuento aplicado"
                        maxlength="200">
                </div>

                <p x-show="cobroError" x-text="cobroError" class="message-error" role="alert"></p>

                <div class="welcome-actions">
                    <button type="submit" class="btn-confirm" :disabled="loading">
                        Confirmar Cobro
                    </button>
                    <button type="button" class="btn-edit" @click="closeCobro()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

</div>
