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
        <section class="static-mission">
            <h2 class="static-mission__title">
                <?= htmlspecialchars($mision['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="static-mission__text">
                <?= htmlspecialchars($mision['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>
        </section>
    <?php endif; ?>

    <!-- Historia en secciones -->
    <?php if (!empty($historia)): ?>
        <section class="static-history">
            <h2 class="static-history__title">
                <?= htmlspecialchars($historia['titulo'] ?? 'Nuestro viaje', ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <?php if (!empty($historia['secciones'])): ?>
                <?php foreach ($historia['secciones'] as $seccion): ?>
                    <div class="static-history__entry">
                        <?php if (!empty($seccion['año'])): ?>
                            <h3 class="static-history__year">
                                <?= htmlspecialchars($seccion['año'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        <?php endif; ?>

                        <h4 class="static-history__entry-title">
                            <?= htmlspecialchars($seccion['titulo'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>

                        <p class="static-history__body">
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
            <h2 class="static-team__section-title">
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
                            <div class="static-team__photo static-team__photo--initial">
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
