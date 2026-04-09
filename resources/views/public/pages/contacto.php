<?php

declare(strict_types=1);

/**
 * Vista: Contacto (Grid de opciones, estilo Linear.app)
 *
 * @var array $datos
 */

$hero = $datos['hero'] ?? [];
$opciones = $datos['opciones'] ?? [];
?>

<div class="static-page">
    <!-- Hero -->
    <header class="static-hero">
        <span class="static-hero__icon"><?= $hero['icono'] ?? '📧' ?></span>
        <h1 class="static-hero__title"><?= htmlspecialchars($hero['titulo'] ?? 'Contacto', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="static-hero__subtitle"><?= htmlspecialchars($hero['subtitulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="static-hero__alert static-hero__alert--success">
                ✅ <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="static-hero__alert static-hero__alert--error">
                ❌ <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </header>

    <!-- Grid de opciones de contacto -->
    <div class="static-grid static-grid--3col">
        <?php foreach ($opciones as $opcion): ?>
            <div class="static-card">
                <span class="static-card__icon"><?= $opcion['icono'] ?></span>
                <h3 class="static-card__title"><?= htmlspecialchars($opcion['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="static-card__text"><?= htmlspecialchars($opcion['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>

                <?php if (!empty($opcion['link'])): ?>
                    <a href="<?= htmlspecialchars($opcion['link'], ENT_QUOTES, 'UTF-8') ?>" class="static-card__link">
                        <?= htmlspecialchars($opcion['link_texto'] ?? 'Ver más', ENT_QUOTES, 'UTF-8') ?> →
                    </a>
                <?php endif; ?>

                <?php if (!empty($opcion['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($opcion['email'], ENT_QUOTES, 'UTF-8') ?>" class="static-card__link" aria-label="Enviar email a <?= htmlspecialchars($opcion['email'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($opcion['email'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($opcion['telefono'])): ?>
                    <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $opcion['telefono']), ENT_QUOTES, 'UTF-8') ?>" class="static-card__link" aria-label="Llamar al <?= htmlspecialchars($opcion['telefono'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($opcion['telefono'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($opcion['horario'])): ?>
                    <p class="static-card__text" style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--color-texto-suave);">
                        🕐 <?= htmlspecialchars($opcion['horario'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- CTA con enlace a reservas -->
    <div class="static-cta">
        <h3 class="static-cta__title">¿Prefieres reservar directamente?</h3>
        <p class="static-cta__text">Reserva tu mesa en pocos clics.</p>
        <a href="/reservas" class="btn">Reservar mesa</a>
    </div>
</div>
