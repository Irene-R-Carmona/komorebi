<!-- Resultado del Quiz "Tu Café del Alma" -->
<div class="quiz-resultado" x-data="{ mostrarDetalles: false }">

    <!-- Hero del resultado -->
    <div class="quiz-resultado__hero">
        <div class="quiz-resultado__particulas" aria-hidden="true">
            ✨🌸🍃☕
        </div>

        <div class="quiz-resultado__contenido">
            <p class="quiz-resultado__etiqueta">Tu Café del Alma es...</p>
            <h1 class="quiz-resultado__titulo"><?= htmlspecialchars($cafe['nombre']) ?></h1>
            <p class="quiz-resultado__subtitulo"><?= htmlspecialchars($cafe['personalidad_guia']) ?></p>
        </div>
    </div>

    <!-- Animal guía -->
    <div class="animal-guia">
        <div class="animal-guia__icono" role="img" aria-label="<?= htmlspecialchars($cafe['animal_guia']) ?>">
            <?php
            $animales = [
                'Gato Guardián' => '🐱',
                'Conejo de Luna' => '🐰',
                'Búho Nocturno' => '🦉',
                'Pájaro Cantor' => '🐦',
            ];
            echo $animales[$cafe['animal_guia']] ?? '🦊';
            ?>
        </div>
        <div class="animal-guia__info">
            <h2 class="animal-guia__titulo">Tu animal guía</h2>
            <p class="animal-guia__nombre"><?= htmlspecialchars($cafe['animal_guia']) ?></p>
            <p class="animal-guia__personalidad"><?= htmlspecialchars($cafe['personalidad_guia']) ?></p>
        </div>
    </div>

    <!-- Descripción profunda -->
    <div class="quiz-descripcion">
        <h2 class="quiz-descripcion__titulo">¿Por qué este café?</h2>
        <p class="quiz-descripcion__texto">
            <?= htmlspecialchars($cafe['descripcion']) ?>
        </p>
    </div>

    <!-- Características -->
    <div class="quiz-caracteristicas">
        <h3 class="quiz-caracteristicas__titulo">Tu perfil energético</h3>
        <div class="caracteristicas-grid">
            <?php foreach ($puntuaciones as $caracteristica => $valor): ?>
                <?php if ($valor > 0): ?>
                    <div class="caracteristica-item">
                        <span class="caracteristica-item__nombre"><?= ucfirst($caracteristica) ?></span>
                        <div class="caracteristica-item__barra">
                            <div class="caracteristica-item__relleno"
                                style="width: <?= min(($valor / 15) * 100, 100) ?>%"
                                role="progressbar"
                                aria-valuenow="<?= $valor ?>"
                                aria-valuemin="0"
                                aria-valuemax="15">
                            </div>
                        </div>
                        <span class="caracteristica-item__valor"><?= $valor ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Café real (si existe) -->
    <?php if ($cafeData): ?>
        <div class="cafe-destino">
            <h2 class="cafe-destino__titulo">Visita tu refugio</h2>

            <div class="cafe-destino__card">
                <?php if (!empty($cafeData['image_url'])): ?>
                    <img src="<?= htmlspecialchars($cafeData['image_url']) ?>"
                        alt="<?= htmlspecialchars($cafeData['name']) ?>"
                        class="cafe-destino__imagen"
                        onerror="this.onerror=null; this.src='/images/ui/placeholder.svg'">
                <?php endif; ?>

                <div class="cafe-destino__info">
                    <h3><?= htmlspecialchars($cafeData['name']) ?></h3>
                    <p><?= htmlspecialchars($cafeData['description']) ?></p>

                    <div class="cafe-destino__meta">
                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($cafeData['location'] ?? 'Tokyo, Japón') ?></span>
                        <span><i class="bi bi-star-fill" aria-hidden="true" style="color:var(--color-acento)"></i> <span class="visually-hidden">Valoración:</span><?= number_format($cafeData['rating_avg'] ?? 4.5, 1) ?></span>
                    </div>
                </div>
            </div>

            <div class="cafe-destino__acciones">
                <a href="/cafes/<?= htmlspecialchars($cafe['slug']) ?>" class="btn-komorebi btn-komorebi-primary">
                    Ver el café
                </a>
                <a href="/reservas" class="btn-komorebi btn-komorebi-secondary">
                    Reservar experiencia
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones finales -->
    <div class="quiz-acciones">
        <a href="/quiz" class="btn-komorebi btn-komorebi-ghost">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Repetir quiz
        </a>
        <a href="/cafes" class="btn-komorebi btn-komorebi-ghost">
            <i class="bi bi-search" aria-hidden="true"></i> Descubrir otros refugios
        </a>
        <button class="btn-komorebi btn-komorebi-ghost" @click="window.print()">
            <i class="bi bi-file-earmark" aria-hidden="true"></i> Guardar resultado
        </button>
    </div>

    <!-- Compartir (opcional) -->
    <div class="quiz-compartir">
        <p>Comparte tu resultado:</p>
        <div class="compartir-botones">
            <button class="compartir-btn compartir-btn--twitter"
                data-action="openWindow"
                data-url="<?= 'https://twitter.com/intent/tweet?text=' . urlencode('Mi café del alma es ' . $cafe['nombre'] . ' en Komorebi Café') ?>"
                data-features="width=550,height=420">
                <i class="bi bi-twitter-x" aria-hidden="true"></i> Twitter
            </button>
            <button class="compartir-btn compartir-btn--facebook"
                data-action="openWindow"
                data-url="<?= 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode('https://' . $_SERVER['HTTP_HOST'] . '/quiz') ?>"
                data-features="width=550,height=420">
                <i class="bi bi-facebook" aria-hidden="true"></i> Facebook
            </button>
            <button class="compartir-btn compartir-btn--copiar"
                @click="navigator.clipboard.writeText('Mi café del alma es <?= htmlspecialchars($cafe['nombre']) ?> - Descubre el tuyo en Komorebi Café'); window.dispatchEvent(new CustomEvent('toast', {detail:{message:'¡Enlace copiado!',type:'success'}}))">
                <i class="bi bi-clipboard" aria-hidden="true"></i> Copiar
            </button>
        </div>
    </div>
</div>
