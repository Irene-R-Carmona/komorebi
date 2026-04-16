<?php

declare(strict_types=1);

/**
 * Listado de Incidentes Activos
 *
 * Muestra todos los incidentes abiertos con severidad, animal y acciones.
 */

$severityBadge = static function (string $severity): string {
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
                    <h1 class="h3 mb-1">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        Incidentes Activos
                    </h1>
                    <p class="text-muted mb-0">Incidentes abiertos sin resolver</p>
                </div>
                <a href="/keeper/incidents/create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Reportar Incidente
                </a>
            </div>
        </div>
    </div>

    <!-- Flash messages -->
    <?php $flashSuccess = \App\Core\Flash::get('success'); ?>
    <?php if ($flashSuccess !== null): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php $flashError = \App\Core\Flash::get('error'); ?>
    <?php if ($flashError !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabla de incidentes -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        Incidentes sin resolver (<?= count($incidents) ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($incidents)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            No hay incidentes activos. ¡Todo en orden!
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Animal</th>
                                        <th>Severidad</th>
                                        <th>Descripción</th>
                                        <th>Reportado</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidents as $inc): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($inc['animal_name']) ?></strong>
                                                <small class="d-block text-muted"><?= e($inc['species'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $severityBadge($inc['severity']) ?>">
                                                    <?= e(ucfirst($inc['severity'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-truncate" style="max-width:260px">
                                                <?= e($inc['description']) ?>
                                            </td>
                                            <td>
                                                <small><?= e(date('d/m/Y H:i', strtotime($inc['reported_at'] ?? $inc['created_at']))) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?= e(ucfirst($inc['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="/keeper/incidents/<?= (int) $inc['id'] ?>" class="btn btn-sm btn-outline-primary">
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
            </div>
        </div>
    </div>
</div>
