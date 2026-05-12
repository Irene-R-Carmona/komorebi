<?php

declare(strict_types=1);

/**
 * Vista: Dashboard Supervisor - Panel de Supervisión en Sala
 * Ruta: GET /supervisor/dashboard
 *
 * Datos esperados del controlador:
 * @var string                    $titulo
 * @var int                       $cafe_id
 * @var list<array<string,mixed>> $reservations   — Reservas del día (todas)
 * @var list<array<string,mixed>> $activeTables   — Reservas con status='active' (en local)
 * @var list<array<string,mixed>> $pendingOrders  — Ítems KDS en estado 'pending'
 * @var list<array<string,mixed>> $kitchenOrders  — Ítems KDS en estado 'kitchen'
 * @var list<array<string,mixed>> $readyOrders    — Ítems KDS en estado 'ready'
 *
 * Computed properties en Alpine (supervisor-dashboard.js):
 *   readyByTable    — ítems ready agrupados por tracker_code / reservation_id
 *   upcomingArrivals — reservas confirmadas en los próximos 30 min
 */

use App\Core\Raw;

$initialData = Raw::json([
    'reservations' => $reservations ?? [],
    'activeTables' => $activeTables ?? [],
    'pendingOrders' => $pendingOrders ?? [],
    'kitchenOrders' => $kitchenOrders ?? [],
    'readyOrders' => $readyOrders ?? [],
]);
?>

<script nonce="<?= $cspNonce ?? '' ?>">
    window.__MERCURE__ = {
        cafeId: <?= (int) ($cafe_id ?? 0) ?>,
        hub: '/.well-known/mercure'
    };
</script>

<div x-data='supervisorDashboard(<?= $initialData ?>)' x-cloak>

    <!-- KPI Strip -->
    <div class="supervisor-kpi-strip">
        <div class="supervisor-kpi">
            <div class="supervisor-kpi__value" x-text="reservations.length"></div>
            <div class="supervisor-kpi__label">Reservas Hoy</div>
        </div>
        <div class="supervisor-kpi">
            <div class="supervisor-kpi__value" x-text="activeTables.length"></div>
            <div class="supervisor-kpi__label">En Local</div>
        </div>
        <div class="supervisor-kpi">
            <div class="supervisor-kpi__value" x-text="pendingOrders.length"></div>
            <div class="supervisor-kpi__label">Pendientes</div>
        </div>
        <div class="supervisor-kpi">
            <div class="supervisor-kpi__value" x-text="kitchenOrders.length"></div>
            <div class="supervisor-kpi__label">En Cocina</div>
        </div>
        <div class="supervisor-kpi"
            :data-alert="readyOrders.length >= 4 ? 'danger' : readyOrders.length >= 2 ? 'warning' : ''">
            <div class="supervisor-kpi__value" x-text="readyOrders.length"></div>
            <div class="supervisor-kpi__label">Listos</div>
        </div>
    </div>

    <!-- Layout 2 columnas -->
    <div class="supervisor-2col">

        <!-- ===== COLUMNA IZQUIERDA: órdenes de cocina ===== -->
        <div class="supervisor-col supervisor-col--left">

            <!-- Panel: Pendientes -->
            <section class="sv-panel" aria-label="Órdenes pendientes">
                <div class="sv-panel__header sv-panel__header--pending">
                    <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                    Pendientes
                    <span class="badge badge--warning ms-auto" x-text="pendingOrders.length"></span>
                </div>
                <div class="sv-panel__body">
                    <template x-if="pendingOrders.length === 0">
                        <p class="sv-empty">Sin órdenes pendientes</p>
                    </template>
                    <template x-for="order in pendingOrders" :key="order.id">
                        <div class="order-card" data-status="pending">
                            <div class="order-card__header">
                                <span class="order-card__id" x-text="'#' + (order.tracker_code || order.id)"></span>
                                <span class="order-card__table" x-text="order.station || '—'"></span>
                                <span class="timer"
                                    :class="getTimerClass(getOrderAge(order, 'created_ts'))"
                                    x-text="getOrderAge(order, 'created_ts') + 'min'"
                                    x-show="_tick >= 0 && !!order.created_ts"></span>
                            </div>
                            <div class="order-card__items" x-text="order.quantity + '× ' + order.product_name"></div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Panel: En Cocina -->
            <section class="sv-panel" aria-label="Órdenes en cocina">
                <div class="sv-panel__header sv-panel__header--kitchen">
                    <span class="material-symbols-outlined" aria-hidden="true">outdoor_grill</span>
                    En Cocina
                    <span class="badge badge--warning ms-auto" x-text="kitchenOrders.length"></span>
                </div>
                <div class="sv-panel__body">
                    <template x-if="kitchenOrders.length === 0">
                        <p class="sv-empty">Sin órdenes en preparación</p>
                    </template>
                    <template x-for="order in kitchenOrders" :key="order.id">
                        <div class="order-card" data-status="kitchen">
                            <div class="order-card__header">
                                <span class="order-card__id" x-text="'#' + (order.tracker_code || order.id)"></span>
                                <span class="order-card__table" x-text="order.station || '—'"></span>
                                <!-- Usar kitchen_started_ts cuando está disponible -->
                                <span class="timer"
                                    :class="getTimerClass(getOrderAge(order, 'kitchen_started_ts'))"
                                    x-text="getOrderAge(order, 'kitchen_started_ts') + 'min'"
                                    x-show="_tick >= 0 && (!!order.kitchen_started_ts || !!order.created_ts)"></span>
                            </div>
                            <div class="order-card__items" x-text="order.quantity + '× ' + order.product_name"></div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Panel: Listos para servir (vista expedidor) -->
            <section class="sv-panel" aria-label="Mesas listas para servir">
                <div class="sv-panel__header sv-panel__header--ready">
                    <span class="material-symbols-outlined" aria-hidden="true">done_all</span>
                    Listos — Vista Expedidor
                    <span class="badge badge--success ms-auto" x-text="readyOrders.length"></span>
                </div>
                <div class="sv-panel__body">
                    <template x-if="readyByTable.length === 0">
                        <p class="sv-empty">Sin platos esperando servicio</p>
                    </template>
                    <template x-for="group in readyByTable" :key="group.tracker_code">
                        <div class="expeditor-row">
                            <div class="expeditor-row__id">
                                <span class="material-symbols-outlined" aria-hidden="true">table_restaurant</span>
                                #<span x-text="group.tracker_code"></span>
                            </div>
                            <div class="expeditor-row__count">
                                <span x-text="group.ready"></span>/<span x-text="group.total"></span>
                                <span x-text="group.ready === group.total ? 'platos' : 'listos'"></span>
                            </div>
                            <span class="badge"
                                :class="group.ready === group.total ? 'badge--success' : 'badge--warning'"
                                x-text="group.ready === group.total ? 'COMPLETA' : 'PARCIAL'"></span>
                        </div>
                    </template>
                </div>
            </section>

        </div><!-- /columna izquierda -->

        <!-- ===== COLUMNA DERECHA: llegadas + mesas + reservas ===== -->
        <div class="supervisor-col supervisor-col--right">

            <!-- Panel: Próximas llegadas (30 min) -->
            <section class="sv-panel" aria-label="Próximas llegadas">
                <div class="sv-panel__header sv-panel__header--arrivals">
                    <span class="material-symbols-outlined" aria-hidden="true">directions_walk</span>
                    Próximas llegadas
                    <span class="badge badge--info ms-auto" x-text="upcomingArrivals.length"></span>
                </div>
                <div class="sv-panel__body">
                    <template x-if="upcomingArrivals.length === 0">
                        <p class="sv-empty">Sin llegadas en los próximos 30 min</p>
                    </template>
                    <template x-for="r in upcomingArrivals" :key="r.id">
                        <div class="upcoming-item"
                            :class="{'upcoming-item--urgent': (r.unix_time - Math.floor(Date.now()/1000)) < 600}">
                            <div class="upcoming-item__time" x-text="r.time"></div>
                            <div class="upcoming-item__info">
                                <span class="upcoming-item__name" x-text="r.customer_name"></span>
                                <span class="upcoming-item__guests"
                                    x-text="r.guests + ' pers.'"></span>
                            </div>
                            <span class="upcoming-item__mins"
                                x-text="Math.max(0, Math.floor((r.unix_time - Math.floor(Date.now()/1000)) / 60)) + 'min'"></span>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Panel: Mesas activas -->
            <section class="sv-panel" aria-label="Mesas activas">
                <div class="sv-panel__header">
                    <span class="material-symbols-outlined" aria-hidden="true">table_bar</span>
                    Mesas activas
                    <span class="badge badge--info ms-auto" x-text="activeTables.length"></span>
                </div>
                <div class="sv-panel__body">
                    <template x-if="activeTables.length === 0">
                        <p class="sv-empty">Sin mesas ocupadas en este momento</p>
                    </template>
                    <div class="table-grid">
                        <template x-for="table in activeTables" :key="table.id">
                            <div class="table-cell table-cell--occupied">
                                <div class="table-cell__code" x-text="table.table_code || '—'"></div>
                                <div class="table-cell__name" x-text="table.customer_name"></div>
                                <div class="table-cell__capacity">
                                    <span x-text="table.guests + ' pers. · ' + table.time"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Panel: Reservas del día -->
            <section class="sv-panel" aria-label="Reservas del día">
                <div class="sv-panel__header">
                    <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                    Reservas del día
                    <span class="badge badge--neutral ms-auto" x-text="reservations.length"></span>
                </div>
                <div class="sv-panel__body sv-panel__body--scroll">
                    <template x-if="reservations.length === 0">
                        <p class="sv-empty">Sin reservas hoy</p>
                    </template>
                    <template x-for="res in reservations" :key="res.id">
                        <div class="reservation-card" :data-status="res.status">
                            <div class="reservation-card__time" x-text="res.time"></div>
                            <div class="reservation-card__info">
                                <strong x-text="res.customer_name"></strong>
                                <span x-text="res.guests + ' pers.'"></span>
                                <span x-text="res.table_code ? '· Mesa ' + res.table_code : ''" x-show="!!res.table_code"></span>
                            </div>
                            <span class="badge"
                                :class="{
                                      'badge--success': res.status === 'confirmed',
                                      'badge--warning': res.status === 'pending',
                                      'badge--info':    res.status === 'active',
                                      'badge--neutral': !['confirmed','pending','active'].includes(res.status)
                                  }"
                                x-text="res.statusLabel"></span>
                        </div>
                    </template>
                </div>
            </section>

        </div><!-- /columna derecha -->

    </div><!-- /supervisor-2col -->

</div><!-- /x-data -->

<script src="/js/backoffice/supervisor-dashboard.js" nonce="<?= $cspNonce ?? '' ?>"></script>
