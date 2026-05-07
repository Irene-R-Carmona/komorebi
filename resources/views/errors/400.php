<?php

declare(strict_types=1);

/**
 * Vista parcial: 400 Bad Request
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string $message - Mensaje de error específico
 */
?>
<div class="error-card" data-kanji="誤">
    <i class="bi bi-exclamation-triangle-fill error-icon" aria-hidden="true"></i>
    <p class="error-code">400</p>
    <h1 class="error-title">Solicitud incorrecta</h1>

    <p class="error-desc">
        <?= isset($message) && $message !== ''
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'La solicitud no pudo ser procesada por datos inválidos.' ?>
    </p>

    <div class="error-actions">
        <a href="/" class="error-btn">Ir al inicio</a>
    </div>

    <nav class="error-nav" aria-label="Páginas de ayuda">
        <a href="/contacto" class="error-nav__link">Contacto</a>
    </nav>
</div>
