<?php

declare(strict_types=1);

/**
 * Panel Keeper — Detalle de una solicitud de adopción.
 *
 * Variables disponibles:
 *  - $request:    array<string, mixed>  Datos de la solicitud con info del animal y el solicitante
 *  - $csrf_token: string                Token CSRF
 */

$flashSuccess = \App\Core\Flash::get('success');
$flashError = \App\Core\Flash::get('error');
$req = $request;
$isPending = ($req['status'] === 'pending');
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-heart text-primary" aria-hidden="true"></i>
                        Solicitud de Adopción #<?= (int) $req['id'] ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <a href="/keeper/adopciones" class="text-decoration-none">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a solicitudes
                        </a>
                    </p>
                </div>
                <span class="badge fs-6 <?= $req['status'] === 'pending' ? 'bg-warning text-dark' : ($req['status'] === 'approved' ? 'bg-success' : 'bg-danger') ?>">
                    <?= htmlspecialchars(ucfirst((string) $req['status']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if ($flashSuccess !== null): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashError !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Animal -->
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h2 class="h6 mb-0"><i class="bi bi-tag" aria-hidden="true"></i> Animal</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars((string) $req['animal_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Especie</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars((string) $req['species_type'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php if (!empty($req['is_adoptable'])): ?>
                            <dt class="col-sm-4">Adoptable</dt>
                            <dd class="col-sm-8">
                                <?= (bool) $req['is_adoptable']
                                    ? '<span class="badge bg-success">Sí</span>'
                                    : '<span class="badge bg-secondary">No</span>' ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Solicitante -->
        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h2 class="h6 mb-0"><i class="bi bi-person" aria-hidden="true"></i> Solicitante</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars((string) $req['applicant_name'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8">
                            <a href="mailto:<?= htmlspecialchars((string) $req['applicant_email'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) $req['applicant_email'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </dd>
                        <dt class="col-sm-4">Fecha</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars((string) ($req['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Mensaje del solicitante -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h2 class="h6 mb-0"><i class="bi bi-chat-left-text" aria-hidden="true"></i> Mensaje del solicitante</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($req['message'])): ?>
                        <blockquote class="blockquote mb-0">
                            <p><?= htmlspecialchars((string) $req['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        </blockquote>
                    <?php else: ?>
                        <p class="text-muted mb-0">El solicitante no adjuntó mensaje.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Acciones (solo si está pendiente) -->
        <?php if ($isPending): ?>
            <div class="col-12">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h2 class="h6 mb-0"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Decisión</h2>
                    </div>
                    <div class="card-body d-flex gap-3 flex-wrap">
                        <!-- Aprobar -->
                        <form method="POST" action="/keeper/adopciones/<?= (int) $req['id'] ?>/aprobar"
                            onsubmit="return confirm('¿Confirmar aprobación de la adopción?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg" aria-hidden="true"></i> Aprobar adopción
                            </button>
                        </form>

                        <!-- Rechazar -->
                        <form method="POST" action="/keeper/adopciones/<?= (int) $req['id'] ?>/rechazar" class="flex-grow-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-2">
                                <label for="keeper_notes" class="form-label mb-1">Notas para el solicitante <span class="text-muted">(opcional)</span></label>
                                <textarea id="keeper_notes" name="keeper_notes" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Motivo del rechazo..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-x-lg" aria-hidden="true"></i> Rechazar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
