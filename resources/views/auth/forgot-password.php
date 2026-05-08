<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Recuperar contraseña
 *
 * Variables esperadas:
 * - string $csrf_token  (disponible como fallback, se prefiere Csrf::field())
 */
?>
<section class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-header__icon"><i class="bi bi-key-fill"></i></span>
            <h1 class="auth-header__titulo">Recupera tu contraseña</h1>
            <p class="auth-header__subtitulo">Recibirás un enlace para restablecer tu contraseña</p>
        </div>

        <?php if ($error = $this->flash('error')): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success = $this->flash('success')): ?>
            <div class="alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/forgot-password" class="auth-form" novalidate>
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="ejemplo@email.com"
                    required
                    autocomplete="email">
            </div>

            <button type="submit" class="btn btn--primario w-100 mt-2">
                Enviar instrucciones
            </button>
        </form>

        <div class="auth-footer">
            <a href="/login" class="auth-link small">← Volver al login</a>
        </div>
    </div>
</section>
