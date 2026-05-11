<?php

/**
 * Vista: Logs de Autenticación
 * Ruta: GET /admin/logs/auth
 */

use App\Core\View;

$titulo ??= 'Logs de Autenticación';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'key',
        'title' => 'Autenticación',
        'subtitle' => 'Actividad de inicio de sesión',
    ]) ?>

    <div x-data="authLogsManagement()" x-cloak>

        <!-- Stats Grid -->
        <div class="stats-grid mb-4 animate-fade-in">
            <div class="stat-card stat-card--success animate-stagger-1">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Exitosos</div>
                    <div class="stat-card__value"><?= $stats['successful_logins'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Accesos correctos</div>
                </div>
            </div>

            <div class="stat-card stat-card--danger animate-stagger-2">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Fallidos</div>
                    <div class="stat-card__value"><?= $stats['failed_attempts'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Intentos no válidos</div>
                </div>
            </div>

            <div class="stat-card stat-card--warning animate-stagger-3">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Sospechosa</div>
                    <div class="stat-card__value"><?= $stats['suspicious_activity'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Requiere atención</div>
                </div>
            </div>

            <div class="stat-card stat-card--info animate-stagger-4">
                <div class="stat-card__header">
                    <div class="stat-card__icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="stat-card__content">
                    <div class="stat-card__label">Hoy</div>
                    <div class="stat-card__value"><?= $stats['active_today'] ?? 0 ?></div>
                    <div class="stat-card__subtitle">Usuarios activos</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4 border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="bi bi-shield-exclamation text-warning"></i>
                            Actividad Sospechosa Detectada
                        </h5>
                        <p class="text-muted mb-0">
                            <span x-text="suspiciousCount"></span> eventos requieren atención
                        </p>
                    </div>
                    <button type="button" class="btn btn-warning" @click="viewSuspicious()">
                        <i class="bi bi-eye"></i> Revisar
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="filter-date-from" class="form-label">Fecha Inicio</label>
                        <input id="filter-date-from" type="date" class="form-control" x-model="filters.dateFrom">
                    </div>
                    <div class="col-md-3">
                        <label for="filter-date-to" class="form-label">Fecha Fin</label>
                        <input id="filter-date-to" type="date" class="form-control" x-model="filters.dateTo">
                    </div>
                    <div class="col-md-2">
                        <label for="filter-status" class="form-label">Estado</label>
                        <select id="filter-status" class="form-select" x-model="filters.status">
                            <option value="">Todos</option>
                            <option value="success">Exitoso</option>
                            <option value="failed">Fallido</option>
                            <option value="suspicious">Sospechoso</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-event" class="form-label">Tipo de Evento</label>
                        <select id="filter-event" class="form-select" x-model="filters.event">
                            <option value="">Todos</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                            <option value="password_reset">Reset Password</option>
                            <option value="2fa">2FA</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filter-search" class="form-label">Usuario/Email</label>
                        <input id="filter-search" type="text" class="form-control" placeholder="Buscar..." x-model="filters.search">
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
                        <span class="visually-hidden">Cargando logs...</span>
                    </output>
                </div>

                <div x-show="!loading" x-cloak>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Usuario/Email</th>
                                    <th>Evento</th>
                                    <th>Estado</th>
                                    <th>IP</th>
                                    <th>User Agent</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-if="logs.length === 0">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <?= View::componentToString('components/admin/empty-state', [
                                                'icon' => 'inbox',
                                                'title' => 'Sin registros',
                                                'message' => 'Ajusta los filtros para ver más resultados',
                                            ]) ?>
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="log in logs" :key="log.id">
                                    <tr :class="{'table-danger': log.is_suspicious}">
                                        <td x-text="log.created_at"></td>
                                        <td>
                                            <div x-text="log.user_email"></div>
                                            <small class="text-muted" x-text="log.user_name"></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary" x-text="log.event_type"></span>
                                        </td>
                                        <td>
                                            <span class="badge" :class="{
                                            'bg-success': log.status === 'success',
                                            'bg-danger': log.status === 'failed',
                                            'bg-warning': log.is_suspicious
                                        }" x-text="log.status"></span>
                                        </td>
                                        <td>
                                            <code x-text="log.ip_address"></code>
                                        </td>
                                        <td>
                                            <small x-text="log.user_agent.substring(0, 50) + '...'"></small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info" @click="viewDetails(log)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <template x-if="log.is_suspicious">
                                                <button type="button" class="btn btn-sm btn-outline-danger" @click="blockIP(log.ip_address)">
                                                    <i class="bi bi-ban"></i>
                                                </button>
                                            </template>
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
