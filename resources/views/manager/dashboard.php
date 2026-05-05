<?php

declare(strict_types=1);

/**
 * Vista: Dashboard Manager - Panel de Gestor de Café
 * Ruta: GET /manager/dashboard
 *
 * Datos esperados del controlador:
 * @var array $cafe - Datos del café asignado
 * @var array $stats - Métricas [reservations_today, animals_count, week_revenue, avg_rating]
 * @var array $chartData - Datos para gráficos [weekly_revenue, top_animals, reservation_status]
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Session;

// Cargar componentes
require_once __DIR__ . '/../components/card.php';
require_once __DIR__ . '/../components/badge.php';

$user = Session::user();
$greeting = match (true) {
    date('H') < 12 => 'Buenos días',
    date('H') < 18 => 'Buenas tardes',
    default => 'Buenas noches',
};

// Config para Alpine.js
$alpineConfig = json_encode([
    'chartData' => $chartData ?? [],
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='managerDashboard(<?= $alpineConfig ?>)' x-cloak>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($greeting) ?>, <?= e($user['name'] ?? 'Gestor') ?></h1>
            <p class="dashboard-header__subtitle">
                Gestión de <strong><?= e($cafe['name'] ?? 'tu café') ?></strong>
            </p>
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
        <!-- Reservas Hoy -->
        <?= renderCard([
            'variant' => 'elevated',
            'padding' => 'md',
            'interactive' => false,
            'class' => 'stat-card',
            'body' => '
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Reservas Hoy</div>
                        <h2 class="mb-0 fw-bold">' . number_format($stats['reservations_today'] ?? 0) . '</h2>
                        <div class="text-muted small mt-1">Visitas confirmadas</div>
                    </div>
                    <div class="stat-card__icon stat-card__icon--primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>',
        ]) ?>

        <!-- Animales Activos -->
        <?= renderCard([
            'variant' => 'elevated',
            'padding' => 'md',
            'interactive' => false,
            'class' => 'stat-card',
            'body' => '
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Animales Activos</div>
                        <h2 class="mb-0 fw-bold">' . ($stats['animals_count'] ?? 0) . '</h2>
                        <div class="text-muted small mt-1">Listos para interactuar</div>
                    </div>
                    <div class="stat-card__icon stat-card__icon--success">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                </div>',
        ]) ?>

        <!-- Ingresos Semanales -->
        <?= renderCard([
            'variant' => 'elevated',
            'padding' => 'md',
            'interactive' => false,
            'class' => 'stat-card',
            'body' => '
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Ingresos Semana</div>
                        <h2 class="mb-0 fw-bold">¥' . number_format($stats['week_revenue'] ?? 0, 0) . '</h2>
                        <div class="text-muted small mt-1">Últimos 7 días</div>
                    </div>
                    <div class="stat-card__icon stat-card__icon--warning">
                        <i class="bi bi-currency-yen"></i>
                    </div>
                </div>',
        ]) ?>

        <!-- Rating Promedio -->
        <?= renderCard([
            'variant' => 'elevated',
            'padding' => 'md',
            'interactive' => false,
            'class' => 'stat-card',
            'body' => '
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="text-muted small mb-1">Rating Promedio</div>
                        <h2 class="mb-0 fw-bold">' . number_format($stats['avg_rating'] ?? 0, 1) . '/5</h2>
                        <div class="text-muted small mt-1">Valoración clientes</div>
                    </div>
                    <div class="stat-card__icon stat-card__icon--info">
                        <i class="bi bi-star-fill"></i>
                    </div>
                </div>',
        ]) ?>
    </div>

    <!-- Acciones Rápidas -->
    <div class="quick-actions">
        <a href="/manager/reservations" class="quick-action">
            <div class="quick-action__icon quick-action__icon--primary">
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Reservas</h3>
                <p class="quick-action__desc">Gestionar reservas del día</p>
            </div>
        </a>

        <a href="/manager/reviews" class="quick-action">
            <div class="quick-action__icon quick-action__icon--success">
                <i class="bi bi-chat-left-text"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Reseñas</h3>
                <p class="quick-action__desc">Ver opiniones de clientes</p>
            </div>
        </a>

        <a href="/manager/staff" class="quick-action">
            <div class="quick-action__icon quick-action__icon--info">
                <i class="bi bi-people"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Personal</h3>
                <p class="quick-action__desc">Gestionar turnos y staff</p>
            </div>
        </a>

        <a href="/manager/reports" class="quick-action">
            <div class="quick-action__icon quick-action__icon--warning">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="quick-action__content">
                <h3 class="quick-action__title">Reportes</h3>
                <p class="quick-action__desc">Analíticas y estadísticas</p>
            </div>
        </a>
    </div>

    <!-- Bento Grid Principal -->
    <div class="bento-grid bento-grid--dashboard">

        <!-- Chart Card: Ingresos Semanales -->
        <div class="bento-item--chart">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-graph-up-arrow"></i>
                        Ingresos Semanales
                    </h3>
                </div>
                <div class="glass-card__body">
                    <div class="chart-container">
                        <canvas id="revenueChart" aria-label="Gráfico de ingresos semanales"></canvas>

                        <!-- Loading state -->
                        <div class="chart-loading" x-show="loading" x-cloak>
                            <div class="spinner" aria-label="Cargando gráfico"></div>
                            <p>Cargando datos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Animals Card -->
        <div class="bento-item--activity">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-heart"></i>
                        Animales Más Populares
                    </h3>
                </div>
                <div class="glass-card__body">
                    <?php if (empty($chartData['top_animals'])): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <i class="bi bi-emoji-smile"></i>
                            </div>
                            <p class="empty-state__message">No hay interacciones registradas</p>
                        </div>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($chartData['top_animals'] as $animal): ?>
                                <li class="activity-list__item">
                                    <div class="activity-list__avatar">
                                        <i class="bi bi-activity" aria-hidden="true"></i>
                                    </div>
                                    <div class="activity-list__content">
                                        <h4 class="activity-list__title">
                                            <?= e($animal['name']) ?>
                                        </h4>
                                        <p class="activity-list__subtitle">
                                            <?= e($animal['species_type']) ?> ·
                                            <?= number_format($animal['interaction_count']) ?> interacciones
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reservation Status Card -->
        <div class="bento-item--wide">
            <div class="glass-card">
                <div class="glass-card__header">
                    <h3 class="glass-card__title">
                        <i class="bi bi-pie-chart"></i>
                        Estado de Reservas
                    </h3>
                </div>
                <div class="glass-card__body">
                    <div class="chart-container chart-container--small">
                        <canvas id="statusChart" aria-label="Gráfico de estado de reservas"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
