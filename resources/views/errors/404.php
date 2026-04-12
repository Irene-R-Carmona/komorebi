<?php

declare(strict_types=1);

/**
 * Vista parcial: 404
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string $requestedPath
 * - array{href:string,label:string} $suggestedLink
 */
?>
<div class="error-card">
    <div class="error-icon"><i class="bi bi-signpost-2-fill"></i></div>
    <h1 class="error-code">404</h1>
    <h2 class="error-title">Página no encontrada</h2>

    <p class="error-desc">
        Parece que te has perdido en el bosque.<br>
        La página que buscas no existe o ha sido movida.
    </p>

    <?php if (!empty($requestedPath)): ?>
        <p class="error-desc error-desc--detail">
            Ruta solicitada: <strong><?= $requestedPath ?></strong>
        </p>
    <?php endif; ?>

    <div class="error-actions">
        <a href="<?= $suggestedLink['href'] ?? '/' ?>" class="error-btn">
            <?= $suggestedLink['label'] ?? 'Regresar al café' ?>
        </a>
    </div>

    <nav class="error-nav" aria-label="Otros destinos">
        <a href="/" class="error-nav__link"><i class="bi bi-house"></i> Inicio</a>
        <a href="/cafes" class="error-nav__link"><i class="bi bi-cup-hot"></i> Explorar cafés</a>
        <a href="/reservas" class="error-nav__link"><i class="bi bi-calendar-check"></i> Reservar mesa</a>
        <a href="/contacto" class="error-nav__link"><i class="bi bi-envelope"></i> Contacto</a>
    </nav>
</div>
