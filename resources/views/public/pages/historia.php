<?php

declare(strict_types=1);

/**
 * Vista: Historia (Narrativa lineal modular, estilo Linear.app)
 *
 * @var array $datos
 */

$hero = $datos['hero'] ?? [];
$mision = $datos['mision'] ?? [];
$historia = $datos['historia'] ?? [];
$equipo = $datos['equipo'] ?? [];
?>

<div class="static-page">
    <!-- Hero -->
    <header class="static-hero">
        <span class="static-hero__icon"><?= $hero['icono'] ?? '🍵' ?></span>
        <h1 class="static-hero__title"><?= htmlspecialchars($hero['titulo'] ?? 'Nuestra Historia', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="static-hero__subtitle"><?= htmlspecialchars($hero['subtitulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <!-- Misión (Statement grande) -->
    <?php if (!empty($mision)): ?>
        <section class="static-content" style="max-width: 800px; margin: 0 auto 4rem;">
            <h2 style="font-size: 1.875rem; line-height: 1.4; font-weight: 700; text-align: center; color: var(--color-primario); margin-bottom: 1.5rem;">
                <?= htmlspecialchars($mision['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p style="font-size: 1.125rem; line-height: 1.7; text-align: center; color: var(--color-texto-suave);">
                <?= htmlspecialchars($mision['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>
        </section>
    <?php endif; ?>

    <!-- Historia en secciones -->
    <?php if (!empty($historia)): ?>
        <section class="static-content" style="max-width: 800px; margin: 0 auto 4rem;">
            <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--color-acento);">
                <?= htmlspecialchars($historia['titulo'] ?? 'Nuestro viaje', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <?php if (!empty($historia['secciones'])): ?>
                <?php foreach ($historia['secciones'] as $seccion): ?>
                    <div style="margin-bottom: 3rem;">
                        <?php if (!empty($seccion['año'])): ?>
                            <h3 style="color: var(--color-acento); font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($seccion['año'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        <?php endif; ?>

                        <h4 style="font-size: 1.375rem; font-weight: 600; color: var(--color-primario); margin-bottom: 1rem;">
                            <?= htmlspecialchars($seccion['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>

                        <p style="line-height: 1.7; color: var(--color-texto);">
                            <?= htmlspecialchars($seccion['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Equipo (Team grid) -->
    <?php if (!empty($equipo) && !empty($equipo['miembros'])): ?>
        <section class="static-team">
            <h2 style="font-size: 1.75rem; font-weight: 700; text-align: center; margin-bottom: 3rem; color: var(--color-primario);">
                <?= htmlspecialchars($equipo['titulo'] ?? 'Nuestro Equipo', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <div class="static-team__grid">
                <?php foreach ($equipo['miembros'] as $miembro): ?>
                    <div class="static-team__member">
                        <?php if (!empty($miembro['foto'])): ?>
                            <img src="<?= htmlspecialchars($miembro['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($miembro['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                class="static-team__photo"
                                loading="lazy">
                        <?php else: ?>
                            <div class="static-team__photo" style="background: var(--color-fondo-alt); display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                                <?= mb_substr($miembro['nombre'], 0, 1) ?>
                            </div>
                        <?php endif; ?>

                        <h3 class="static-team__name"><?= htmlspecialchars($miembro['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="static-team__role"><?= htmlspecialchars($miembro['rol'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($miembro['descripcion'])): ?>
                            <p class="static-team__bio"><?= htmlspecialchars($miembro['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- CTA -->
    <div class="static-cta">
        <h3 class="static-cta__title">¿Quieres conocernos en persona?</h3>
        <p class="static-cta__text">Visítanos y descubre la experiencia Komorebi.</p>
        <a href="/reservas" class="btn">Reservar mesa</a>
    </div>
</div>
