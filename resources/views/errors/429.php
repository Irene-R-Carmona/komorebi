<?php

declare(strict_types=1);

/**
 * Vista parcial: 429 Too Many Requests
 *
 * Variables esperadas:
 * - string|null $titulo
 * - int|null    $retryAfter  Segundos hasta que se puede reintentar (null si desconocido)
 * - string|null $message     Mensaje personalizado del limitador de tasa
 */

$retryAfter ??= null;
$message ??= null;

?>
<div class="error-card" data-kanji="波">
    <i class="bi bi-water error-icon" aria-hidden="true"></i>
    <p class="error-code">429</p>
    <h1 class="error-title">Demasiadas solicitudes</h1>

    <p class="error-desc">
        <?= $message !== null && $message !== ''
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'Has enviado demasiadas peticiones en poco tiempo. Espera un momento antes de continuar.' ?>
    </p>

    <?php if ($retryAfter !== null && $retryAfter > 0): ?>
        <p class="error-desc error-desc--detail">
            Podrás reintentar en <strong><?= htmlspecialchars((string) $retryAfter, ENT_QUOTES, 'UTF-8') ?> segundos</strong>.
        </p>
    <?php endif; ?>

    <div class="error-actions">
        <a href="/" class="error-btn">Ir al inicio</a>
    </div>
</div>
