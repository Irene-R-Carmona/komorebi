<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Establecer nueva contraseña
 *
 * Variables esperadas:
 * - string $token       Token de reset (URL param)
 * - string $csrf_token  (disponible como fallback)
 */
?>
<section class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-header__icon"><i class="bi bi-shield-lock-fill"></i></span>
            <h1 class="auth-header__titulo">Nueva contraseña</h1>
            <p class="auth-header__subtitulo">Elige una contraseña segura para tu cuenta</p>
        </div>

        <?php if ($error = $this->flash('error')): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auth/reset-password" class="auth-form" novalidate>
            <?= Csrf::field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="form-group">
                <label for="new_password" class="form-label">Nueva contraseña</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    class="form-input"
                    placeholder="Mín. 8 caracteres"
                    required
                    minlength="8"
                    autocomplete="new-password">
                <span class="form-hint">Usa mayúsculas, minúsculas, números y símbolos.</span>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-input"
                    placeholder="Repite tu contraseña"
                    required
                    minlength="8"
                    autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn--primario w-100 mt-2">
                Actualizar contraseña
            </button>
        </form>

        <div class="auth-footer">
            <a href="/login" class="auth-link small">← Volver al login</a>
        </div>
    </div>
</section>
