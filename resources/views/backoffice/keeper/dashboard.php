<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Dashboard de Bienestar Animal (Keeper)
 *
 * Vista principal para gestión de animales, logs de cuidado,
 * estado de salud e incidentes.
 */

// Helper functions para la vista
$getStatusBadgeClass = function ($status) {
    return match ($status) {
        'active' => 'success',
        'resting' => 'warning',
        'sick' => 'danger',
        'retired' => 'secondary',
        default => 'secondary'
    };
};

$getStatusLabel = function ($status) {
    return match ($status) {
        'active' => 'Activo',
        'resting' => 'Reposo',
        'sick' => 'Enfermo',
        'retired' => 'Retirado',
        default => ucfirst($status)
    };
};

$getSeverityBadgeClass = static function (string $severity): string {
    return match ($severity) {
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        default => 'secondary',
    };
};
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Dashboard - Bienestar Animal</h1>
                    <p class="text-muted mb-0">Gestión integral del bienestar de los animales</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#incidentModal">
                        <i class="bi bi-exclamation-triangle"></i> Reportar Incidente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid stats-grid--4 mb-4">
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'heart-fill',
            'variant' => 'primary',
            'label' => 'Animales Activos',
            'value' => $stats['total_animals'] ?? 0,
        ]) ?>
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'journal-check',
            'variant' => 'success',
            'label' => 'Logs Hoy',
            'value' => $stats['logs_today'] ?? 0,
        ]) ?>
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'exclamation-triangle-fill',
            'variant' => 'warning',
            'label' => 'Incidentes Activos',
            'value' => count($active_incidents),
        ]) ?>
        <?= View::componentToString('components/admin/stat-card', [
            'icon' => 'graph-up',
            'variant' => 'info',
            'label' => 'Promedio Interacciones',
            'value' => ($stats['avg_interactions'] ?? 0) . '/día',
        ]) ?>
    </div>

    <!-- Widget: Alertas de Health Checks -->
    <?php if (!empty($active_alerts) && count($active_alerts) > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow border-warning">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                Alertas de Salud Activas (últimos 7 días)
                            </h6>
                            <a href="/keeper/health-checks" class="btn btn-sm btn-outline-dark">
                                Ver Dashboard de Chequeos
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach (array_slice($active_alerts, 0, 3) as $alert): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="alert alert-danger mb-0">
                                        <h6 class="alert-heading">
                                            <strong><?= htmlspecialchars($alert['animal_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <br>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($alert['check_date'])) ?></small>
                                        </h6>
                                        <?php if (!empty($alert['alerts']) && is_array($alert['alerts'])): ?>
                                            <ul class="mb-2 ps-3 small">
                                                <?php foreach (array_slice($alert['alerts'], 0, 2) as $alertMsg): ?>
                                                    <li><?= htmlspecialchars($alertMsg, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <a href="/keeper/health-checks/<?= $alert['id'] ?>" class="alert-link small">Ver chequeo completo →</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($active_alerts) > 3): ?>
                            <div class="text-center mt-2">
                                <a href="/keeper/health-checks" class="btn btn-sm btn-warning">
                                    Ver todas las alertas (<?= count($active_alerts) ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Animales -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Animales del Café</h6>
                </div>
                <div class="card-body">
                    <div class="row" id="animals-grid" style="overflow-x:hidden; max-width:100%;">
                        <?php
                        // Crear array de IDs de animales pendientes de chequeo (Semana 6)
                        $pendingAnimalIds = array_column($pending_animals ?? [], 'animal_id');
?>
                        <?php foreach ($animals as $animal): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                                <div class="card animal-card h-100 <?= in_array($animal['id'], $pendingAnimalIds, true) ? 'border-warning' : '' ?>" style="overflow:hidden;">
                                    <!-- Foto del animal -->
                                    <div class="animal-photo-container position-relative">
                                        <?php if (!empty($animal['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($animal['image_url']) ?>"
                                                alt="Foto de <?= htmlspecialchars($animal['name']) ?>"
                                                class="card-img-top animal-photo"
                                                style="width:100%; height:180px; object-fit:cover;"
                                                onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="animal-photo-placeholder d-none align-items-center justify-content-center bg-light"
                                                style="height:180px; font-size:2.5rem;">🐾</div>
                                        <?php else: ?>
                                            <div class="animal-photo-placeholder d-flex align-items-center justify-content-center bg-light"
                                                style="height:180px; font-size:2.5rem;">🐾</div>
                                        <?php endif; ?>

                                        <!-- Botón de subir foto -->
                                        <button class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2 upload-photo-btn"
                                            data-animal-id="<?= $animal['id'] ?>"
                                            data-animal-name="<?= htmlspecialchars($animal['name']) ?>"
                                            title="Subir foto">
                                            <i class="bi bi-camera-fill"></i>
                                        </button>
                                    </div>

                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($animal['name']) ?></h5>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge bg-<?= $getStatusBadgeClass($animal['current_status']) ?>">
                                                    <?= $getStatusLabel($animal['current_status']) ?>
                                                </span>
                                                <?php if (in_array($animal['id'], $pendingAnimalIds, true)): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-clipboard-pulse"></i> Chequeo Pendiente
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <p class="card-text text-muted small mb-2">
                                            <i class="bi bi-tag"></i> <?= htmlspecialchars($animal['species_type']) ?>
                                            <?php if ($animal['age'] > 0): ?>
                                                · <i class="bi bi-calendar"></i> <?= $animal['age'] ?> años
                                            <?php endif; ?>
                                        </p>

                                        <?php if (!empty($animal['personality'])): ?>
                                            <p class="card-text small mb-3">
                                                <strong>Personalidad:</strong> <?= htmlspecialchars($animal['personality']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="mt-auto">
                                            <div class="d-flex gap-1 mb-2">
                                                <!-- Log de cuidado -->
                                                <button class="btn btn-sm btn-outline-success flex-fill log-care-btn"
                                                    data-animal-id="<?= $animal['id'] ?>"
                                                    data-animal-name="<?= htmlspecialchars($animal['name']) ?>">
                                                    <i class="bi bi-journal-plus"></i> Cuidar
                                                </button>

                                                <!-- Chequeo de Salud -->
                                                <?php if (in_array($animal['id'], $pendingAnimalIds, true)): ?>
                                                    <a href="/keeper/health-checks/create/<?= $animal['id'] ?>"
                                                        class="btn btn-sm btn-warning flex-fill">
                                                        <i class="bi bi-clipboard-pulse"></i> Chequeo
                                                    </a>
                                                <?php else: ?>
                                                    <a href="/keeper/health-checks/history/<?= $animal['id'] ?>"
                                                        class="btn btn-sm btn-outline-success flex-fill">
                                                        <i class="bi bi-check-circle"></i> Historial
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Cambiar estado -->
                                                <button class="btn btn-sm btn-outline-warning change-status-btn"
                                                    data-animal-id="<?= $animal['id'] ?>"
                                                    data-status="<?= $animal['current_status'] ?>">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                            </div>

                                            <!-- Último check -->
                                            <?php if (!empty($animal['last_check_at'])): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i>
                                                    Último check: <?= date('d/m H:i', strtotime($animal['last_check_at'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div><!-- /.card-body -->
                                </div><!-- /.card -->
                            </div><!-- /.col -->
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Recientes e Incidentes -->
    <div class="row">
        <!-- Logs Recientes -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Logs de Cuidado Recientes</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_logs)): ?>
                        <p class="text-muted mb-0">No hay logs de cuidado registrados hoy.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recent_logs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($log['animal_name']) ?>
                                                    <small class="text-muted">- Chequeo de salud</small>
                                                </h6>
                                                <?php if (!empty($log['notes'])): ?>
                                                    <p class="mb-1 small"><?= htmlspecialchars($log['notes']) ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> <?= date('H:i', strtotime($log['created_at'])) ?>
                                                    <?php if (!empty($log['keeper_name'])): ?>
                                                        por <?= htmlspecialchars($log['keeper_name']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Incidentes Activos -->
<div class="col-lg-4 mb-4">
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-warning">Incidentes Activos</h6>
        </div>
        <div class="card-body">
            <?php if (empty($active_incidents)): ?>
                <p class="text-muted mb-0">No hay incidentes activos.</p>
            <?php else: ?>
                <?php foreach ($active_incidents as $incident): ?>
                    <div class="incident-item border-start border-warning border-4 ps-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 small">
                                <?= htmlspecialchars($incident['animal_name']) ?>
                            </h6>
                            <span class="badge bg-<?= $getSeverityBadgeClass($incident['severity']) ?> small">
                                <?= ucfirst($incident['severity']) ?>
                            </span>
                        </div>
                        <p class="mb-1 small text-muted">
                            <?= htmlspecialchars(substr($incident['description'], 0, 100)) ?>...
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <?= date('d/m H:i', strtotime($incident['created_at'])) ?>
                            </small>
                            <button class="btn btn-sm btn-outline-success resolve-incident-btn"
                                data-incident-id="<?= $incident['id'] ?>">
                                Resolver
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>

<!-- Modal para subir foto -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Foto - <span id="animal-name-modal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadPhotoForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="animal_id" id="upload-animal-id">

                    <div class="mb-3">
                        <label for="photo-file" class="form-label">Seleccionar Foto</label>
                        <input type="file" class="form-control" id="photo-file" name="photo"
                            accept="image/*" required>
                        <div class="form-text">
                            Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB.
                        </div>
                    </div>

                    <div id="photo-preview" class="text-center d-none">
                        <img id="preview-img" class="img-fluid rounded" style="max-height: 200px;" alt="Vista previa de la foto">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="upload-btn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Subir Foto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para log de cuidado -->
<div class="modal fade" id="logCareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Cuidado - <span id="log-animal-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="logCareForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="animal_id" id="log-animal-id">

                    <div class="mb-3">
                        <label for="activity_type" class="form-label">Tipo de Actividad *</label>
                        <select class="form-select" id="activity_type" name="activity_type" required>
                            <option value="">Seleccionar...</option>
                            <option value="feeding">Alimentación</option>
                            <option value="cleaning">Limpieza</option>
                            <option value="exercise">Ejercicio</option>
                            <option value="medical">Atención médica</option>
                            <option value="social">Interacción social</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="duration_minutes" class="form-label">Duración (minutos)</label>
                            <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="mood_before" class="form-label">Estado Antes</label>
                            <select class="form-select" id="mood_before" name="mood_before">
                                <option value="">Seleccionar...</option>
                                <option value="happy">Feliz</option>
                                <option value="calm">Calmado</option>
                                <option value="stressed">Estresado</option>
                                <option value="aggressive">Agresivo</option>
                                <option value="tired">Cansado</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="mood_after" class="form-label">Estado Después</label>
                        <select class="form-select" id="mood_after" name="mood_after">
                            <option value="">Seleccionar...</option>
                            <option value="happy">Feliz</option>
                            <option value="calm">Calmado</option>
                            <option value="stressed">Estresado</option>
                            <option value="aggressive">Agresivo</option>
                            <option value="tired">Cansado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="log-btn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cambiar estado -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Estado de Salud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changeStatusForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="animal_id" id="status-animal-id">

                    <div class="mb-3">
                        <label for="health_status" class="form-label">Estado de Salud *</label>
                        <select class="form-select" id="health_status" name="health_status" required>
                            <option value="active">Activo</option>
                            <option value="resting">Descansando</option>
                            <option value="sick">Enfermo</option>
                            <option value="retired">Retirado</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status_notes" class="form-label">Notas (opcional)</label>
                        <textarea class="form-control" id="status_notes" name="notes" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="status-btn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Cambiar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para incidentes -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reportar Incidente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="incidentForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="mb-3">
                        <label for="incident_animal_id" class="form-label">Animal *</label>
                        <select class="form-select" id="incident_animal_id" name="animal_id" required>
                            <option value="">Seleccionar animal...</option>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?= $animal['id'] ?>"><?= htmlspecialchars($animal['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="severity" class="form-label">Severidad *</label>
                        <select class="form-select" id="severity" name="severity" required>
                            <option value="low">Baja</option>
                            <option value="medium" selected>Media</option>
                            <option value="high">Alta</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="incident-btn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Reportar Incidente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para resolver incidente -->
<div class="modal fade" id="resolveIncidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resolver Incidente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resolveIncidentForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="incident_id" id="resolve-incident-id">

                    <div class="mb-3">
                        <label for="resolution" class="form-label">Resolución</label>
                        <textarea class="form-control" id="resolution" name="resolution" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="resolve-btn">
                        <span class="spinner-border spinner-border-sm d-none"></span>
                        Resolver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast notifications -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="successMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="errorMessage"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="/js/pages/keeperHelpers.js" nonce="<?= $cspNonce ?? '' ?>"></script>

<!-- JavaScript para funcionalidades -->
<script src="/js/sections/keeper-dashboard.js" nonce="<?= $cspNonce ?? '' ?>"></script>
