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
            <p class="quiz-resultado__subtitulo"><?= htmlspecialchars($cafe['nombre']) ?></p>
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
                <?php if (!empty($cafeData['imagen'])): ?>
                    <img src="<?= htmlspecialchars($cafeData['imagen']) ?>"
                        alt="<?= htmlspecialchars($cafeData['nombre']) ?>"
                        class="cafe-destino__imagen">
                <?php endif; ?>

                <div class="cafe-destino__info">
                    <h3><?= htmlspecialchars($cafeData['nombre']) ?></h3>
                    <p><?= htmlspecialchars($cafeData['descripcion']) ?></p>

                    <div class="cafe-destino__meta">
                        <span>📍 <?= htmlspecialchars($cafeData['ubicacion'] ?? 'Tokyo, Japón') ?></span>
                        <span>⭐ <?= number_format($cafeData['rating'] ?? 4.5, 1) ?></span>
                    </div>
                </div>
            </div>

            <div class="cafe-destino__acciones">
                <a href="/cafes/<?= htmlspecialchars($cafe['slug']) ?>" class="btn btn--primary">
                    Ver el café
                </a>
                <a href="/reservar" class="btn btn--secondary">
                    Reservar experiencia
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones finales -->
    <div class="quiz-acciones">
        <a href="/quiz" class="btn btn--outline">
            🔄 Repetir quiz
        </a>
        <a href="/cafes" class="btn btn--outline">
            🔍 Descubrir otros refugios
        </a>
        <button class="btn btn--outline" @click="window.print()">
            📄 Guardar resultado
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
                🐦 Twitter
            </button>
            <button class="compartir-btn compartir-btn--facebook"
                data-action="openWindow"
                data-url="<?= 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode('https://' . $_SERVER['HTTP_HOST'] . '/quiz') ?>"
                data-features="width=550,height=420">
                📘 Facebook
            </button>
            <button class="compartir-btn compartir-btn--copiar"
                @click="navigator.clipboard.writeText('Mi café del alma es <?= htmlspecialchars($cafe['nombre']) ?> - Descubre el tuyo en Komorebi Café'); alert('¡Copiado!')">
                📋 Copiar
            </button>
        </div>
    </div>
</div>

<!-- Estilos específicos del resultado -->
<style>
    .quiz-resultado {
        max-width: 800px;
        margin: 0 auto;
        padding: var(--espaciado-lg);
    }

    .quiz-resultado__hero {
        position: relative;
        text-align: center;
        padding: var(--espaciado-xl) var(--espaciado-md);
        background: linear-gradient(135deg, var(--color-primario) 0%, var(--color-acento) 100%);
        color: white;
        border-radius: var(--radio-lg);
        margin-bottom: var(--espaciado-lg);
        overflow: hidden;
    }

    .quiz-resultado__particulas {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        font-size: 2rem;
        opacity: 0.2;
        animation: float 20s infinite linear;
        pointer-events: none;
    }

    @keyframes float {
        from {
            transform: translateY(0);
        }

        to {
            transform: translateY(-100%);
        }
    }

    .quiz-resultado__etiqueta {
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: var(--espaciado-xs);
        opacity: 0.9;
    }

    .quiz-resultado__titulo {
        font-size: 2.5rem;
        font-family: var(--fuente-titulo);
        margin: var(--espaciado-sm) 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .quiz-resultado__subtitulo {
        font-size: 1.25rem;
        font-family: var(--fuente-acento);
        opacity: 0.95;
    }

    .animal-guia {
        display: flex;
        align-items: center;
        gap: var(--espaciado-md);
        background: var(--color-superficie);
        padding: var(--espaciado-md);
        border-radius: var(--radio-md);
        margin-bottom: var(--espaciado-lg);
        box-shadow: var(--sombra-md);
    }

    .animal-guia__icono {
        font-size: 4rem;
        flex-shrink: 0;
    }

    .animal-guia__titulo {
        font-size: 0.875rem;
        text-transform: uppercase;
        color: var(--color-texto-suave);
        margin-bottom: var(--espaciado-xs);
    }

    .animal-guia__nombre {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: var(--espaciado-xs);
    }

    .animal-guia__personalidad {
        font-style: italic;
        color: var(--color-texto-suave);
    }

    .quiz-descripcion {
        background: var(--color-fondo-alt);
        padding: var(--espaciado-lg);
        border-left: 4px solid var(--color-acento);
        border-radius: var(--radio-md);
        margin-bottom: var(--espaciado-lg);
    }

    .quiz-descripcion__titulo {
        font-family: var(--fuente-titulo);
        margin-bottom: var(--espaciado-md);
    }

    .quiz-descripcion__texto {
        font-size: 1.125rem;
        line-height: 1.7;
        color: var(--color-texto-suave);
    }

    .quiz-caracteristicas {
        margin-bottom: var(--espaciado-lg);
    }

    .caracteristicas-grid {
        display: grid;
        gap: var(--espaciado-sm);
    }

    .caracteristica-item {
        display: grid;
        grid-template-columns: 100px 1fr 40px;
        align-items: center;
        gap: var(--espaciado-sm);
    }

    .caracteristica-item__barra {
        height: 8px;
        background: var(--color-fondo-alt);
        border-radius: var(--radio-full);
        overflow: hidden;
    }

    .caracteristica-item__relleno {
        height: 100%;
        background: linear-gradient(90deg, var(--color-acento) 0%, var(--color-primario) 100%);
        transition: width 1s ease;
    }

    .cafe-destino {
        background: var(--color-superficie);
        padding: var(--espaciado-lg);
        border-radius: var(--radio-lg);
        margin-bottom: var(--espaciado-lg);
        box-shadow: var(--sombra-md);
    }

    .cafe-destino__card {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: var(--espaciado-md);
        margin: var(--espaciado-md) 0;
    }

    .cafe-destino__imagen {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: var(--radio-md);
    }

    .cafe-destino__acciones {
        display: flex;
        gap: var(--espaciado-sm);
        flex-wrap: wrap;
    }

    .quiz-acciones {
        display: flex;
        gap: var(--espaciado-sm);
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: var(--espaciado-lg);
    }

    .quiz-compartir {
        text-align: center;
        padding-top: var(--espaciado-lg);
        border-top: 1px solid var(--color-borde);
    }

    .compartir-botones {
        display: flex;
        gap: var(--espaciado-sm);
        justify-content: center;
        margin-top: var(--espaciado-md);
        flex-wrap: wrap;
    }

    .compartir-btn {
        padding: var(--espaciado-sm) var(--espaciado-md);
        border: none;
        border-radius: var(--radio-md);
        cursor: pointer;
        transition: transform var(--transicion);
    }

    .compartir-btn:hover {
        transform: translateY(-2px);
    }

    @media (max-width: 48em) {
        .quiz-resultado__titulo {
            font-size: 2rem;
        }

        .animal-guia {
            flex-direction: column;
            text-align: center;
        }

        .cafe-destino__card {
            grid-template-columns: 1fr;
        }
    }

    @media print {

        .quiz-acciones,
        .quiz-compartir {
            display: none;
        }
    }
</style>
