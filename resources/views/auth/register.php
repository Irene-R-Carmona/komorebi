<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Registro
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string|null $error
 * - array{name?:string,email?:string} $old
 *
 * Nota: Los datos llegan escapados automáticamente por View::render() (escape recursivo).
 */
?>
<section class="auth-page">
    <script src="/js/sections/auth.js" nonce="<?= $cspNonce ?? '' ?>"></script>

    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-header__icon"><i class="bi bi-leaf-fill"></i></span>
            <h1 class="auth-header__titulo">Crear Cuenta</h1>
            <p class="auth-header__subtitulo">Únete a la comunidad Komorebi</p>
        </div>

        <?php if (isset($error) && $error !== ''): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="/registro" method="POST" class="auth-form" data-validate="password">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nombre completo</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-input"
                    placeholder="Tu nombre"
                    required
                    autocomplete="name"
                    value="<?= $old['name'] ?? '' ?>">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="ejemplo@email.com"
                    required
                    autocomplete="email"
                    value="<?= $old['email'] ?? '' ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="Mínimo 8 caracteres"
                    required
                    minlength="8"
                    autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">Confirmar contraseña</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    class="form-input"
                    placeholder="Repite la contraseña"
                    required
                    autocomplete="new-password">
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn btn--primario auth-btn-full">
                    Registrarse
                </button>
            </div>
        </form>

        <div class="auth-footer">
            ¿Ya tienes cuenta? <a href="/login" class="auth-link">Inicia sesión</a>
        </div>
    </div>
</section>
