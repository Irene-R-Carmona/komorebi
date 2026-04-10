<section class="seccion seccion--activa">
    <script src="/js/sections/detalle-cafe.js"></script>

    <?php

    use App\Core\Logger;

    $animalesPrep = array_map(static function ($a) {
        $attrs = [];
        if (!empty($a['attributes'])) {
            try {
                $attrs = json_decode($a['attributes'], true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (Exception $e) {
                Logger::warning('Error decodificando atributos de animal', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'animal_id' => $a['id'] ?? 'unknown',
                ]);
            }
        }

        return array_merge($a, $attrs);
    }, $animales);

    // Preparar datos de ratings y café
    $cafeId = (int) ($cafe['id'] ?? 0);
    $ratingAvg = (float) ($ratingStats['average'] ?? 0);
    $ratingCount = (int) ($ratingStats['count'] ?? 0);
    ?>

    <!-- Tracking de visita para Recently Viewed (external, evita inline y CSP) -->
    <div id="komorebi-page-meta" data-cafe-id="<?= $cafeId ?>" style="display:none;"></div>

    <div class="seccion__container"
        x-data="detalleCafe(<?= e(json_encode($animalesPrep, JSON_THROW_ON_ERROR)) ?>)">

        <!-- Migas -->
        <nav class="cafe-breadcrumbs">
            <a href="/cafes" class="btn-back"><span>←</span> Volver al catálogo</a>
        </nav>

        <!-- HERO -->
        <header class="cafe-hero">
            <div class="cafe-hero__bg" style="background-image: url('<?= e($cafe['image_url']) ?>');"></div>
            <div class="cafe-hero__overlay"></div>
            <div class="cafe-hero__content">
                <div class="cafe-hero__badges">
                    <span class="cafe-hero__badge"><?= ucfirst($cafe['animal_type']) ?> Café</span>
                    <?php if ($cafe['has_reservations']): ?><span
                            class="cafe-hero__badge">Reserva Online</span><?php endif; ?>
                </div>
                <h1 class="cafe-hero__titulo"><?= $cafe['name'] ?></h1>
                <p class="cafe-hero__subtitulo"><?= $cafe['japanese_name'] ?></p>
                <div class="cafe-hero__meta">
                    <div class="cafe-hero__meta-item"><span>📍</span> <?= $cafe['location'] ?></div>
                    <div class="cafe-hero__meta-item">
                        <span>⭐</span>
                        <?= number_format($ratingAvg, 1) ?> / 5.0
                        <?php if ($ratingCount > 0): ?>
                            <span class="rating-count">(<?= $ratingCount ?> reseña<?= $ratingCount !== 1 ? 's' : '' ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- INFO -->
        <div class="cafe-info">
            <div class="cafe-info__texto">
                <h3>Sobre este lugar</h3>
                <p class="cafe-info__description"><?= nl2br($cafe['description']) ?></p>

                <!-- Detalles del café -->
                <div class="cafe-details-grid">
                    <!-- Horario -->
                    <div class="detail-card">
                        <div class="detail-card__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                                <path d="M12 7V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <div class="detail-card__content">
                            <h4 class="detail-card__title">Horario</h4>
                            <p class="detail-card__text">
                                <strong><?= substr($cafe['opening_time'], 0, 5) ?></strong> -
                                <strong><?= substr($cafe['closing_time'], 0, 5) ?></strong>
                            </p>
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <div class="detail-card">
                        <div class="detail-card__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor" />
                            </svg>
                        </div>
                        <div class="detail-card__content">
                            <h4 class="detail-card__title">Ubicación</h4>
                            <p class="detail-card__text"><?= e($cafe['location']) ?></p>
                        </div>
                    </div>

                    <!-- Tipo de animal -->
                    <div class="detail-card">
                        <div class="detail-card__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="8" cy="6" r="2" fill="currentColor" />
                                <circle cx="16" cy="6" r="2" fill="currentColor" />
                                <circle cx="6" cy="11" r="1.5" fill="currentColor" />
                                <circle cx="18" cy="11" r="1.5" fill="currentColor" />
                                <path d="M12 9c-3 0-5.5 2-6 5 0 0 1 3 6 3s6-3 6-3c-.5-3-3-5-6-5z" fill="currentColor" />
                            </svg>
                        </div>
                        <div class="detail-card__content">
                            <h4 class="detail-card__title">Especialidad</h4>
                            <p class="detail-card__text"><?= ucfirst(e($cafe['animal_type'])) ?> Café</p>
                        </div>
                    </div>

                    <!-- Reserva online -->
                    <?php if ($cafe['has_reservations']): ?>
                        <div class="detail-card">
                            <div class="detail-card__icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z" fill="currentColor" />
                                    <path d="M9 14l-2 2 4 4 6-6-1.5-1.5L11 17l-2-2z" fill="currentColor" />
                                </svg>
                            </div>
                            <div class="detail-card__content">
                                <h4 class="detail-card__title">Reserva</h4>
                                <p class="detail-card__text">Disponible online</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Valoración -->
                    <div class="detail-card">
                        <div class="detail-card__icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" />
                            </svg>
                        </div>
                        <div class="detail-card__content">
                            <h4 class="detail-card__title">Valoración</h4>
                            <p class="detail-card__text">
                                <?= number_format($ratingAvg, 1) ?>/5.0
                                <?php if ($ratingCount > 0): ?>
                                    <span class="detail-card__meta">(<?= $ratingCount ?> reseñas)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Características adicionales si están disponibles -->
                <?php
                $amenities = [];
                if (!empty($cafe['has_wifi'])) {
                    $amenities[] = ['icon' => 'wifi', 'text' => 'WiFi gratuito'];
                }
                if (!empty($cafe['has_food'])) {
                    $amenities[] = ['icon' => 'food', 'text' => 'Servicio de comida'];
                }
                if (!empty($cafe['has_drinks'])) {
                    $amenities[] = ['icon' => 'drink', 'text' => 'Bebidas incluidas'];
                }
                if (!empty($cafe['wheelchair_accessible'])) {
                    $amenities[] = ['icon' => 'accessible', 'text' => 'Accesible'];
                }

                if (count($amenities) > 0):
                ?>
                    <div class="cafe-amenities">
                        <h4 class="cafe-amenities__title">Servicios y comodidades</h4>
                        <ul class="cafe-amenities__list">
                            <?php foreach ($amenities as $amenity): ?>
                                <li class="cafe-amenities__item">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" />
                                    </svg>
                                    <?= e($amenity['text']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <aside>
                <!-- Widget de Vistos Recientemente -->
                <?php include __DIR__ . '/../../components/recently-viewed-widget.php'; ?>

                <div class="cafe-info__cta-box">
                    <h3 class="cafe-info__cta-title">¿Quieres visitarnos?</h3>
                    <p class="cafe-info__cta-desc">Reserva tu espacio para interactuar.</p>
                    <?php if ($cafe['has_reservations']): ?>
                        <a href="/reservas?cafe=<?= $cafe['id'] ?>" class="btn btn--primario btn-cta-lg">Reservar
                            Ahora</a>
                    <?php else: ?>
                        <button class="btn btn--secundario btn-cta-lg" disabled>No requiere reserva</button>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <!-- RESIDENTES -->
        <div class="animales-section">
            <header class="seccion__header">
                <h2 class="seccion__titulo">Nuestros Residentes</h2>
                <p class="seccion__subtitulo">Haz clic para conocerlos</p>
            </header>

            <?php if (empty($animales)): ?>
                <div class="catalogo__vacio">
                    <p>No hay registros aún.</p>
                </div>
            <?php else: ?>
                <div class="catalogo__grid">
                    <?php foreach ($animales as $index => $animal): ?>
                        <!-- AL HACER CLICK: Pasamos el índice, no el objeto entero -->
                        <article class="animal-card" @click="abrirModal(<?= $index ?>)">
                            <div class="animal-card__avatar">
                                <img src="<?= e($animal['image_url'] ?? '') ?>" alt="<?= e($animal['name'] ?? '') ?>"
                                    class="animal-card__img" loading="lazy">
                            </div>
                            <div class="animal-card__info">
                                <span class="animal-card__nombre"><?= e($animal['name'] ?? '') ?></span>
                                <span class="animal-card__detalle"><?= e((string) ($animal['age'] ?? '')) ?> años</span>
                            </div>
                            <div class="animal-card__flecha">→</div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- EXPERIENCIAS -->
        <?php
        // Variable necesaria para experiences_section.php
        $cafeId = (int) $cafe['id'];
        include 'experiences_section.php';
        ?>

        <!-- RESEÑAS Y VALORACIONES -->
        <section id="reviews-section" class="reviews-section">
            <header class="seccion__header">
                <h2 class="seccion__titulo">Reseñas y Opiniones</h2>
                <p class="seccion__subtitulo">Lo que dicen nuestros visitantes</p>
            </header>

            <!-- Stats de Rating -->
            <div class="rating-stats">
                <div class="rating-stats__average">
                    <div class="rating-stats__number"><?= number_format($ratingAvg, 1) ?></div>
                    <div class="rating-stats__stars">
                        <?php
                        $wholePart = floor($ratingAvg);
                        for ($i = 1; $i <= 5; $i++):
                            $filled = $i <= $wholePart ? 'review-star--filled' : '';
                        ?>
                            <span class="review-star <?= $filled ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-stats__count">
                        <?= $ratingCount ?> reseña<?= $ratingCount !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <!-- Distribución de ratings (si hay reseñas) -->
                <?php if ($ratingCount > 0):
                    $distribution = $ratingStats['distribution'] ?? [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
                ?>
                    <div class="rating-distribution">
                        <?php for ($rating = 5; $rating >= 1; $rating--):
                            $count = (int) ($distribution[$rating] ?? 0);
                            $percentage = $ratingCount > 0 ? round(($count / $ratingCount) * 100) : 0;
                        ?>
                            <div class="rating-bar">
                                <span class="rating-bar__label"><?= $rating ?> ⭐</span>
                                <div class="rating-bar__container">
                                    <div class="rating-bar__fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <span class="rating-bar__count"><?= $count ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Listado de reseñas aprobadas -->
            <div class="reviews-container">
                <h3 class="reviews-container__title">Reseñas recientes</h3>
                <?php
                $page = max(1, (int) ($_GET['page'] ?? 1));
                include 'reviews_section.php';
                ?>
            </div>

            <!-- Formulario para dejar reseña -->
            <div class="review-form-container">
                <h3 class="review-form-container__title">Comparte tu experiencia</h3>
                <?php
                // Variables locales para review_form.php
                include 'review_form.php';
                ?>
            </div>
        </section>

        <!-- MODAL -->
        <div class="animal-modal"
            x-show="modalOpen"
            style="display: none;"
            x-transition.opacity.duration.300ms>

            <div class="animal-modal__overlay" @click="cerrarModal()"></div>

            <div class="animal-modal__card" @click.stop x-show="animalActivo"
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="transform scale(0.9)"
                x-transition:enter-end="transform scale(1)">
                <template x-if="animalActivo">
                    <div class="modal-layout-wrapper">
                        <button class="animal-modal__close" @click="cerrarModal()">×</button>

                        <div class="animal-modal__foto">
                            <img :src="animalActivo.image_url" class="animal-modal__img">
                        </div>

                        <div class="animal-modal__content">
                            <div class="animal-modal__header">
                                <span class="animal-modal__raza" x-text="animalActivo.species_type || 'Animal'"></span>
                                <h2 class="animal-modal__nombre" x-text="animalActivo.name"></h2>
                                <div class="animal-modal__stats">
                                    <span class="animal-modal__stat" x-text="animalActivo.age + ' años'"></span>
                                    <span class="animal-modal__stat" x-text="animalActivo.personality"></span>
                                </div>
                            </div>

                            <p class="animal-modal__bio" x-text="animalActivo.description"></p>

                            <div class="animal-modal__preferencias"
                                x-show="animalActivo.gustos || animalActivo.disgustos">
                                <div class="animal-modal__lista" x-show="animalActivo.gustos">
                                    <div class="detalle__lista-titulo" style="color: var(--color-exito);">♥ LE ENCANTA
                                    </div>
                                    <ul>
                                        <template x-for="gusto in animalActivo.gustos">
                                            <li x-text="gusto"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="animal-modal__lista" x-show="animalActivo.disgustos">
                                    <div class="detalle__lista-titulo" style="color: var(--color-error);">✕ EVITAR</div>
                                    <ul>
                                        <template x-for="disgusto in animalActivo.disgustos">
                                            <li x-text="disgusto"></li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>
