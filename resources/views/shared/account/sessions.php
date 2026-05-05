<?php

use App\Core\Csrf;

/**
 * Vista: Gestión de Sesiones Activas
 *
 * @var array[] $sessions
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

            <h1 class="h3 fw-bold mb-1 account-page-title">Mis Sesiones Activas</h1>
            <p class="text-secondary mb-4">Gestiona los dispositivos conectados a tu cuenta.</p>

            <?php if ($success = $this->flash('success')): ?>
                <div class="alert alert-success small" role="alert">
                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error = $this->flash('error')): ?>
                <div class="alert alert-danger small" role="alert">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Tabla de sesiones -->
            <div class="settings-box mb-4">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-laptop" aria-hidden="true"></i></span>
                    <h2 class="settings-title">Dispositivos conectados</h2>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold small">Dispositivo</th>
                                <th class="fw-semibold small">IP Address</th>
                                <th class="fw-semibold small">Última actividad</th>
                                <th class="fw-semibold small">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td class="small">
                                        <span class="fw-medium">
                                            <?php if ($session['is_current'] ?? false): ?>
                                                <i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($session['device_name'] ?? 'Desconocido') ?>
                                        </span>
                                        <?php if ($session['is_current'] ?? false): ?>
                                            <span class="badge bg-success ms-1">Sesión actual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-secondary">
                                        <code class="text-body"><?= htmlspecialchars($session['ip_address']) ?></code>
                                    </td>
                                    <td class="small text-secondary">
                                        <?= date('d/m/Y H:i', strtotime($session['last_activity'])) ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="/account/sessions/revoke/<?= (int) $session['id'] ?>">
                                            <?= Csrf::field() ?>
                                            <button
                                                type="button"
                                                class="btn-komorebi btn-komorebi-secondary btn-komorebi--sm"
                                                data-action="confirm"
                                                data-confirm="¿Estás seguro de que quieres revocar esta sesión?">
                                                Revocar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($sessions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4 small">
                                        No hay sesiones activas registradas.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Revocar todas -->
            <div class="settings-box">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-shield-x" aria-hidden="true"></i></span>
                    <h2 class="settings-title">Opciones rápidas</h2>
                </div>

                <p class="text-secondary small mb-3">
                    Cierra todas las sesiones activas en otros dispositivos, excepto la actual.
                </p>
                <form method="POST" action="/account/sessions/revoke-all">
                    <?= Csrf::field() ?>
                    <button
                        type="button"
                        class="btn-komorebi btn-komorebi-danger"
                        data-action="confirm"
                        data-confirm="Esto revocará todas tus otras sesiones. ¿Continuar?">
                        <i class="bi bi-shield-x" aria-hidden="true"></i>
                        Revocar todas las demás sesiones
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<?php $this->end(); ?>
