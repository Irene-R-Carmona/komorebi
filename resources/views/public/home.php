<?php

/**
 * Página principal (Home)
 *
 * Variables esperadas:
 * - $titulo: string
 * - $totalCafes: int
 * - $featuredCafes: array
 * - $userData: array|null
 * - $categories: array
 */
?>

<section class="seccion seccion--activa" style="padding: 0;">

    <!-- HERO -->
    <div class="hero">
        <?php

        use App\Core\Container;
use App\Services\ClimaContextoService;
use App\Services\MicroestacionesService;

$climaService = Container::make(ClimaContextoService::class);
$sekkiService = new MicroestacionesService();
$clima = $climaService->obtenerClimaActual();
$estacion = $sekkiService->obtenerActual();
?>

        <!-- Badges contextuales flotantes -->
        <div class="hero__badges">
            <!-- Badge Clima Tokyo -->
            <div class="badge badge--clima" title="<?= htmlspecialchars($clima['mensaje_poetico']) ?>" role="status" aria-label="Tokyo: <?= $clima['temperatura_celsius'] ?>°C, <?= htmlspecialchars($clima['descripcion']) ?>">
                <span class="badge__icon" aria-hidden="true">🌤️</span>
                <span class="badge__content">
                    <span class="badge__label" aria-hidden="true">Tokyo</span>
                    <span class="badge__value" aria-hidden="true"><?= $clima['temperatura_celsius'] ?>°C · <?= htmlspecialchars($clima['descripcion']) ?></span>
                </span>
            </div>

            <!-- Badge Estación Japonesa -->
            <div class="badge badge--estacion" title="<?= htmlspecialchars($estacion['descripcion']) ?>" aria-label="Estación: <?= htmlspecialchars($estacion['nombre_es']) ?>">
                <span class="badge__icon" aria-hidden="true"><?= $estacion['icono'] ?></span>
                <span class="badge__content">
                    <span class="badge__label" aria-hidden="true"><?= htmlspecialchars($estacion['nombre_ja']) ?></span>
                    <span class="badge__value" aria-hidden="true"><?= htmlspecialchars($estacion['nombre_es']) ?></span>
                </span>
            </div>
        </div>

        <div class="hero__content">
            <span class="hero__subtitle">木漏れ日カフェへようこそ</span>
            <h1 class="hero__title">Descubre la paz<br>entre café y animales</h1>
            <p class="hero__description">
                Sumérgete en la cultura del <em>Kissaten</em> japonés.
                Un refugio urbano donde el aroma del café se mezcla con la compañía de seres adorables.
            </p>

            <div class="hero__cta-group">
                <a href="/cafes" class="btn-hero btn-hero--primary">
                    Explorar Cafés
                </a>
                <a href="/quiz" class="btn-hero btn-hero--secondary">
                    ¿Cuál es para ti?
                </a>
            </div>
        </div>

        <div class="hero__scroll"
            data-action="scrollTo"
            data-target="comoFunciona">
            ▼
        </div>
    </div>

    <!-- CÓMO FUNCIONA -->
    <div class="como-funciona" id="comoFunciona">
        <div class="como-funciona__header">
            <h2 class="como-funciona__titulo">Tu experiencia en 3 pasos</h2>
            <p class="seccion__subtitulo">Simple, relajante y memorable</p>
        </div>

        <div class="como-funciona__pasos">
            <!-- Paso 1 -->
            <div class="paso-card">
                <span class="paso-card__numero" aria-hidden="true">01</span>
                <span class="paso-card__icono" aria-hidden="true">🔍</span>
                <h3 class="paso-card__titulo">Explora</h3>
                <p class="paso-card__texto">
                    Navega por nuestro catálogo de cafés temáticos. Desde gatos juguetones hasta búhos sabios.
                </p>
            </div>

            <!-- Paso 2 -->
            <div class="paso-card">
                <span class="paso-card__numero" aria-hidden="true">02</span>
                <span class="paso-card__icono" aria-hidden="true">📅</span>
                <h3 class="paso-card__titulo">Reserva</h3>
                <p class="paso-card__texto">
                    Elige tu fecha y hora. Nuestro sistema te garantiza tu espacio para interactuar sin agobios.
                </p>
            </div>

            <!-- Paso 3 -->
            <div class="paso-card">
                <span class="paso-card__numero" aria-hidden="true">03</span>
                <span class="paso-card__icono" aria-hidden="true">☕</span>
                <h3 class="paso-card__titulo">Disfruta</h3>
                <p class="paso-card__texto">
                    Relájate, toma un café premium y deja que la compañía animal haga el resto.
                </p>
            </div>
        </div>
    </div>

    <!-- ESTADÍSTICAS -->
    <div class="estadisticas">
        <div class="estadisticas__grid">
            <div class="stat-item">
                <span class="stat-item__valor"><?= $totalCafes ?? 0 ?></span>
                <span class="stat-item__label">Sedes Únicas</span>
            </div>
            <div class="stat-item">
                <span class="stat-item__valor"><?= $totalEspecies ?? 0 ?></span>
                <span class="stat-item__label">Especies</span>
            </div>
            <div class="stat-item">
                <span class="stat-item__valor"><?= $ratingPromedio ?? '5.0' ?></span>
                <span class="stat-item__label">Valoración</span>
            </div>
            <div class="stat-item">
                <span class="stat-item__valor">100%</span>
                <span class="stat-item__label">Felicidad</span>
            </div>
        </div>
    </div>

</section>
