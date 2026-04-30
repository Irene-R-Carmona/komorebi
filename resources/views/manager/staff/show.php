<?php

declare(strict_types=1);

/**
 * Vista: Detalle de Staff Member (Manager — HDA)
 *
 * @var array  $staff         - Datos del staff member
 * @var array  $shift_history - Historial de turnos (30 días)
 * @var array  $metrics       - Métricas de performance (PHP-injected)
 */

$staff ??= [];
$shift_history ??= [];
$metrics ??= [];
?>

<div class="container">

    <header class="mb-4">
        <h1 class="h3"><?= htmlspecialchars((string) ($staff['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-muted">Información y historial de staff member</p>
    </header>

    <!-- Bootstrap tabs (no JS needed) -->
    <ul class="nav nav-tabs mb-4" id="staffDetailTabs">
        <li class="nav-item">
            <button class="nav-link active" id="tab-info-btn"
                data-bs-toggle="tab" data-bs-target="#tab-info"
                type="button" role="tab" aria-controls="tab-info" aria-selected="true">
                Información
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tab-historial-btn"
                data-bs-toggle="tab" data-bs-target="#tab-historial"
                type="button" role="tab" aria-controls="tab-historial" aria-selected="false">
                Historial de Turnos
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="tab-performance-btn"
                data-bs-toggle="tab" data-bs-target="#tab-performance"
                type="button" role="tab" aria-controls="tab-performance" aria-selected="false">
                Performance
            </button>
        </li>
    </ul>

    <div class="tab-content" id="staffDetailTabsContent">

        <!-- Tab: Información -->
        <div class="tab-pane fade show active" id="tab-info" role="tabpanel" aria-labelledby="tab-info-btn">
            <div class="info-grid">
                <div class="info-item">
                    <strong>Email:</strong>
                    <span><?= htmlspecialchars((string) ($staff['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="info-item">
                    <strong>Roles:</strong>
                    <span><?= htmlspecialchars((string) ($staff['roles'] ?? 'Sin rol'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="info-item">
                    <strong>Estado:</strong>
                    <span class="badge <?= !empty($staff['is_active']) ? 'badge-success' : 'badge-danger' ?>">
                        <?= !empty($staff['is_active']) ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>Fecha de Alta:</strong>
                    <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($staff['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($staff['last_login'])): ?>
                <div class="info-item">
                    <strong>Último Login:</strong>
                    <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $staff['last_login'])), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($staff['email_verified_at'])): ?>
                <div class="info-item">
                    <strong>Email Verificado:</strong>
                    <span><?= htmlspecialchars(date('d/m/Y', strtotime((string) $staff['email_verified_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Historial de Turnos -->
        <div class="tab-pane fade" id="tab-historial" role="tabpanel" aria-labelledby="tab-historial-btn">
            <h3 class="h6 mb-3">Últimos 30 días</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Duración</th>
                        <th>Notas</th>
                        <th>Creado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($shift_history === []): ?>
                    <tr>
                        <td colspan="6" class="text-center">No hay turnos registrados en los últimos 30 días</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($shift_history as $shift): ?>
                    <?php
                        $start = new DateTime((string) ($shift['shift_start'] ?? 'now'));
                        $end = new DateTime((string) ($shift['shift_end'] ?? 'now'));
                        $duration = $start->diff($end);
                        ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) ($shift['shift_date'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(substr((string) ($shift['shift_start'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(substr((string) ($shift['shift_end'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $duration->h ?>h <?= $duration->i ?>m</td>
                        <td><?= htmlspecialchars((string) ($shift['notes'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>Manager</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tab: Performance (PHP-injected — no AJAX) -->
        <div class="tab-pane fade" id="tab-performance" role="tabpanel" aria-labelledby="tab-performance-btn">
            <?php if ($metrics === []): ?>
            <p class="text-muted">No hay métricas disponibles.</p>
            <?php else: ?>
            <div class="performance-metrics">
                <div class="metric-card">
                    <h4>Total de Turnos (30 días)</h4>
                    <p class="metric-value"><?= (int) ($metrics['total_shifts'] ?? 0) ?></p>
                </div>
                <div class="metric-card">
                    <h4>Total de Horas</h4>
                    <p class="metric-value"><?= (int) ($metrics['total_hours'] ?? 0) ?></p>
                </div>
                <div class="metric-card">
                    <h4>Turnos Este Mes</h4>
                    <p class="metric-value"><?= (int) ($metrics['shifts_this_month'] ?? 0) ?></p>
                </div>
                <div class="metric-card">
                    <h4>Duración Promedio</h4>
                    <p class="metric-value"><?= number_format((float) ($metrics['avg_shift_duration'] ?? 0), 1) ?>h</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="mt-4">
        <a href="/manager/staff" class="btn btn-secondary">Volver a Staff</a>
    </div>

</div>

<style>
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap: 15px; margin: 20px 0; }
.info-item { background: var(--admin-bg-alt,#f8f9fa); padding: 15px; border-radius: 4px; display: flex; flex-direction: column; gap: 5px; }
.info-item strong { color: var(--admin-text-muted,#6c757d); font-size: .9em; }
.performance-metrics { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 20px; margin: 20px 0; }
.metric-card { background: var(--gradient-coffee,#6f4e37); color: white; padding: 20px; border-radius: 8px; text-align: center; }
.metric-card h4 { margin: 0 0 10px 0; font-size: .9em; opacity: .9; }
.metric-value { font-size: 2.5em; font-weight: bold; margin: 0; }
</style>
