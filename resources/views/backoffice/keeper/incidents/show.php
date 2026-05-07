<?php

declare(strict_types=1);

/**
 * Detalle de Incidente con Formulario de Resolución
 *
 * Muestra el detalle completo de un incidente y permite resolverlo.
 */

$severityColors = [
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'secondary',
];
$severityColor = $severityColors[$incident['severity']] ?? 'secondary';
$isResolved = !empty($incident['resolved_at']);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2">
                <a href="/keeper/incidents" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="bi bi-exclamation-triangle text-<?= $severityColor ?>"></i>
                    Incidente #<?= (int) $incident['id'] ?>
                </h1>
                <?php if ($isResolved): ?>
                    <span class="badge bg-success ms-2">Resuelto</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark ms-2">Abierto</span>
                <?php endif; ?>
                <a href="/keeper/incidents/<?= (int) $incident['id'] ?>/edit" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        </div>
    </div>

    <!-- Flash messages -->
    <?php $flashError = \App\Core\Flash::get('error'); ?>
    <?php if ($flashError !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Detalle del incidente -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Detalle del Incidente</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Animal</dt>
                        <dd class="col-sm-8">
                            <strong><?= e($incident['animal_name']) ?></strong>
                            <?php if (!empty($incident['species'])): ?>
                                <small class="text-muted">(<?= e($incident['species']) ?>)</small>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Severidad</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $severityColor ?>">
                                <?= e(ucfirst($incident['severity'])) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8"><?= e($incident['description']) ?></dd>

                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8"><?= e(['open' => 'Abierto', 'monitoring' => 'En seguimiento', 'resolved' => 'Resuelto', 'archived' => 'Archivado'][$incident['status']] ?? ucfirst($incident['status'])) ?></dd>

                        <dt class="col-sm-4">Reportado</dt>
                        <dd class="col-sm-8">
                            <?= e(date('d/m/Y H:i', strtotime($incident['reported_at'] ?? $incident['created_at']))) ?>
                        </dd>

                        <?php if ($isResolved): ?>
                            <dt class="col-sm-4">Resuelto</dt>
                            <dd class="col-sm-8">
                                <?= e(date('d/m/Y H:i', strtotime((string) $incident['resolved_at']))) ?>
                            </dd>

                            <?php if (!empty($incident['resolution'])): ?>
                                <dt class="col-sm-4">Resolución</dt>
                                <dd class="col-sm-8"><?= e($incident['resolution']) ?></dd>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Formulario de resolución (solo si está abierto) -->
        <?php if (!$isResolved): ?>
            <div class="col-lg-5 mb-4">
                <div class="card shadow border-success h-100">
                    <div class="card-header py-3 bg-success text-white">
                        <h6 class="m-0 fw-bold">
                            <i class="bi bi-check-circle"></i> Marcar como Resuelto
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/keeper/incidents/<?= (int) $incident['id'] ?>/resolve">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Core\Csrf::token()) ?>">

                            <div class="mb-3">
                                <label for="resolution" class="form-label fw-semibold">
                                    Descripción de la resolución
                                </label>
                                <textarea
                                    id="resolution"
                                    name="resolution"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Describe cómo se resolvió el incidente…"></textarea>
                                <div class="form-text">Opcional, pero recomendado.</div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check2-circle"></i> Confirmar Resolución
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
