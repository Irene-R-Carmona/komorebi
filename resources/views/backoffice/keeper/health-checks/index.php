<?php

declare(strict_types=1);

use App\Core\View;
use App\Support\DateFormatting;

/**
 * Dashboard de Chequeos de Salud Animal
 *
 * Muestra:
 * - Chequeos completados hoy
 * - Animales pendientes de chequeo
 * - Alertas activas de los últimos 7 días
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-clipboard2-pulse text-primary"></i>
                        Chequeos de Salud
                    </h1>
                    <p class="text-muted mb-0">Dashboard de chequeos diarios - <?= date('d/m/Y') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="stats-grid stats-grid--3 mb-4">
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'check-circle-fill',
            'variant' => 'success',
            'label' => 'Chequeos Completados Hoy',
            'value' => $completed_count,
        ]) ?>
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'hourglass-split',
            'variant' => 'warning',
            'label' => 'Chequeos Pendientes',
            'value' => $pending_count,
        ]) ?>
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'exclamation-triangle-fill',
            'variant' => 'error',
            'label' => 'Alertas Activas (7 días)',
            'value' => count($active_alerts),
        ]) ?>
    </div>

    <!-- Tabbed Content: Pendientes | Completados | Alertas -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="healthCheckTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                <i class="bi bi-hourglass"></i> Pendientes (<?= $pending_count ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                                <i class="bi bi-check-circle"></i> Completados Hoy (<?= $completed_count ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab">
                                <i class="bi bi-exclamation-triangle"></i> Alertas (<?= count($active_alerts) ?>)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="healthCheckTabsContent">
                        <!-- TAB: Animales Pendientes -->
                        <div class="tab-pane fade show active" id="pending" role="tabpanel">
                            <?php if (empty($pending_animals)): ?>
                                <div class="alert alert-success" role="alert">
                                    <strong>¡Excelente trabajo!</strong> Todos los animales tienen su chequeo diario completado.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Animal</th>
                                                <th>Especie</th>
                                                <th>Estado</th>
                                                <th>Último Chequeo</th>
                                                <th>Café</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_animals as $animal): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($animal['animal_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= htmlspecialchars($animal['species_type'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusColors = [
                                                            'active' => 'success',
                                                            'monitoring' => 'warning',
                                                            'resting' => 'secondary',
                                                            'sick' => 'danger',
                                                        ];
                                                        $statusLabels = [
                                                            'active' => 'Activo',
                                                            'monitoring' => 'En observación',
                                                            'resting' => 'Descansando',
                                                            'sick' => 'Enfermo',
                                                            'retired' => 'Retirado',
                                                        ];
                                                        $statusColor = $statusColors[$animal['current_status']] ?? 'secondary';
                                                        $statusLabel = $statusLabels[$animal['current_status']] ?? ucfirst($animal['current_status']);
                                                        ?>
                                                        <span class="badge bg-<?= $statusColor ?>">
                                                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($animal['last_health_check']): ?>
                                                            <span class="text-muted small">
                                                                <?= e(DateFormatting::toSpanishDate($animal['last_health_check'])) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Sin registro</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($animal['cafe_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <a href="/keeper/health-checks/create/<?= $animal['animal_id'] ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-clipboard-plus"></i> Realizar Chequeo
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: Chequeos Completados Hoy -->
                        <div class="tab-pane fade" id="completed" role="tabpanel">
                            <?php if (empty($completed_checks)): ?>
                                <div class="alert alert-info" role="alert">
                                    No se han realizado chequeos hoy aún.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Animal</th>
                                                <th>Keeper</th>
                                                <th>Hora</th>
                                                <th>Temperatura</th>
                                                <th>Apetito</th>
                                                <th>Energía</th>
                                                <th>Alertas</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($completed_checks as $check): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($check['animal_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                    <td><small><?= htmlspecialchars($check['keeper_name'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                                    <td><small class="text-muted"><?= date('H:i', strtotime($check['created_at'])) ?></small></td>
                                                    <td>
                                                        <?php if ($check['temperature_c']): ?>
                                                            <?= number_format((float) $check['temperature_c'], 1) ?>°C
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $check['appetite'] === 'normal' ? 'success' : ($check['appetite'] === 'reduced' ? 'warning' : 'danger') ?>">
                                                            <?= htmlspecialchars($check['appetite'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $check['energy_level'] === 'normal' ? 'success' : ($check['energy_level'] === 'low' ? 'warning' : 'primary') ?>">
                                                            <?= htmlspecialchars($check['energy_level'], ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($check['alerts']) && is_array($check['alerts'])): ?>
                                                            <span class="badge bg-danger"><?= count($check['alerts']) ?> alerta(s)</span>
                                                        <?php else: ?>
                                                            <span class="text-success"><i class="bi bi-check-circle"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="/keeper/health-checks/<?= $check['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Ver
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: Alertas Activas -->
                        <div class="tab-pane fade" id="alerts" role="tabpanel">
                            <?php if (empty($active_alerts)): ?>
                                <div class="alert alert-success" role="alert">
                                    No hay alertas activas en los últimos 7 días.
                                </div>
                            <?php else: ?>
                                <?php foreach ($active_alerts as $alertCheck): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <h6 class="alert-heading">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <?= htmlspecialchars($alertCheck['animal_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <small class="text-muted">(<?= e(DateFormatting::toSpanishDate($alertCheck['check_date'])) ?>)</small>
                                        </h6>
                                        <ul class="mb-2">
                                            <?php if (!empty($alertCheck['alerts']) && is_array($alertCheck['alerts'])): ?>
                                                <?php foreach ($alertCheck['alerts'] as $alert): ?>
                                                    <li><?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                        <hr>
                                        <p class="mb-0">
                                            <strong>Keeper:</strong> <?= htmlspecialchars($alertCheck['keeper_name'], ENT_QUOTES, 'UTF-8') ?> •
                                            <a href="/keeper/health-checks/<?= $alertCheck['id'] ?>" class="alert-link">Ver chequeo completo</a>
                                        </p>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
