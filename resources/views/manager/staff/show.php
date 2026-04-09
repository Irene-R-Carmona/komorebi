<?php

declare(strict_types=1);

/**
 * Vista: Detalle de Staff Member (Manager)
 *
 * @var array $staff Datos del staff member
 * @var array $shift_history Historial de turnos
 * @var string $csrf_token Token CSRF
 */

$pageTitle = 'Detalle de Staff - ' . htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Komorebi Café</title>
    <link rel="stylesheet" href="/css/admin.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Información y historial de staff member</p>
        </header>

        <main x-data="{
            activeTab: 'info',
            showPerformance: false,
            performanceData: null
        }">
            <!-- Tabs -->
            <div class="tabs">
                <button
                    @click="activeTab = 'info'"
                    :class="{'active': activeTab === 'info'}">
                    Información
                </button>
                <button
                    @click="activeTab = 'historial'"
                    :class="{'active': activeTab === 'historial'}">
                    Historial de Turnos
                </button>
                <button
                    @click="activeTab = 'performance'; if (!performanceData) {
                        fetch('/manager/staff/performance/<?= $staff['id'] ?>')
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    performanceData = data.metrics;
                                }
                            });
                    }"
                    :class="{'active': activeTab === 'performance'}">
                    Performance
                </button>
            </div>

            <!-- Tab: Información -->
            <div x-show="activeTab === 'info'" class="tab-content">
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Email:</strong>
                        <span><?= htmlspecialchars($staff['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Roles:</strong>
                        <span><?= htmlspecialchars($staff['roles'] ?? 'Sin rol', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Estado:</strong>
                        <span class="badge <?= $staff['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $staff['is_active'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>Fecha de Alta:</strong>
                        <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($staff['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php if ($staff['last_login']): ?>
                        <div class="info-item">
                            <strong>Último Login:</strong>
                            <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($staff['last_login'])), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($staff['email_verified_at']): ?>
                        <div class="info-item">
                            <strong>Email Verificado:</strong>
                            <span><?= htmlspecialchars(date('d/m/Y', strtotime($staff['email_verified_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Historial de Turnos -->
            <div x-show="activeTab === 'historial'" class="tab-content">
                <h3>Últimos 30 días</h3>
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
                        <?php if (empty($shift_history)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No hay turnos registrados en los últimos 30 días</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shift_history as $shift): ?>
                                <?php
                                $start = new DateTime($shift['shift_start']);
                                $end = new DateTime($shift['shift_end']);
                                $duration = $start->diff($end);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($shift['shift_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(substr($shift['shift_start'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(substr($shift['shift_end'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $duration->h ?>h <?= $duration->i ?>m</td>
                                    <td><?= htmlspecialchars($shift['notes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>Manager</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab: Performance -->
            <div x-show="activeTab === 'performance'" class="tab-content">
                <div x-show="!performanceData" class="loading">
                    Cargando métricas...
                </div>
                <div x-show="performanceData" class="performance-metrics">
                    <div class="metric-card">
                        <h4>Total de Turnos (30 días)</h4>
                        <p class="metric-value" x-text="(performanceData && performanceData.total_shifts) || 0"></p>
                    </div>
                    <div class="metric-card">
                        <h4>Total de Horas</h4>
                        <p class="metric-value" x-text="(performanceData && performanceData.total_hours) || 0"></p>
                    </div>
                    <div class="metric-card">
                        <h4>Turnos Este Mes</h4>
                        <p class="metric-value" x-text="(performanceData && performanceData.shifts_this_month) || 0"></p>
                    </div>
                    <div class="metric-card">
                        <h4>Duración Promedio</h4>
                        <p class="metric-value" x-text="((performanceData && performanceData.avg_shift_duration) || 0) + 'h'"></p>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="/manager/staff" class="btn btn-secondary">Volver a Staff</a>
            </div>
        </main>
    </div>

    <style>
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .info-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-item strong {
            color: #555;
            font-size: 0.9em;
        }

        .performance-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .metric-card h4 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 0;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</body>

</html>
