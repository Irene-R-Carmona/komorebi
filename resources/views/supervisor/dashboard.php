<?php

declare(strict_types=1);

/**
 * Vista: Dashboard Supervisor - Panel de Supervisión en Sala
 * Ruta: GET /supervisor/dashboard
 *
 * Datos esperados del controlador:
 * @var string $titulo
 * @var array $reservations   - Reservas del día
 * @var array $activeTables   - Reservas con status=checked_in (mesas ocupadas ahora)
 * @var array $activeOrders   - Órdenes en curso del KitchenService::getAllPending()
 */

use App\Core\Session;

// Cargar componentes
require_once __DIR__ . '/../components/badge.php';

$user = Session::user();
$greeting = match (true) {
    date('H') < 12 => 'Buenos días',
    date('H') < 18 => 'Buenas tardes',
    default => 'Buenas noches',
};
?>

<div class="container-fluid" x-data="supervisorDashboard()" x-cloak>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($greeting) ?>, <?= e($user['name'] ?? 'Supervisor') ?></h1>
            <p class="dashboard-header__subtitle">Panel de supervisión en sala</p>
        </div>
        <div class="dashboard-header__meta">
            <time class="dashboard-header__date" datetime="<?= date('Y-m-d') ?>">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <?php
                $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
echo $formatter->format(new DateTime());
?>
            </time>
        </div>
    </div>

    <!-- Bento Grid Principal -->
    <div class="bento-grid bento-grid--supervisor">

        <!-- Reservas del Día -->
        <div class="bento-item--reservations">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-calendar-check"></i>
                        Reservas del Día (<?= count($reservations) ?>)
                    </h3>
                </div>
                <div class="glass-card__body">
                    <?php if (empty($reservations)): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <p class="empty-state__message">No hay reservas para hoy</p>
                        </div>
                    <?php else: ?>
                        <div class="reservation-list">
                            <?php foreach ($reservations as $res): ?>
                                <div class="reservation-card" data-status="<?= e($res['status']) ?>">
                                    <div class="reservation-card__time"><?= e($res['time']) ?></div>
                                    <div class="reservation-card__info">
                                        <h4><?= e($res['customer_name']) ?></h4>
                                        <p><?= $res['guests'] ?> persona<?= $res['guests'] > 1 ? 's' : '' ?></p>
                                    </div>
                                    <?php
                    $badgeVariant = match ($res['status']) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'neutral'
                    };
                                echo renderBadge([
                                    'label' => $res['statusLabel'],
                                    'variant' => $badgeVariant,
                                    'size' => 'sm',
                                ]);
                                ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estado de Mesas -->
        <div class="bento-item--tables">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-grid-3x3"></i>
                        Mesas Ocupadas (<?= count($activeTables ?? []) ?>)
                    </h3>
                </div>
                <div class="glass-card__body">
                    <?php if (empty($activeTables ?? [])): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <i class="bi bi-door-open"></i>
                            </div>
                            <p class="empty-state__message">No hay mesas ocupadas en este momento</p>
                        </div>
                    <?php else: ?>
                        <div class="table-grid">
                            <?php foreach ($activeTables as $table): ?>
                                <div class="table-cell table-cell--occupied">
                                    <div class="table-cell__code"><?= e($table['customer_name']) ?></div>
                                    <div class="table-cell__capacity">
                                        <i class="bi bi-person"></i> <?= (int) $table['guests'] ?>
                                        &nbsp;&middot;&nbsp;<?= e($table['time']) ?>
                                    </div>
                                    <?php
                                echo renderBadge([
                                    'label' => 'En local',
                                    'variant' => 'success',
                                    'size' => 'sm',
                                    'class' => 'table-cell__status',
                                ]);
                                ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Órdenes Activas -->
        <div class="bento-item--orders">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-receipt"></i>
                        Órdenes Activas (<?= count($activeOrders ?? []) ?>)
                    </h3>
                </div>
                <div class="glass-card__body">
                    <?php if (empty($activeOrders ?? [])): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <p class="empty-state__message">Todo al día</p>
                        </div>
                    <?php else: ?>
                        <div class="order-list">
                            <?php foreach ($activeOrders as $order): ?>
                                <div class="order-card" data-status="<?= e($order['status']) ?>">
                                    <div class="order-card__header">
                                        <span class="order-card__id">#<?= e($order['tracker_code'] ?? (string) $order['id']) ?></span>
                                        <span class="order-card__table"><?= e($order['station'] ?? '—') ?></span>
                                    </div>
                                    <div class="order-card__items">
                                        <?= e($order['quantity'] . 'x ' . ($order['product_name'] ?? '')) ?>
                                    </div>
                                    <?php
                                $statusLabel = match ($order['status'] ?? '') {
                                    'kitchen' => 'En cocina',
                                    'pending' => 'Pendiente',
                                    'ready' => 'Listo',
                                    default => ucfirst((string) ($order['status'] ?? ''))
                                };
                                $statusVariant = match ($order['status'] ?? '') {
                                    'kitchen' => 'warning',
                                    'pending' => 'neutral',
                                    'ready' => 'info',
                                    default => 'neutral'
                                };
                                echo renderBadge([
                                    'label' => $statusLabel,
                                    'variant' => $statusVariant,
                                    'size' => 'sm',
                                ]);
                                ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>
