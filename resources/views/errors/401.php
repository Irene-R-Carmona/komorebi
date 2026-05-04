<?php

declare(strict_types=1);

/**
 * Vista parcial: 401
 * Variables esperadas:
 * - string|null $titulo
 */
?>
<div class="error-card" data-kanji="鍵">
    <i class="bi bi-key-fill error-icon" aria-hidden="true"></i>
    <p class="error-code">401</p>
    <h1 class="error-title">No autenticado</h1>
    <p class="error-desc">
        Debes iniciar sesión para acceder a esta sección.
    </p>
    <div class="error-actions">
        <a href="/login" class="error-btn">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            Iniciar sesión
        </a>
        <a href="/registro" class="error-btn error-btn--alt">Registrarse</a>
    </div>
    <nav class="error-nav" aria-label="Páginas de ayuda">
        <a href="/" class="error-nav__link">Inicio</a>
    </nav>
</div>
