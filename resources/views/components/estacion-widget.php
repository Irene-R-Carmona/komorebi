<?php

declare(strict_types=1);

/**
 * Componente: Widget de Estación (24 Sekki)
 *
 * Muestra el término solar japonés actual con información contextual.
 */

use App\Services\MicroestacionesService;

// Obtener término solar actual
$microestacionesService = new MicroestacionesService();
$sekkiActual = $microestacionesService->obtenerActual();
$mensajeContextual = $microestacionesService->obtenerMensajeContextual();
?>

<!-- Widget de Estación Japonesa -->
<div class="estacion-widget"
    data-sekki="<?= htmlspecialchars($sekkiActual['romaji']) ?>"
    style="--estacion-color: <?= htmlspecialchars($sekkiActual['color']) ?>">

    <div class="estacion-widget__contenido">
        <div class="estacion-widget__icono" role="img" aria-label="<?= htmlspecialchars($sekkiActual['nombre_es']) ?>">
            <?= $sekkiActual['icono'] ?>
        </div>

        <div class="estacion-widget__info">
            <div class="estacion-widget__titulo">
                <span class="estacion-widget__nombre-es"><?= htmlspecialchars($sekkiActual['nombre_es']) ?></span>
                <span class="estacion-widget__nombre-ja"><?= htmlspecialchars($sekkiActual['nombre_ja']) ?></span>
            </div>

            <p class="estacion-widget__descripcion">
                <?= htmlspecialchars($sekkiActual['descripcion']) ?>
            </p>

            <p class="estacion-widget__mensaje">
                <?= htmlspecialchars($mensajeContextual) ?>
            </p>
        </div>
    </div>

    <div class="estacion-widget__fecha">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        Desde el <?= date('d', strtotime('2026-' . $sekkiActual['fecha_inicio'])) ?> de <?= strftime('%B', strtotime('2026-' . $sekkiActual['fecha_inicio'])) ?>
    </div>
</div>

<!-- Estilos del widget -->
<style>
    .estacion-widget {
        background: linear-gradient(135deg,
                color-mix(in srgb, var(--estacion-color) 20%, var(--color-superficie)) 0%,
                var(--color-superficie) 100%);
        border-left: 4px solid var(--estacion-color);
        border-radius: var(--radio-md);
        padding: var(--espaciado-md);
        margin-bottom: var(--espaciado-md);
        box-shadow: var(--sombra-sm);
        transition: all var(--transicion);
    }

    .estacion-widget:hover {
        box-shadow: var(--sombra-md);
        transform: translateY(-2px);
    }

    .estacion-widget__contenido {
        display: flex;
        gap: var(--espaciado-md);
        align-items: flex-start;
    }

    .estacion-widget__icono {
        font-size: 3rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .estacion-widget__info {
        flex: 1;
    }

    .estacion-widget__titulo {
        display: flex;
        align-items: baseline;
        gap: var(--espaciado-xs);
        margin-bottom: var(--espaciado-xs);
        flex-wrap: wrap;
    }

    .estacion-widget__nombre-es {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-texto);
        font-family: var(--fuente-titulo);
    }

    .estacion-widget__nombre-ja {
        font-size: 1.125rem;
        font-weight: 500;
        color: var(--color-texto-suave);
        font-family: var(--fuente-acento);
    }

    .estacion-widget__descripcion {
        font-size: 0.875rem;
        color: var(--color-texto-suave);
        line-height: 1.5;
        margin: var(--espaciado-xs) 0;
    }

    .estacion-widget__mensaje {
        font-size: 0.9375rem;
        color: var(--color-texto);
        font-style: italic;
        line-height: 1.6;
        margin: var(--espaciado-sm) 0 0;
        padding-top: var(--espaciado-sm);
        border-top: 1px solid var(--color-borde);
    }

    .estacion-widget__fecha {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        color: var(--color-texto-suave);
        margin-top: var(--espaciado-sm);
        padding-top: var(--espaciado-sm);
        border-top: 1px solid var(--color-borde);
    }

    .estacion-widget__fecha svg {
        stroke: var(--estacion-color);
    }

    /* Responsive */
    @media (max-width: 48em) {
        .estacion-widget__contenido {
            flex-direction: column;
            text-align: center;
        }

        .estacion-widget__icono {
            font-size: 2.5rem;
        }

        .estacion-widget__titulo {
            flex-direction: column;
            gap: 0.25rem;
        }
    }

    /* Animación sutil al cargar */
    @keyframes estacion-fade-in {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .estacion-widget {
        animation: estacion-fade-in 0.6s ease-out;
    }

    /* Respetar reduced motion */
    @media (prefers-reduced-motion: reduce) {
        .estacion-widget {
            animation: none;
            transition: none;
        }

        .estacion-widget:hover {
            transform: none;
        }
    }

    /* Tema oscuro */
    [data-tema="oscuro"] .estacion-widget {
        background: linear-gradient(135deg,
                color-mix(in srgb, var(--estacion-color) 15%, var(--color-superficie)) 0%,
                var(--color-superficie) 100%);
    }
</style>
