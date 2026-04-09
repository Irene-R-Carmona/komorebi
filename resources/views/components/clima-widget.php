<?php

declare(strict_types=1);

/**
 * Componente: Widget de Clima Tokyo
 *
 * Muestra el clima actual en Tokyo con efectos visuales adaptativos.
 *
 * @var array $clima Datos del clima actual de Tokyo
 */

use App\Core\Container;
use App\Services\ClimaContextoService;

// Obtener datos de clima
$climaService = Container::make(ClimaContextoService::class);
$clima = $climaService->obtenerClimaActual();
$efectos = $climaService->obtenerConfiguracionEfectos($clima['condicion']);
?>

<!-- Widget de Clima Tokyo -->
<div class="clima-widget" data-clima="<?= htmlspecialchars($clima['condicion']) ?>" x-data="climaWidget">
    <!-- Canvas para efectos visuales -->
    <canvas id="efectos-clima" class="clima-widget__canvas" aria-hidden="true"></canvas>

    <!-- Información del clima -->
    <div class="clima-widget__contenido">
        <div class="clima-widget__encabezado">
            <span class="clima-widget__ubicacion">
                <svg class="clima-widget__icono-ubicacion" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                    <circle cx="12" cy="10" r="3" />
                </svg>
                Tokyo, Japón
            </span>
            <time class="clima-widget__hora" datetime="<?= htmlspecialchars($clima['hora_local_tokyo']) ?>">
                <?= htmlspecialchars($clima['hora_tokyo']) ?>
            </time>
        </div>

        <div class="clima-widget__principal">
            <div class="clima-widget__temperatura">
                <span class="clima-widget__grados"><?= $clima['temperatura_celsius'] ?></span>
                <span class="clima-widget__unidad">°C</span>
            </div>

            <div class="clima-widget__estado">
                <span class="clima-widget__condicion"><?= htmlspecialchars($clima['descripcion']) ?></span>
                <p class="clima-widget__mensaje"><?= htmlspecialchars($clima['mensaje_poetico']) ?></p>
            </div>
        </div>

        <?php if ($clima['desde_cache']): ?>
            <small class="clima-widget__cache-info">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                Actualizado hace <?= floor((time() - $clima['timestamp']) / 60) ?> min
            </small>
        <?php endif; ?>
    </div>
</div>


<!-- Script de inicialización (efectos incluidos) -->
<script src="/js/efectos-clima.js"></script>

<!-- Estilos del widget -->
<style>
    .clima-widget {
        position: relative;
        background: linear-gradient(135deg,
                var(--color-fondo-alt) 0%,
                var(--color-fondo) 100%);
        border-radius: var(--radio-lg);
        padding: var(--espaciado-md);
        box-shadow: var(--sombra-md);
        overflow: hidden;
        min-height: 180px;
    }

    .clima-widget__canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
    }

    .clima-widget__contenido {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        gap: var(--espaciado-sm);
    }

    .clima-widget__encabezado {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
        color: var(--color-texto-suave);
    }

    .clima-widget__ubicacion {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 500;
    }

    .clima-widget__icono-ubicacion {
        stroke: var(--color-primario);
    }

    .clima-widget__hora {
        font-family: var(--fuente-acento);
    }

    .clima-widget__principal {
        display: flex;
        align-items: center;
        gap: var(--espaciado-md);
    }

    .clima-widget__temperatura {
        display: flex;
        align-items: flex-start;
        line-height: 1;
    }

    .clima-widget__grados {
        font-size: 3.5rem;
        font-weight: 700;
        font-family: var(--fuente-acento);
        color: var(--color-texto);
    }

    .clima-widget__unidad {
        font-size: 1.5rem;
        font-weight: 500;
        color: var(--color-texto-suave);
        margin-top: 0.5rem;
    }

    .clima-widget__estado {
        flex: 1;
    }

    .clima-widget__condicion {
        display: block;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-texto);
        margin-bottom: 0.25rem;
    }

    .clima-widget__mensaje {
        font-size: 0.875rem;
        color: var(--color-texto-suave);
        font-style: italic;
        margin: 0;
        line-height: 1.4;
    }

    .clima-widget__cache-info {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        color: var(--color-texto-suave);
        opacity: 0.7;
    }

    /* Responsive */
    @media (max-width: 48em) {
        .clima-widget__principal {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--espaciado-sm);
        }

        .clima-widget__grados {
            font-size: 2.5rem;
        }
    }

    /* Tema oscuro */
    [data-tema="oscuro"] .clima-widget {
        background: linear-gradient(135deg,
                var(--color-superficie) 0%,
                var(--color-fondo-alt) 100%);
    }

    /* Variaciones por condición climática */
    .clima-widget[data-clima="rain"] {
        background: linear-gradient(135deg, #4682B4 0%, #5F9EA0 100%);
        color: white;
    }

    .clima-widget[data-clima="rain"] .clima-widget__grados,
    .clima-widget[data-clima="rain"] .clima-widget__condicion {
        color: white;
    }

    .clima-widget[data-clima="rain"] .clima-widget__mensaje,
    .clima-widget[data-clima="rain"] .clima-widget__hora {
        color: rgba(255, 255, 255, 0.9);
    }

    .clima-widget[data-clima="snow"] {
        background: linear-gradient(135deg, #F0F8FF 0%, #E0FFFF 100%);
    }

    .clima-widget[data-clima="fog"] {
        background: linear-gradient(135deg, #D3D3D3 0%, #C0C0C0 100%);
    }

    .clima-widget[data-clima="thunderstorm"] {
        background: linear-gradient(135deg, #2F4F4F 0%, #708090 100%);
        color: white;
    }

    .clima-widget[data-clima="thunderstorm"] .clima-widget__grados,
    .clima-widget[data-clima="thunderstorm"] .clima-widget__condicion {
        color: white;
    }

    .clima-widget[data-clima="clear"] {
        background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    }
</style>
