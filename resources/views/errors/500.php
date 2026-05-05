<?php

declare(strict_types=1);

/**
 * Vista parcial: 500
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string|null $errorId      Identificador de referencia del error (log ID)
 * - bool        $show_details Mostrar detalles de excepción (solo en dev/debug)
 * - string|null $exception    Representación string de la excepción (solo si $show_details es true)
 */

$errorId ??= null;

?>
<div class="error-card" data-kanji="霧">
    <i class="bi bi-cloud-fog2 error-icon" aria-hidden="true"></i>
    <p class="error-code">500</p>
    <h1 class="error-title">Error del servidor</h1>
    <p class="error-desc">
        Algo ha salido mal en nuestra cocina.<br>
        El equipo técnico ya está limpiando el desastre.
    </p>

    <?php if ($errorId !== null && $errorId !== ''): ?>
        <p class="error-id">Ref: <?= htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <!-- Mostrar detalles sólo en entornos de desarrollo/debug -->
    <?php if (!empty($show_details) && isset($exception) && \is_string($exception) && $exception !== ''): ?>
        <div class="error-debug">
            <strong>Detalle (debug):</strong>
            <pre><?= htmlspecialchars($exception, ENT_QUOTES, 'UTF-8') ?></pre>
        </div>
    <?php endif; ?>

    <div class="error-actions">
        <a href="/" class="error-btn">Volver al inicio</a>
        <a href="/contacto" class="error-btn error-btn--alt">Contactar soporte</a>
    </div>
</div>
