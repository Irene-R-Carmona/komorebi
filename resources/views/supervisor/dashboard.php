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
 */

use App\Core\Raw;
use App\Core\Session;

require_once __DIR__ . '/../components/badge.php';

$user = Session::user();
$greeting = match (true) {
    (int) \date('H') < 12 => 'Buenos días',
    (int) \date('H') < 18 => 'Buenas tardes',
    default               => 'Buenas noches',
};

$initialData = Raw::json([
    'reservations'  => $reservations  ?? [],
    'activeTables'  => $activeTables  ?? [],
    'pendingOrders' => $pendingOrders ?? [],
    'kitchenOrders' => $kitchenOrders ?? [],
    'readyOrders'   => $readyOrders   ?? [],
]);
?>

<script nonce="<?= $cspNonce ?? '' ?>">
    window.__MERCURE__ = {
        cafeId: <?= (int) ($cafe_id ?? 0) ?>,
        hub: '/.well-known/mercure'
    };
</script>

<div class="container-fluid" x-data='supervisorDashboard(<?= $initialData ?>)' x-cloak>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($greeting) ?>, <?= e($user['name'] ?? 'Supervisor') ?></h1>
            <p class="dashboard-header__subtitle">Panel de supervisión en sala</p>
        </div>
        <div class="dashboard-header__meta">
            <time class="dashboard-header__date" datetime="<?= \date('Y-m-d') ?>">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <?php
                $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                echo $formatter->format(new DateTime());
                ?>
            </time>
            <span class="ms-3 text-muted small" x-show="lastUpdate">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Actualizado <span x-text="lastUpdate ? lastUpdate.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'}) : ''"></span>
            </span>
        </div>
    </div>

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

    <!-- Bento Grid Principal -->
    <div class="bento-grid bento-grid--supervisor">

        <!-- Reservas del Día -->
        <div class="bento-item--reservations">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        Reservas del Día (<span x-text="reservations.length"></span>)
                    </h3>
                    <!-- Filtros rápidos -->
                    <div class="d-flex gap-2 flex-wrap mt-2">
                        <button class="btn btn-sm" :class="reservationFilter === '' ? 'btn-primary' : 'btn-outline-secondary'" @click="reservationFilter = ''">Todas</button>
                        <button class="btn btn-sm" :class="reservationFilter === 'confirmed' ? 'btn-primary' : 'btn-outline-secondary'" @click="reservationFilter = 'confirmed'">Confirmadas</button>
                        <button class="btn btn-sm" :class="reservationFilter === 'active' ? 'btn-primary' : 'btn-outline-secondary'" @click="reservationFilter = 'active'">En local</button>
                        <button class="btn btn-sm" :class="reservationFilter === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary'" @click="reservationFilter = 'cancelled'">Canceladas</button>
                    </div>
                </div>
                <div class="glass-card__body">
                    <template x-if="filteredReservations.length === 0">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></div>
                            <p class="empty-state__message">No hay reservas para este filtro</p>
                        </div>
                    </template>
                    <div class="reservation-list">
                        <template x-for="res in filteredReservations" :key="res.id">
                            <div class="reservation-card" :data-status="res.status">
                                <div class="reservation-card__time" x-text="res.time"></div>
                                <div class="reservation-card__info">
                                    <h4 x-text="res.customer_name"></h4>
                                    <p x-text="res.guests + ' persona' + (res.guests > 1 ? 's' : '')"></p>
                                </div>
                                <span class="badge ms-auto"
                                    :class="{
                                          'badge--success': res.status === 'confirmed',
                                          'badge--warning': res.status === 'pending',
                                          'badge--danger':  res.status === 'cancelled',
                                          'badge--info':    res.status === 'active',
                                          'badge--neutral': !['confirmed','pending','cancelled','active'].includes(res.status)
                                      }"
                                    x-text="res.statusLabel">
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de Mesas -->
        <div class="bento-item--tables">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-grid-3x3" aria-hidden="true"></i>
                        Mesas Ocupadas (<span x-text="activeTables.length"></span>)
                    </h3>
                </div>
                <div class="glass-card__body">
                    <template x-if="activeTables.length === 0">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="bi bi-door-open" aria-hidden="true"></i></div>
                            <p class="empty-state__message">No hay mesas ocupadas en este momento</p>
                        </div>
                    </template>
                    <div class="table-grid">
                        <template x-for="table in activeTables" :key="table.id">
                            <div class="table-cell table-cell--occupied">
                                <div class="table-cell__code" x-text="table.table_code || '—'"></div>
                                <div class="table-cell__name" x-text="table.customer_name"></div>
                                <div class="table-cell__capacity">
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <span x-text="table.guests"></span>
                                    &nbsp;&middot;&nbsp;
                                    <span x-text="table.time"></span>
                                </div>
                                <span class="badge badge--success table-cell__status">En local</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Órdenes por Estado -->
        <div class="bento-item--orders">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-receipt" aria-hidden="true"></i>
                        Órdenes Activas
                        (<span x-text="pendingOrders.length + kitchenOrders.length + readyOrders.length"></span>)
                    </h3>
                </div>
                <div class="glass-card__body">
                    <template x-if="pendingOrders.length + kitchenOrders.length + readyOrders.length === 0">
                        <div class="empty-state">
                            <div class="empty-state__icon"><i class="bi bi-check-circle" aria-hidden="true"></i></div>
                            <p class="empty-state__message">Todo al día</p>
                        </div>
                    </template>
                    <div class="orders-status-grid" x-show="pendingOrders.length + kitchenOrders.length + readyOrders.length > 0">

                        <!-- Columna: Pendiente -->
                        <div class="orders-status-col orders-status-col--pending">
                            <div class="orders-status-col__header">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                Pendiente
                                <span class="badge badge--warning ms-auto" x-text="pendingOrders.length"></span>
                            </div>
                            <div class="order-list">
                                <template x-for="order in pendingOrders" :key="order.id">
                                    <div class="order-card" data-status="pending">
                                        <div class="order-card__header">
                                            <span class="order-card__id" x-text="'#' + (order.tracker_code || order.id)"></span>
                                            <span class="order-card__table" x-text="order.station || '—'"></span>
                                            <span class="timer" :class="getTimerClass(getOrderAge(order))" x-text="getOrderAge(order) + 'min'" x-show="_tick >= 0 && !!order.created_ts"></span>
                                        </div>
                                        <div class="order-card__items" x-text="order.quantity + 'x ' + order.product_name"></div>
                                    </div>
                                </template>
                                <template x-if="pendingOrders.length === 0">
                                    <p class="text-muted small text-center py-2">—</p>
                                </template>
                            </div>
                        </div>

                        <!-- Columna: En preparación -->
                        <div class="orders-status-col orders-status-col--kitchen">
                            <div class="orders-status-col__header">
                                <i class="bi bi-fire" aria-hidden="true"></i>
                                En preparación
                                <span class="badge badge--warning ms-auto" x-text="kitchenOrders.length"></span>
                            </div>
                            <div class="order-list">
                                <template x-for="order in kitchenOrders" :key="order.id">
                                    <div class="order-card" data-status="kitchen">
                                        <div class="order-card__header">
                                            <span class="order-card__id" x-text="'#' + (order.tracker_code || order.id)"></span>
                                            <span class="order-card__table" x-text="order.station || '—'"></span>
                                            <span class="timer" :class="getTimerClass(getOrderAge(order))" x-text="getOrderAge(order) + 'min'" x-show="_tick >= 0 && !!order.created_ts"></span>
                                        </div>
                                        <div class="order-card__items" x-text="order.quantity + 'x ' + order.product_name"></div>
                                    </div>
                                </template>
                                <template x-if="kitchenOrders.length === 0">
                                    <p class="text-muted small text-center py-2">—</p>
                                </template>
                            </div>
                        </div>

                        <!-- Columna: Listo -->
                        <div class="orders-status-col orders-status-col--ready">
                            <div class="orders-status-col__header">
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                Listo
                                <span class="badge badge--success ms-auto" x-text="readyOrders.length"></span>
                            </div>
                            <div class="order-list">
                                <template x-for="order in readyOrders" :key="order.id">
                                    <div class="order-card" data-status="ready">
                                        <div class="order-card__header">
                                            <span class="order-card__id" x-text="'#' + order.reservation_id"></span>
                                        </div>
                                        <div class="order-card__items" x-text="order.quantity + 'x ' + order.product_name"></div>
                                    </div>
                                </template>
                                <template x-if="readyOrders.length === 0">
                                    <p class="text-muted small text-center py-2">—</p>
                                </template>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
