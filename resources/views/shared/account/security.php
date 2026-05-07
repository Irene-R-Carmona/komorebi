<?php

use App\Support\DateFormatting;

/**
 * Vista: Historial de Seguridad
 *
 * @var array[] $auth_history
 */
?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="mb-4">
                <a href="/perfil" class="text-decoration-none small account-back-link">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mi cuenta
                </a>
            </div>

            <h1 class="h3 fw-bold mb-1 account-page-title">Seguridad y Autenticación</h1>
            <p class="text-secondary mb-4">Historial de eventos de seguridad en tu cuenta.</p>

            <!-- Historial de accesos -->
            <div class="settings-box mb-4">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <h2 class="settings-title">Historial de accesos</h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold small">Evento</th>
                                <th class="fw-semibold small">Estado</th>
                                <th class="fw-semibold small">IP Address</th>
                                <th class="fw-semibold small">Dispositivo</th>
                                <th class="fw-semibold small">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auth_history as $event): ?>
                                <tr>
                                    <td class="small">
                                        <span class="fw-medium"><?= htmlspecialchars($event['event_type']) ?></span>
                                        <?php if ($event['reason']): ?>
                                            <br><span class="text-muted small">(<?= htmlspecialchars($event['reason']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($event['success']): ?>
                                            <span class="badge bg-success-subtle text-success-emphasis small">
                                                <i class="bi bi-check-lg" aria-hidden="true"></i> Exitoso
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger-emphasis small">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i> Fallido
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <code class="text-body"><?= htmlspecialchars($event['ip_address']) ?></code>
                                    </td>
                                    <td class="small text-secondary">
                                        <?= htmlspecialchars($event['device_name'] ?? 'Desconocido') ?>
                                    </td>
                                    <td class="small text-secondary">
                                        <?= DateFormatting::toSpanishDateTime($event['created_at']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($auth_history)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4 small">
                                        No hay eventos de autenticación registrados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Zona peligrosa -->
            <div class="settings-box settings-box--danger">
                <div class="settings-header">
                    <span class="settings-icon">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    </span>
                    <h2 class="settings-title">Zona peligrosa</h2>
                </div>

                <p class="text-secondary small mb-3">
                    Las siguientes acciones son permanentes e irreversibles. Procede con precaución.
                </p>
                <a href="/account/delete" class="btn-komorebi btn-komorebi-danger">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Eliminar mi cuenta
                </a>
            </div>

        </div>
    </div>
</div>

<?php $this->end(); ?>
