<?php

declare(strict_types=1);

/**
 * Panel Keeper — Solicitudes de adopción pendientes.
 *
 * Variables disponibles:
 *  - $pending:    array<int, array<string, mixed>>  Solicitudes pendientes (v_pending_adoptions)
 *  - $csrf_token: string                            Token CSRF
 */

$flashSuccess = \App\Core\Flash::get('success');
$flashError = \App\Core\Flash::get('error');
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-heart-fill text-primary" aria-hidden="true"></i>
                        Solicitudes de Adopción Pendientes
                    </h1>
                    <p class="text-muted mb-0">Revisa y gestiona las solicitudes recibidas</p>
                </div>
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

    <!-- Tabla de solicitudes -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h2 class="h6 m-0 fw-bold text-primary">
                        <?= count($pending) ?> solicitud<?= count($pending) !== 1 ? 'es' : '' ?> pendiente<?= count($pending) !== 1 ? 's' : '' ?>
                    </h2>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($pending)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle" style="font-size: 2.5rem;" aria-hidden="true"></i>
                            <p class="mt-2 mb-0">No hay solicitudes pendientes de revisión.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" aria-label="Solicitudes de adopción pendientes">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Animal</th>
                                        <th scope="col">Solicitante</th>
                                        <th scope="col">Mensaje</th>
                                        <th scope="col">Fecha</th>
                                        <th scope="col" class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending as $req): ?>
                                        <tr>
                                            <td class="text-muted small"><?= (int) $req['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars((string) $req['animal_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars((string) $req['species_type'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars((string) $req['applicant_name'], ENT_QUOTES, 'UTF-8') ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars((string) $req['applicant_email'], ENT_QUOTES, 'UTF-8') ?></small>
                                            </td>
                                            <td class="small" style="max-width: 200px;">
                                                <?php if (!empty($req['message'])): ?>
                                                    <span title="<?= htmlspecialchars((string) $req['message'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars(mb_strimwidth((string) $req['message'], 0, 80, '…'), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin mensaje</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= htmlspecialchars((string) ($req['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-end">
                                                <!-- Aprobar -->
                                                <form method="POST" action="/keeper/adopciones/<?= (int) $req['id'] ?>/aprobar"
                                                    class="d-inline"
                                                    onsubmit="return confirm('¿Aprobar la adopción de <?= htmlspecialchars(addslashes((string) $req['animal_name']), ENT_QUOTES, 'UTF-8') ?>?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Aprobar solicitud">
                                                        <i class="bi bi-check-lg" aria-hidden="true"></i> Aprobar
                                                    </button>
                                                </form>

                                                <!-- Rechazar -->
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger ms-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rechazar-modal"
                                                    data-request-id="<?= (int) $req['id'] ?>"
                                                    data-animal-name="<?= htmlspecialchars((string) $req['animal_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Rechazar solicitud">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i> Rechazar
                                                </button>
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

<!-- Modal compartido para rechazar solicitudes de adopción (fuera de la tabla para compatibilidad Bootstrap) -->
<div class="modal fade" id="rechazar-modal" tabindex="-1"
    aria-labelledby="rechazar-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rechazar-modal-label">Rechazar solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" id="rechazar-form" action="">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="rechazar-keeper-notes" class="form-label">
                        Notas para el solicitante <span class="text-muted">(opcional)</span>
                    </label>
                    <textarea
                        id="rechazar-keeper-notes"
                        name="keeper_notes"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        placeholder="Motivo del rechazo..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rechazar solicitud</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
    (function() {
        var rechazarModal = document.getElementById('rechazar-modal');
        if (!rechazarModal) {
            return;
        }
        rechazarModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var requestId = button.getAttribute('data-request-id');
            var animalName = button.getAttribute('data-animal-name');
            rechazarModal.querySelector('#rechazar-modal-label').textContent =
                'Rechazar solicitud \u2014 ' + animalName;
            rechazarModal.querySelector('#rechazar-form').setAttribute(
                'action', '/keeper/adopciones/' + requestId + '/rechazar'
            );
            rechazarModal.querySelector('#rechazar-keeper-notes').value = '';
        });
    }());
</script>
