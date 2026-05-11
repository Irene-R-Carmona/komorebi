<?php

declare(strict_types=1);

/**
 * Galería pública de animales disponibles para adopción.
 *
 * Variables disponibles:
 *  - $animals: array<int, array<string, mixed>>  Lista de animales adoptables (v_adoptable_animals)
 */
?>

<section class="seccion seccion--activa">
    <div class="seccion__container">
        <header class="seccion__header">
            <h1 class="seccion__titulo">Adopciones</h1>
            <p class="seccion__subtitulo">
                Dale un hogar para siempre a uno de nuestros animales.
                Cada adopción es una historia de amor que empieza en el café.
            </p>
        </header>

        <?php if (empty($animals)): ?>
            <div class="adopciones__vacio">
                <i class="bi bi-heart" aria-hidden="true" style="font-size: 3rem; color: var(--color-acento);"></i>
                <p>No hay animales disponibles para adopción en este momento.</p>
                <p>¡Vuelve pronto! Actualizamos la lista regularmente.</p>
            </div>
        <?php else: ?>
            <ul class="adopciones__grid" role="list" aria-label="Animales disponibles para adopción">
                <?php foreach ($animals as $animal): ?>
                    <li class="adopciones__card">
                        <a href="/adopciones/<?= (int) $animal['id'] ?>" class="adopciones__card-link" aria-label="Ver detalles de <?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (!empty($animal['photo_url'])): ?>
                                <img
                                    class="adopciones__card-img"
                                    src="<?= htmlspecialchars((string) $animal['photo_url'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="Foto de <?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                    width="400"
                                    height="300">
                            <?php else: ?>
                                <div class="adopciones__card-img adopciones__card-img--placeholder" aria-hidden="true">
                                    <i class="bi bi-camera"></i>
                                </div>
                            <?php endif; ?>

                            <div class="adopciones__card-body">
                                <h2 class="adopciones__card-nombre"><?= htmlspecialchars((string) $animal['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="adopciones__card-especie">
                                    <i class="bi bi-tag" aria-hidden="true"></i>
                                    <?= htmlspecialchars((string) $animal['species_type'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                                <?php if (!empty($animal['cafe_name'])): ?>
                                    <p class="adopciones__card-cafe">
                                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                        <?= htmlspecialchars((string) $animal['cafe_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>
                                <span class="btn btn--primario btn--sm" aria-hidden="true">Conocerme</span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
