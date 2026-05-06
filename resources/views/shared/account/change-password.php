<?php

/**
 * Vista: Cambiar Contraseña
 */

use App\Core\Csrf;

?>

<?php $this->extend('layouts/main.php'); ?>

<?php $this->start('content'); ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

            <div class="mb-4">
                <a href="/account/security" class="text-decoration-none small account-back-link">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a seguridad
                </a>
            </div>

            <div class="settings-box">
                <div class="settings-header">
                    <span class="settings-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                    <h1 class="settings-title">Cambiar contraseña</h1>
                </div>

                <p class="text-secondary small mb-3">Actualiza tu contraseña de acceso.</p>

                <?php if ($error = $this->flash('error')): ?>
                    <div class="alert alert-danger small" role="alert">
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success = $this->flash('success')): ?>
                    <div class="alert alert-success small" role="alert">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/account/change-password" class="form-komorebi">
                    <?= Csrf::field() ?>

                    <div class="form-komorebi__group">
                        <label for="current_password" class="form-komorebi__label form-komorebi__label--required">
                            Contraseña actual
                        </label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="form-komorebi__input">
                    </div>

                    <div class="form-komorebi__group">
                        <label for="new_password" class="form-komorebi__label form-komorebi__label--required">
                            Nueva contraseña
                        </label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Mín. 8 caracteres"
                            class="form-komorebi__input">
                    </div>

                    <div class="form-komorebi__group">
                        <label for="confirm_password" class="form-komorebi__label form-komorebi__label--required">
                            Confirmar nueva contraseña
                        </label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="form-komorebi__input">
                    </div>

                    <button type="submit" class="btn-komorebi btn-komorebi-primary">
                        Actualizar contraseña
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<?php $this->end(); ?>
