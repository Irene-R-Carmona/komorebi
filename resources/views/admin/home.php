<?php

/**
 * Vista: Dashboard Admin - Modern Design 2026
 * Ruta: GET /admin/dashboard
 *
 * Datos esperados del controlador:
 * @var string $greeting - Saludo según hora
 * @var string $userName - Nombre del usuario
 * @var array $stats - Estadísticas [users, reservations, cafes, reviews, pending_reviews]
 * @var array $recent_reservations - Reservas recientes
 * @var array $chart_data - Datos para el gráfico
 * @var array $system_status - Estado de servicios
 */

// Config para Alpine.js
$alpineConfig = json_encode([
    'chartData' => $chart_data ?? [],
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='dashboard(<?= $alpineConfig ?>)' x-cloak>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($greeting) ?>, <?= e($userName) ?></h1>
            <p class="dashboard-header__subtitle">Panel de control general</p>
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

    <!-- Estadísticas Principales -->
    <div class="stats-grid">
        <!-- Estadística de Usuarios -->
        <div class="stat-card stat-card--primary">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-people"></i>
                </div>
                <?php if (isset($stats['users_trend'])): ?>
                    <div class="stat-card__trend <?= strpos($stats['users_trend'], '+') === 0 ? 'stat-card__trend--up' : 'stat-card__trend--down' ?>">
                        <i class="bi <?= strpos($stats['users_trend'], '+') === 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
                        <span><?= e($stats['users_trend']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Usuarios</div>
                <div class="stat-card__value"><?= number_format($stats['users'] ?? 0) ?></div>
                <div class="stat-card__subtitle">Amantes del café registrados</div>
            </div>
        </div>

        <!-- Reservations Stat -->
        <div class="stat-card stat-card--success">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <?php if (isset($stats['reservations_trend'])): ?>
                    <div class="stat-card__trend <?= strpos($stats['reservations_trend'], '+') === 0 ? 'stat-card__trend--up' : 'stat-card__trend--down' ?>">
                        <i class="bi <?= strpos($stats['reservations_trend'], '+') === 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
                        <span><?= e($stats['reservations_trend']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Reservas</div>
                <div class="stat-card__value"><?= number_format($stats['reservations'] ?? 0) ?></div>
                <div class="stat-card__subtitle">Visitas programadas</div>
            </div>
        </div>

        <!-- Cafes Stat -->
        <div class="stat-card stat-card--warning">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-shop"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Cafeterías</div>
                <div class="stat-card__value"><?= $stats['cafes'] ?? 0 ?></div>
                <div class="stat-card__subtitle">Espacios con gatitos</div>
            </div>
        </div>

        <!-- Reviews Stat -->
        <div class="stat-card stat-card--info">
            <div class="stat-card__header">
                <div class="stat-card__icon">
                    <i class="bi bi-star-fill"></i>
                </div>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Reseñas</div>
                <div class="stat-card__value"><?= number_format($stats['reviews'] ?? 0) ?></div>
                <div class="stat-card__subtitle">
                    <?php
                    $pending = $stats['pending_reviews'] ?? 0;
                    echo $pending > 0 ? "$pending esperando revisión" : 'Todo al día';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="quick-actions">
        <a href="/admin/users" class="quick-action">
            <div class="quick-action__icon quick-action__icon--primary">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Gestionar Usuarios</h3>
                <p class="quick-action__desc">Ver y administrar usuarios</p>
            </div>
        </a>

        <a href="/admin/cafes" class="quick-action">
            <div class="quick-action__icon quick-action__icon--success">
                <i class="bi bi-shop"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Gestionar Cafeterías</h3>
                <p class="quick-action__desc">Ver y administrar cafeterías</p>
            </div>
        </a>

        <a href="/admin/menu" class="quick-action">
            <div class="quick-action__icon quick-action__icon--info">
                <i class="bi bi-cup-hot"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Gestionar Menú</h3>
                <p class="quick-action__desc">Ver y administrar productos</p>
            </div>
        </a>

        <a href="/admin/reservations" class="quick-action">
            <div class="quick-action__icon quick-action__icon--warning">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Gestionar Reservas</h3>
                <p class="quick-action__desc">Ver y administrar reservas</p>
            </div>
        </a>
    </div>

    <!-- Bento Grid Principal -->
    <div class="bento-grid bento-grid--dashboard">

        <!-- Chart Card -->
        <div class="bento-item--chart">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-graph-up-arrow"></i>
                        Actividad de la Semana
                    </h3>
                    <div style="display: flex; gap: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                        <span>📊 Reservas</span>
                    </div>
                </div>
                <div class="glass-card__body">
                    <div class="chart-container">
                        <canvas id="dashboardChart" aria-label="Gráfico de evolución de reservas"></canvas>

                        <!-- Loading state -->
                        <div class="chart-loading" x-show="loading" x-cloak>
                            <div class="spinner" aria-label="Cargando gráfico"></div>
                            <p>Cargando datos...</p>
                        </div>

                        <!-- Error state -->
                        <div class="chart-loading" x-show="chartError" x-cloak>
                            <div class="empty-state__icon" aria-hidden="true">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <p style="color: var(--danger-500); font-weight: 500;">Error al cargar el gráfico</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Feed -->
        <div class="bento-item--activity">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-lightning-charge"></i>
                        Últimos Movimientos
                    </h3>
                </div>
                <div class="glass-card__body">
                    <?php if (empty($recent_activity)): ?>
                        <div class="empty-state empty-state--compact">
                            <div class="empty-state__icon" aria-hidden="true">
                                <i class="bi bi-activity"></i>
                            </div>
                            <p class="empty-state__text">No hay actividad reciente</p>
                        </div>
                    <?php else: ?>
                        <ul class="activity-feed">
                            <?php foreach ($recent_activity as $activity): ?>
                                <li class="activity-item activity-item--<?= e($activity['type']) ?>">
                                    <div class="activity-item__icon" aria-hidden="true">
                                        <i class="bi bi-<?= e($activity['icon']) ?>"></i>
                                    </div>
                                    <div class="activity-item__content">
                                        <p class="activity-item__text"><?= e($activity['text']) ?></p>
                                        <p class="activity-item__meta"><?= e($activity['meta']) ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reservations Table -->
        <div class="bento-item--reservations">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-calendar-check"></i>
                        Reservas Recientes
                    </h3>
                    <a href="/admin/reservations" class="btn-primary btn-sm" aria-label="Ver todas las reservas">
                        Ver todas
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="glass-card__body" style="padding: 0;">
                    <?php if (empty($recent_reservations)): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon" aria-hidden="true">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h4 class="empty-state__title">No hay reservas recientes</h4>
                            <p class="empty-state__text">Las visitas aparecerán aquí cuando se confirmen</p>
                            <a href="/admin/reservas/crear" class="btn-primary btn-sm">Crear reserva</a>
                        </div>
                    <?php else: ?>
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Cafetería</th>
                                    <th>Fecha & Hora</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_reservations, 0, 5) as $res): ?>
                                    <tr>
                                        <td>
                                            <div class="table-cell-user">
                                                <div class="table-cell-user__avatar" aria-hidden="true">
                                                    <?= strtoupper(e(($res['customer_name'] ?? 'U')[0])) ?>
                                                </div>
                                                <span class="table-cell-user__name"><?= e($res['customer_name'] ?? 'Invitado') ?></span>
                                            </div>
                                        </td>
                                        <td class="table-cell--secondary">
                                            <?= e($res['cafe_name'] ?? 'N/A') ?>
                                        </td>
                                        <td>
                                            <?php
                                            $dateStr = 'N/A';
                                            $timeStr = '';
                                            if (!empty($res['date'])) {
                                                $timestamp = strtotime($res['date']);
                                                if ($timestamp !== false) {
                                                    $dateStr = date('d M Y', $timestamp);
                                                }
                                            }
                                            if (!empty($res['time_slot'])) {
                                                $timeStr = e($res['time_slot']);
                                            }
                                            ?>
                                            <div class="table-cell-date__main"><?= $dateStr ?></div>
                                            <?php if ($timeStr): ?>
                                                <div class="table-cell-date__time"><?= $timeStr ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusConfig = match ($res['status'] ?? '') {
                                                'confirmed' => ['class' => 'badge-modern--success', 'label' => 'Confirmada'],
                                                'pending' => ['class' => 'badge-modern--warning', 'label' => 'Pendiente'],
                                                'cancelled' => ['class' => 'badge-modern--danger', 'label' => 'Cancelada'],
                                                'completed' => ['class' => 'badge-modern--info', 'label' => 'Completada'],
                                                default => ['class' => 'badge-modern--info', 'label' => ucfirst(e($res['status']))]
                                            };
                                            ?>
                                            <span class="badge-modern <?= $statusConfig['class'] ?>">
                                                <?= $statusConfig['label'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="bento-item--status">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-heart-pulse"></i>
                        Estado del Sistema
                    </h3>
                </div>
                <div class="glass-card__body">
                    <ul class="system-status">
                        <?php
                        $services = [
                            ['key' => 'database', 'icon' => 'database', 'label' => 'Base de Datos', 'desc' => 'MySQL 8.4'],
                            ['key' => 'cache', 'icon' => 'hdd-stack', 'label' => 'Cache', 'desc' => 'Redis'],
                            ['key' => 'email', 'icon' => 'envelope', 'label' => 'Email', 'desc' => 'SMTP'],
                        ];

                        foreach ($services as $service):
                            $status = $system_status[$service['key']] ?? 'offline';
                            $statusClass = match ($status) {
                                'online' => 'status-item--online',
                                'warning' => 'status-item--warning',
                                default => 'status-item--offline'
                            };
                            $statusLabel = match ($status) {
                                'online' => 'Operativo',
                                'warning' => 'Limitado',
                                default => 'Inactivo'
                            };
                        ?>
                            <li class="status-item <?= $statusClass ?>">
                                <div class="status-item__info">
                                    <div class="status-item__icon" aria-hidden="true">
                                        <i class="bi bi-<?= $service['icon'] ?>"></i>
                                    </div>
                                    <div class="status-item__text">
                                        <div class="status-item__label"><?= e($service['label']) ?></div>
                                        <div class="status-item__desc"><?= e($service['desc']) ?></div>
                                    </div>
                                </div>
                                <div class="status-item__indicator">
                                    <span class="status-item__dot" aria-hidden="true"></span>
                                    <span class="status-item__status"><?= $statusLabel ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
