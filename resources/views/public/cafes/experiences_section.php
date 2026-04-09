<?php

/**
 * experiences_section.php - Sección de Experiencias/Pases
 *
 * Renderiza las experiencias disponibles para el café actual.
 * Se incluye en cafes/show.php
 */

if (empty($experiences)):
    ?>
    <section class="experiences-section">
        <header class="seccion__header">
            <h2 class="seccion__titulo">Experiencias Disponibles</h2>
            <p class="seccion__subtitulo">Pases para disfrutar con nuestros animales</p>
        </header>
        <div class="catalogo__vacio">
            <p>No hay experiencias disponibles en este momento.</p>
        </div>
    </section>
<?php
        return;
endif;
?>

<section class="experiences-section">
    <header class="seccion__header">
        <h2 class="seccion__titulo">Experiencias Disponibles</h2>
        <p class="seccion__subtitulo">Pases para disfrutar con nuestros animales</p>
    </header>

    <div class="experiences-grid">
        <?php foreach ($experiences as $exp): ?>
            <?php
            // Decodificar datos JSON
            $attrs = [];
            if (!empty($exp['attributes'])) {
                try {
                    $attrs = json_decode($exp['attributes'], true, 512, JSON_THROW_ON_ERROR) ?? [];
                } catch (Exception) {
                    $attrs = [];
                }
            }

            $animalTargets = [];
            if (!empty($exp['target_animal_types'])) {
                try {
                    $animalTargets = json_decode($exp['target_animal_types'], true, 512, JSON_THROW_ON_ERROR) ?? [];
                } catch (Exception) {
                    $animalTargets = [];
                }
            }

            $duration = (int) ($exp['duration_minutes'] ?? 0);
            $minPax = (int) ($exp['min_pax'] ?? 1);
            $maxPax = (int) ($exp['max_pax'] ?? null);
            $price = (int) ($exp['price'] ?? 0);
            ?>

            <article class="experience-card">
                <div class="experience-card__image">
                    <?php if (!empty($exp['image_url'])): ?>
                        <img src="<?= e($exp['image_url']) ?>"
                            alt="<?= e($exp['name']) ?>"
                            class="experience-card__img"
                            loading="lazy">
                    <?php else: ?>
                        <div class="experience-card__placeholder">🎟️</div>
                    <?php endif; ?>
                </div>

                <div class="experience-card__content">
                    <h3 class="experience-card__name">
                        <?= e($exp['name']) ?>
                    </h3>

                    <?php if (!empty($exp['japanese_name'])): ?>
                        <p class="experience-card__jp">
                            <?= e($exp['japanese_name']) ?>
                        </p>
                    <?php endif; ?>

                    <p class="experience-card__description">
                        <?= e($exp['description'] ?? '') ?>
                    </p>

                    <!-- Meta información -->
                    <div class="experience-card__meta">
                        <?php if ($duration > 0): ?>
                            <span class="experience-meta__item">
                                <span class="icon">⏱️</span>
                                <?= $duration ?> minutos
                            </span>
                        <?php endif; ?>

                        <?php if ($minPax > 0): ?>
                            <span class="experience-meta__item">
                                <span class="icon">👥</span>
                                <?php if ($maxPax && $maxPax !== $minPax): ?>
                                    <?= $minPax ?>-<?= $maxPax ?> personas
                                <?php else: ?>
                                    <?php if ($maxPax): ?>
                                        Máximo <?= $maxPax ?> persona<?= $maxPax !== 1 ? 's' : '' ?>
                                    <?php else: ?>
                                        Desde <?= $minPax ?> persona<?= $minPax !== 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($animalTargets)): ?>
                            <span class="experience-meta__item">
                                <span class="icon">🐾</span>
                                <?= implode(', ', array_map('ucfirst', $animalTargets)) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Atributos especiales -->
                    <?php if (!empty($attrs)): ?>
                        <div class="experience-card__attrs">
                            <?php if (!empty($attrs['includes_drink'])): ?>
                                <span class="experience-attr">☕ Bebida incluida</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['includes_dessert'])): ?>
                                <span class="experience-attr">🍰 Postre incluido</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['includes_feed'])): ?>
                                <span class="experience-attr">🌾 Alimentar animales</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['private_room'])): ?>
                                <span class="experience-attr">🔒 Sala privada</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['quiet'])): ?>
                                <span class="experience-attr">🤫 Ambiente tranquilo</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['guided'])): ?>
                                <span class="experience-attr">👨‍🏫 Sesión guiada</span>
                            <?php endif; ?>
                            <?php if (!empty($attrs['high_energy'])): ?>
                                <span class="experience-attr">⚡ Alta energía</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="experience-card__footer">
                    <div class="experience-card__price">
                        <span class="experience-price__amount">¥<?= number_format($price) ?></span>
                        <span class="experience-price__label">/persona</span>
                    </div>
                    <a href="/reservas?cafe=<?= (int) $cafe['id'] ?>&pass=<?= (int) $exp['id'] ?>"
                        class="experience-card__cta">
                        Reservar
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
