<?php

declare(strict_types=1);

/**
 * Vista parcial: 401
 * Variables esperadas:
 * - string|null $titulo
 */
?>
<div class="error-card">
    <div class="error-icon"><i class="bi bi-key-fill"></i></div>
    <h1 class="error-code">401</h1>
    <h2 class="error-title">No Autenticado</h2>
    <p class="error-desc">
        Debes iniciar sesión para acceder a esta sección.
    </p>
    <a href="/auth/login" class="error-btn">Iniciar Sesión</a>
</div>
