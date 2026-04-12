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
<div class="error-card">
    <div class="error-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <h1 class="error-code">400</h1>
    <h2 class="error-title">Solicitud Incorrecta</h2>

    <p class="error-desc">
        La solicitud enviada no es válida o contiene parámetros incorrectos.
    </p>

    <?php if (!empty($message)): ?>
        <p class="error-desc error-desc--detail">
            Detalle: <strong><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    <?php endif; ?>

    <div class="error-actions">
        <a href="<?= $_SERVER['HTTP_REFERER'] ?? '/' ?>" class="error-btn">
            Volver atrás
        </a>
        <a href="/" class="error-btn error-btn--alt">
            Ir al inicio
        </a>
    </div>
</div>
