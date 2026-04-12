<?php

declare(strict_types=1);

/**
 * Vista parcial: 500
 * Variables esperadas:
 * - string|null $titulo
 */
?>
<div class="error-card">
    <div class="error-icon">☕</div>
    <h1 class="error-code">500</h1>
    <h2 class="error-title">Error del Servidor</h2>
    <p class="error-desc">
        Algo ha salido mal en nuestra cocina.<br>
        El equipo técnico ya está limpiando el desastre.
    </p>
    <!-- Mostrar detalles sólo en entornos de desarrollo/debug -->
    <?php if (!empty($show_details) && isset($exception)): ?>
        <div class="error-debug">
            <strong>Detalle (debug):</strong>
            <pre>
<?= htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') ?>

Archivo: <?= htmlspecialchars($exception->getFile() . ':' . $exception->getLine(), ENT_QUOTES, 'UTF-8') ?>

Traza:
<?= htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8') ?>
            </pre>
        </div>
    <?php else: ?>
        <!-- No mostrar detalles en la vista pública por defecto -->
    <?php endif; ?>

    <a href="/" class="error-btn">Volver al Inicio</a>
</div>
