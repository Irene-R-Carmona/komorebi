<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Login
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string|null $error
 * - array{email?:string} $old
 *
 * Nota: Los datos llegan escapados automáticamente por View::render() (escape recursivo).
 */
?>
<section class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <span class="auth-header__icon">☕</span>
            <h1 class="auth-header__titulo">Bienvenido de nuevo</h1>
            <p class="auth-header__subtitulo">Inicia sesión para gestionar tus reservas</p>
        </div>

        <?php if (isset($error) && $error !== ''): ?>
            <div class="alert-error">
                <span>⚠️</span> <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="/login" method="POST" class="auth-form" autocomplete="on" novalidate>
            <?= Csrf::field() ?>

            <!-- Email -->
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

            <!-- Password -->
            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password">
            </div>

            <!-- Submit -->
            <button type="submit" class="btn btn--primario" style="width: 100%; margin-top: 0.5rem;">
                Entrar
            </button>

            <!-- Forgot Password -->
            <div style="text-align: center; margin-top: 1rem;">
                <a href="/auth/forgot-password" class="auth-link" style="font-size: 0.9rem;">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>

        <div class="auth-footer">
            ¿No tienes cuenta?
            <a href="/registro" class="auth-link">Regístrate aquí</a>
        </div>
    </div>
</section>
