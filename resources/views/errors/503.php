<?php

declare(strict_types=1);

/**
 * Vista parcial: 503 Service Unavailable
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string|null $message   Mensaje de contexto para el usuario
 * - string|null $service   Nombre del servicio o componente no disponible
 */

$message ??= null;
$service ??= null;

?>
<div class="error-card" data-kanji="眠">
    <i class="bi bi-moon-stars error-icon" aria-hidden="true"></i>
    <p class="error-code">503</p>
    <h1 class="error-title">Servicio no disponible</h1>

    <p class="error-desc">
        <?= $message !== null && $message !== ''
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'El servicio está temporalmente fuera de servicio. Estamos trabajando para restaurarlo.' ?>
    </p>

    <?php if ($service !== null && $service !== ''): ?>
        <p class="error-desc error-desc--detail">
            Servicio afectado: <strong><?= htmlspecialchars($service, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    <?php endif; ?>

    <div class="error-actions">
        <a href="/" class="error-btn">Ir al inicio</a>
        <a href="/contacto" class="error-btn error-btn--alt">Contactar soporte</a>
    </div>
</div>
