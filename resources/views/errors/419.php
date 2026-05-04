<?php

declare(strict_types=1);

/**
 * Vista parcial: 419 CSRF / Sesión expirada
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string      $refererPath  Ruta previa segura (extraída en controller, solo path)
 */

$refererPath ??= '/';

?>
<div class="error-card" data-kanji="時">
    <i class="bi bi-clock-history error-icon" aria-hidden="true"></i>
    <p class="error-code">419</p>
    <h1 class="error-title">Sesión expirada</h1>
    <p class="error-desc">
        Por seguridad, tu sesión ha expirado o el token CSRF no es válido.<br>
        Vuelve al formulario e inténtalo de nuevo.
    </p>
    <div class="error-actions">
        <a href="<?= htmlspecialchars($refererPath, ENT_QUOTES, 'UTF-8') ?>"
            class="error-btn">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver al formulario
        </a>
        <a href="/" class="error-btn error-btn--alt">Ir al inicio</a>
    </div>
</div>
