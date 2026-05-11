<?php

/**
 * Vista: Logs de Auditoría
 * Ruta: GET /admin/logs/audit
 */

use App\Core\View;

$titulo ??= 'Logs de Auditoría';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'shield-check',
        'title' => 'Auditoría',
        'subtitle' => 'Acciones del sistema',
    ]) ?>

    <div x-data="auditLogsManagement()" x-cloak>

        <!-- Stats Grid -->
        <div class="stats-grid mb-4 animate-fade-in">
            <div class="stat-card stat-card--primary animate-stagger-1">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-file-text"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Total</div>
                    <div class="stat-card__value"><?= $stats['total_logs'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Registros guardados</div>
                </div>
            </div>

            <div class="stat-card stat-card--info animate-stagger-2">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Recientes</div>
                    <div class="stat-card__value"><?= $stats['last_24h'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Últimas 24h</div>
                </div>
            </div>

            <div class="stat-card stat-card--warning animate-stagger-3">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Críticas</div>
                    <div class="stat-card__value"><?= $stats['critical_actions'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Acciones importantes</div>
                </div>
            </div>

            <div class="stat-card stat-card--success animate-stagger-4">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Activos</div>
                    <div class="stat-card__value"><?= $stats['active_users'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Usuarios operando</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="audit-date-from" class="form-label">Fecha Inicio</label>
                        <input id="audit-date-from" type="date" class="form-control" x-model="filters.dateFrom">
                    </div>
                    <div class="col-md-3">
                        <label for="audit-date-to" class="form-label">Fecha Fin</label>
                        <input id="audit-date-to" type="date" class="form-control" x-model="filters.dateTo">
                    </div>
                    <div class="col-md-3">
                        <label for="audit-action" class="form-label">Tipo de Acción</label>
                        <select id="audit-action" class="form-select" x-model="filters.action">
                            <option value="">Todas</option>
                            <option value="create">Crear</option>
                            <option value="update">Actualizar</option>
                            <option value="delete">Eliminar</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="audit-user" class="form-label">Usuario</label>
                        <input id="audit-user" type="text" class="form-control" placeholder="Buscar usuario..." x-model="filters.user">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" @click="applyFilters()">
                        <i class="bi bi-funnel"></i> Aplicar Filtros
                    </button>
                    <button type="button" class="btn btn-outline-secondary" @click="clearFilters()">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </button>
                    <button type="button" class="btn btn-outline-success" @click="exportLogs()">
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-body">
                <div x-show="loading" class="text-center py-5">
                    <output class="spinner-border text-primary" aria-busy="true">
                        <span class="visually-hidden">Cargando auditoría...</span>
                    </output>
                </div>

                <div x-show="!loading" x-cloak>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Recurso</th>
                                    <th>IP</th>
                                    <th>Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="logs.length === 0">
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <?= View::componentToString('components/admin/empty-state', [
                                                'icon' => 'inbox',
                                                'title' => 'Sin registros',
                                                'message' => 'Ajusta los filtros para ver más actividad',
                                            ]) ?>
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="log in logs" :key="log.id">
                                    <tr>
                                        <td x-text="log.created_at"></td>
                                        <td x-text="log.user_name"></td>
                                        <td>
                                            <span class="badge" :class="getActionBadge(log.action)" x-text="log.action"></span>
                                        </td>
                                        <td x-text="log.resource"></td>
                                        <td x-text="log.ip_address"></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info" @click="viewDetails(log)">
                                                <i class="bi bi-eye"></i> Ver
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div x-show="totalPages > 1" class="mt-3">
                        <?= View::componentToString('components/admin/pagination', [
                            'currentPage' => 1,
                            'totalPages' => 1,
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
