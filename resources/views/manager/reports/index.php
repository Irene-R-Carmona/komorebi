<?php

declare(strict_types=1);

/**
 * Vista: Reportes del Manager
 * Ruta: GET /manager/reports
 *
 * Variables pre-escapadas por View::render():
 * @var string $titulo
 * @var int    $reservationsToday
 * @var float  $revenueToday
 * @var int    $monthlyReservations
 * @var float  $avgRating
 * @var int    $pendingReservations
 * @var array  $statusDistribution  — [['status' => string, 'count' => int], ...]
 * @var array  $reservations        — [['id', 'fecha', 'estado', 'personas', 'importe', 'pago'], ...]
 * @var string $from
 * @var string $to
 */
?>

<div class="container-fluid">

    <!-- Cabecera -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= $titulo ?></h1>
            <p class="dashboard-header__subtitle">Métricas y estadísticas de tu café</p>
        </div>
        <div class="dashboard-header__meta">
            <a href="/manager/reports/export?from=<?= $from ?>&amp;to=<?= $to ?>"
                class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download" aria-hidden="true"></i> Exportar CSV
            </a>
        </div>
    </div>

    <!-- Filtro de fechas -->
    <div class="glass-card mb-4" style="padding: 1.25rem;">
        <form method="GET" action="/manager/reports" class="d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label for="filter-from" class="form-label">Desde</label>
                <input type="date" id="filter-from" name="from" class="form-control"
                    value="<?= $from ?>" max="<?= $to ?>">
            </div>
            <div>
                <label for="filter-to" class="form-label">Hasta</label>
                <input type="date" id="filter-to" name="to" class="form-control"
                    value="<?= $to ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- KPIs -->
    <div class="stats-grid mb-4">
        <div class="glass-card stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="text-muted small mb-1">Reservas Hoy</div>
                    <h2 class="mb-0 fw-bold"><?= $reservationsToday ?></h2>
                </div>
                <div class="stat-card__icon stat-card__icon--primary">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="glass-card stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="text-muted small mb-1">Reservas del Mes</div>
                    <h2 class="mb-0 fw-bold"><?= $monthlyReservations ?></h2>
                </div>
                <div class="stat-card__icon stat-card__icon--success">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="glass-card stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="text-muted small mb-1">Ingresos Hoy</div>
                    <h2 class="mb-0 fw-bold">¥<?= number_format((float) $revenueToday, 0) ?></h2>
                </div>
                <div class="stat-card__icon stat-card__icon--warning">
                    <i class="bi bi-currency-yen" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="glass-card stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="text-muted small mb-1">Rating Promedio</div>
                    <h2 class="mb-0 fw-bold"><?= number_format((float) $avgRating, 1) ?></h2>
                </div>
                <div class="stat-card__icon stat-card__icon--info">
                    <i class="bi bi-star-fill" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Distribución de estados (últimos 30 días) -->
        <div class="col-lg-4">
            <div class="glass-card h-100">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">
                    Estado de reservas <span class="text-muted">(últimos 30 días)</span>
                </h3>
                <?php if (empty($statusDistribution)): ?>
                    <p class="text-muted">Sin datos en el período.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Estado</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusDistribution as $row): ?>
                                <tr>
                                    <td><?= $row['status'] ?></td>
                                    <td class="text-end fw-bold"><?= (int) $row['count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reservas del período filtrado -->
        <div class="col-lg-8">
            <div class="glass-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 style="font-size: 1rem; font-weight: 600; margin: 0;">
                        Reservas
                        <span class="text-muted">(<?= $from ?> – <?= $to ?>)</span>
                    </h3>
                    <a href="/manager/reports/export?from=<?= $from ?>&amp;to=<?= $to ?>"
                        class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download" aria-hidden="true"></i> Exportar CSV
                    </a>
                </div>

                <?php if (empty($reservations)): ?>
                    <p class="text-muted">Sin reservas en el período seleccionado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th class="text-center">Personas</th>
                                    <th class="text-end">Importe</th>
                                    <th>Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $row): ?>
                                    <tr>
                                        <td class="text-muted small"><?= (int) $row['id'] ?></td>
                                        <td><?= $row['fecha'] ?></td>
                                        <td><?= $row['estado'] ?></td>
                                        <td class="text-center"><?= (int) $row['personas'] ?></td>
                                        <td class="text-end">¥<?= number_format((float) $row['importe'], 0) ?></td>
                                        <td><?= $row['pago'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($reservations) === 100): ?>
                        <p class="text-muted small mt-2 mb-0">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Se muestran las primeras 100 reservas. Usa <strong>Exportar CSV</strong> para el listado completo.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Acciones inferiores -->
    <div class="mt-4 d-flex gap-2">
        <a href="/manager/reports/export?from=<?= $from ?>&amp;to=<?= $to ?>"
            class="btn btn-outline-primary">
            <i class="bi bi-download" aria-hidden="true"></i>
            Exportar reporte CSV
        </a>
        <a href="/manager/dashboard" class="btn btn-outline-secondary">
            ← Volver al Dashboard
        </a>
    </div>

</div>
