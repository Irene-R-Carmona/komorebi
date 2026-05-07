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
            <span class="auth-header__icon"><i class="bi bi-cup-hot-fill"></i></span>
            <h1 class="auth-header__titulo">Bienvenido de nuevo</h1>
            <p class="auth-header__subtitulo">Inicia sesión para gestionar tus reservas</p>
        </div>

        <?php if (isset($error) && $error !== ''): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
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
            <button type="submit" class="btn btn--primario w-100 mt-2" aria-label="Entrar">
                Entrar
            </button>

            <!-- Forgot Password -->
            <div class="text-center mt-3">
                <a href="/forgot-password" class="auth-link small">
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
