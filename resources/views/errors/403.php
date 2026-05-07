<?php

declare(strict_types=1);

/**
 * Vista parcial: 403
 * Variables esperadas:
 * - string|null $titulo
 */
?>
<div class="error-card" data-kanji="境">
    <i class="bi bi-shield-fill-x error-icon" aria-hidden="true"></i>
    <p class="error-code">403</p>
    <h1 class="error-title">Acceso restringido</h1>
    <p class="error-desc">
        No tienes los permisos necesarios para acceder a esta sección.
        Si crees que es un error, contacta con administración.
    </p>
    <div class="error-actions">
        <a href="/" class="error-btn">Volver al inicio</a>
        <a href="/contacto" class="error-btn error-btn--alt">Contactar soporte</a>
    </div>
    <nav class="error-nav" aria-label="Páginas de ayuda">
        <a href="/" class="error-nav__link">Inicio</a>
        <a href="/contacto" class="error-nav__link">Contacto</a>
    </nav>
</div>
