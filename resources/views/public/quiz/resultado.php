<!-- Resultado del Quiz "Tu Café del Alma" -->
<?php
// Mapeo animal → icono Bootstrap Icons
// Cubre las 14 especies reales del proyecto + alias en español/inglés/japonés
// IMPORTANTE: orden descendente de especificidad para que str_contains no haga falsos positivos
$animalIconMap = [
    // BÚHO / OWL / フクロウ (antes que pájaro: búho es un pájaro pero específico)
    'búho' => 'bi-moon-stars',
    'buho' => 'bi-moon-stars',
    'owl' => 'bi-moon-stars',
    'fukurou' => 'bi-moon-stars',
    'nocturno' => 'bi-moon-stars',
    // LORO / PARROT (antes que pájaro: loro es un pájaro pero específico)
    'loro' => 'bi-megaphone',
    'parrot' => 'bi-megaphone',
    'guacamayo' => 'bi-megaphone',
    'agapornis' => 'bi-megaphone',
    // PÁJARO / BIRD / 小鳥 (genérico, después de búho y loro)
    'pájaro' => 'bi-feather',
    'pajaro' => 'bi-feather',
    'bird' => 'bi-feather',
    'tori' => 'bi-feather',
    'kotori' => 'bi-feather',
    'cantor' => 'bi-feather',
    // PERRITO DE LA PRADERA (antes que perro: "perrito de la pradera" contiene "perro")
    'pradera' => 'bi-binoculars',
    'prairie' => 'bi-binoculars',
    // PERRO / DOG / 犬
    'perro' => 'bi-heart-pulse',
    'dog' => 'bi-heart-pulse',
    'inu' => 'bi-heart-pulse',
    'shiba' => 'bi-heart-pulse',
    // GATO / CAT / ネコ
    'gato' => 'bi-cat',
    'cat' => 'bi-cat',
    'neko' => 'bi-cat',
    // CONEJO / RABBIT / ウサギ
    'conejo' => 'bi-flower2',
    'rabbit' => 'bi-flower2',
    'usagi' => 'bi-flower2',
    // CHINCHILLA / チンチラ (antes que ardilla: ambas son roedores)
    'chinchilla' => 'bi-cloud-fog2',
    // ARDILLA / SQUIRREL / リス
    'ardilla' => 'bi-tree',
    'squirrel' => 'bi-tree',
    'chipmunk' => 'bi-tree',
    // CERDITO / PIG / 豚
    'cerdito' => 'bi-piggy-bank',
    'cerdo' => 'bi-piggy-bank',
    'pig' => 'bi-piggy-bank',
    // CAPIBARA / CAPYBARA / カピバラ
    'capibara' => 'bi-emoji-smile',
    'capybara' => 'bi-emoji-smile',
    // ALPACA / アルパカ
    'alpaca' => 'bi-layers',
    // CABALLO / HORSE / 馬
    'caballo' => 'bi-wind',
    'horse' => 'bi-wind',
    'pony' => 'bi-wind',
    'falabella' => 'bi-wind',
    // PATO / DUCK / アヒル
    'pato' => 'bi-water',
    'duck' => 'bi-water',
    // COBAYA / GUINEA PIG / モルモット
    'cobaya' => 'bi-heart',
    'guinea' => 'bi-heart',
    'conejillo' => 'bi-heart',
    // TORTUGA / TURTLE / カメ (antes que reptil)
    'tortuga' => 'bi-shield',
    'turtle' => 'bi-shield',
    // REPTIL / REPTILE / 爬虫類
    'reptil' => 'bi-sun',
    'reptile' => 'bi-sun',
    'iguana' => 'bi-sun',
    'lagarto' => 'bi-sun',
    // ERIZO / HEDGEHOG
    'erizo' => 'bi-shield-shaded',
    'hedgehog' => 'bi-shield-shaded',
    // HAMSTER / ハムスター
    'hamster' => 'bi-circle-square',
    // ZORRO / FOX / キツネ
    'zorro' => 'bi-lightning',
    'fox' => 'bi-lightning',
    'kitsune' => 'bi-lightning',
    // TANUKI / MAPACHE / RACCOON
    'tanuki' => 'bi-cloud',
    'raccoon' => 'bi-cloud',
    'mapache' => 'bi-cloud',
];

$animalGuiaLower = mb_strtolower($cafe['animal_guia'] ?? '');
$animalIcon = 'bi-stars';
foreach ($animalIconMap as $keyword => $icon) {
    if (str_contains($animalGuiaLower, $keyword)) {
        $animalIcon = $icon;
        break;
    }
}

// Clase de color temática basada en animal
$animalColorMap = [
    // 4 perfiles activos del quiz
    'bi-cat' => 'tema-neko',
    'bi-heart-pulse' => 'tema-inu',
    'bi-flower2' => 'tema-usagi',
    'bi-feather' => 'tema-tori',
    // Especies adicionales del proyecto
    'bi-moon-stars' => 'tema-fukuro',
    'bi-megaphone' => 'tema-tori',       // loro → tori (ave)
    'bi-shield-shaded' => 'tema-hari',        // erizo
    'bi-emoji-smile' => 'tema-kapiwa',      // capibara
    'bi-lightning' => 'tema-kitsune',     // zorro
    'bi-cloud' => 'tema-fukuro',      // tanuki
    'bi-circle-square' => 'tema-neko',        // hamster
    'bi-cloud-fog2' => 'tema-usagi',       // chinchilla (suave/nocturna)
    'bi-tree' => 'tema-tori',        // ardilla (naturaleza)
    'bi-piggy-bank' => 'tema-kapiwa',      // cerdito (alegre)
    'bi-layers' => 'tema-usagi',       // alpaca (suave)
    'bi-wind' => 'tema-kitsune',     // caballo (veloz)
    'bi-water' => 'tema-fukuro',      // pato (sereno)
    'bi-heart' => 'tema-usagi',       // cobaya (adorable)
    'bi-binoculars' => 'tema-inu',         // perrito de la pradera (activo)
    'bi-shield' => 'tema-hari',        // tortuga (protectora)
    'bi-sun' => 'tema-kitsune',     // reptil (astuto, amante del sol)
];
$temaCss = $animalColorMap[$animalIcon] ?? 'tema-default';
?>
<div class="quiz-resultado" x-data="{ mostrarDetalles: false }">

    <!-- Hero del resultado -->
    <div class="quiz-resultado__hero quiz-resultado__hero--<?= $temaCss ?>">
        <div class="quiz-resultado__kanji" aria-hidden="true">結果</div>
        <div class="quiz-resultado__contenido">
            <p class="quiz-resultado__etiqueta">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                Tu Café del Alma
            </p>
            <h1 class="quiz-resultado__titulo"><?= htmlspecialchars($cafe['nombre']) ?></h1>
            <p class="quiz-resultado__subtitulo"><?= htmlspecialchars($cafe['personalidad_guia']) ?></p>
        </div>
    </div>

    <!-- Animal guía -->
    <div class="animal-guia animal-guia--<?= $temaCss ?>">
        <div class="animal-guia__icono-wrap" role="img" aria-label="Animal guía: <?= htmlspecialchars($cafe['animal_guia']) ?>">
            <i class="bi <?= $animalIcon ?> animal-guia__icono" aria-hidden="true"></i>
        </div>
        <div class="animal-guia__info">
            <p class="animal-guia__titulo">Tu animal guía</p>
            <h2 class="animal-guia__nombre"><?= htmlspecialchars($cafe['animal_guia']) ?></h2>
            <p class="animal-guia__personalidad"><?= htmlspecialchars($cafe['personalidad_guia']) ?></p>
        </div>
    </div>

    <!-- Descripción profunda -->
    <div class="quiz-descripcion">
        <h2 class="quiz-descripcion__titulo">
            <i class="bi bi-chat-quote" aria-hidden="true"></i>
            ¿Por qué este café?
        </h2>
        <p class="quiz-descripcion__texto">
            <?= htmlspecialchars($cafe['descripcion']) ?>
        </p>
    </div>

    <!-- Características -->
    <div class="quiz-caracteristicas">
        <h3 class="quiz-caracteristicas__titulo">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
            Tu perfil energético
        </h3>
        <div class="caracteristicas-grid">
            <?php foreach ($puntuaciones as $caracteristica => $valor): ?>
                <?php if ($valor > 0): ?>
                    <?php $porcentaje = min(($valor / 15) * 100, 100); ?>
                    <div class="caracteristica-item">
                        <span class="caracteristica-item__nombre"><?= ucfirst($caracteristica) ?></span>
                        <div class="caracteristica-item__barra">
                            <div class="caracteristica-item__relleno caracteristica-item__relleno--<?= $porcentaje >= 70 ? 'alto' : ($porcentaje >= 40 ? 'medio' : 'bajo') ?>"
                                style="width: <?= $porcentaje ?>%"
                                role="progressbar"
                                aria-valuenow="<?= $valor ?>"
                                aria-valuemin="0"
                                aria-valuemax="15"
                                aria-label="<?= ucfirst($caracteristica) ?>: <?= $valor ?>/15">
                            </div>
                        </div>
                        <span class="caracteristica-item__valor"><?= $valor ?><span class="caracteristica-item__max">/15</span></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Café real (si existe) -->
    <?php if ($cafeData): ?>
        <div class="cafe-destino">
            <h2 class="cafe-destino__titulo">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                Visita tu refugio
            </h2>

            <div class="cafe-destino__card">
                <?php if (!empty($cafeData['image_url'])): ?>
                    <img src="<?= htmlspecialchars($cafeData['image_url']) ?>"
                        alt="<?= htmlspecialchars($cafeData['name']) ?>"
                        class="cafe-destino__imagen"
                        x-data
                        x-on:error="$el.src='/images/ui/placeholder.svg'">
                <?php endif; ?>

                <div class="cafe-destino__info">
                    <h3 class="cafe-destino__nombre"><?= htmlspecialchars($cafeData['name']) ?></h3>
                    <p class="cafe-destino__descripcion"><?= htmlspecialchars($cafeData['description']) ?></p>

                    <div class="cafe-destino__meta">
                        <span class="cafe-destino__meta-item">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <?= htmlspecialchars($cafeData['location'] ?? 'Tokyo, Japón') ?>
                        </span>
                        <?php if (!empty($cafeData['rating_avg'])): ?>
                            <span class="cafe-destino__meta-item cafe-destino__meta-item--rating">
                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                                <span class="visually-hidden">Valoración:</span>
                                <?= number_format((float) $cafeData['rating_avg'], 1) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="cafe-destino__acciones">
                <a href="/cafes/<?= htmlspecialchars($cafeData['slug']) ?>" class="btn-komorebi btn-komorebi-primary">
                    <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                    Ver el café
                </a>
                <a href="/reservas" class="btn-komorebi btn-komorebi-secondary">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                    Reservar experiencia
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="cafe-destino cafe-destino--error" role="alert">
            <p class="cafe-destino__mensaje-error">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                No hemos podido cargar tu café perfecto en este momento.
                <a href="/quiz" class="link-inline">Vuelve a intentarlo</a>.
            </p>
        </div>
    <?php endif; ?>

    <!-- Acciones finales -->
    <div class="quiz-acciones">
        <a href="/quiz" class="btn-komorebi btn-komorebi-ghost">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            Repetir quiz
        </a>
        <a href="/cafes" class="btn-komorebi btn-komorebi-ghost">
            <i class="bi bi-search" aria-hidden="true"></i>
            Otros refugios
        </a>
        <button class="btn-komorebi btn-komorebi-acento" @click="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i>
            Guardar PDF
        </button>
    </div>

    <!-- Compartir -->
    <div class="quiz-compartir">
        <p class="quiz-compartir__titulo">
            <i class="bi bi-share" aria-hidden="true"></i>
            Comparte tu resultado
        </p>
        <div class="compartir-botones">
            <button class="compartir-btn compartir-btn--twitter"
                @click="window.open('<?= 'https://twitter.com/intent/tweet?text=' . urlencode('Mi café del alma es ' . $cafe['nombre'] . ' en Komorebi Café') ?>', '_blank', 'width=550,height=420')">
                <i class="bi bi-twitter-x" aria-hidden="true"></i> Twitter
            </button>
            <button class="compartir-btn compartir-btn--facebook"
                @click="window.open('<?= 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode('https://' . ($_SERVER['HTTP_HOST'] ?? 'komorebi.cafe') . '/quiz') ?>', '_blank', 'width=550,height=420')">
                <i class="bi bi-facebook" aria-hidden="true"></i> Facebook
            </button>
            <button class="compartir-btn compartir-btn--copiar"
                @click="navigator.clipboard.writeText('Mi café del alma es <?= htmlspecialchars($cafe['nombre'], ENT_QUOTES) ?> - Descubre el tuyo en Komorebi Café').then(() => window.dispatchEvent(new CustomEvent('toast', {detail:{message:'¡Enlace copiado!',type:'success'}})))">
                <i class="bi bi-clipboard-check" aria-hidden="true"></i> Copiar
            </button>
        </div>
    </div>
</div>
