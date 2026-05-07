<?php

declare(strict_types=1);

/**
 * Componente: Información Contextual para Reservas
 *
 * Muestra clima actual en Tokyo y festivos japoneses relevantes
 * para ayudar al usuario a elegir mejores fechas de reserva.
 *
 * @var array $clima Datos del clima actual
 * @var array $festivos Lista de festivos del año
 */

$festivosProximos = array_filter($festivos ?? [], function ($festivo) {
    $fechaFestivo = new DateTime($festivo['fecha']);
    $hoy = new DateTime();
    $diferencia = $hoy->diff($fechaFestivo)->days;

    // Mostrar solo festivos futuros dentro de los próximos 60 días
    return $fechaFestivo >= $hoy && $diferencia <= 60;
});

// Limitar a primeros 5
$festivosProximos = array_slice($festivosProximos, 0, 5);
?>

<!-- Información Contextual para Reservas -->
<div class="reserva-contexto">
    <!-- Clima en Tokyo -->
    <div class="contexto-card contexto-card--clima">
        <div class="contexto-card__header">
            <span class="contexto-card__icono"><i class="bi bi-cloud-sun" aria-hidden="true"></i></span>
            <div>
                <h3 class="contexto-card__titulo">Clima en Tokyo</h3>
                <p class="contexto-card__subtitulo">Ahora mismo</p>
            </div>
        </div>

        <div class="contexto-card__contenido">
            <div class="clima-info">
                <div class="clima-info__principal">
                    <span class="clima-info__temperatura"><?= $clima['temperatura_celsius'] ?>°C</span>
                    <span class="clima-info__condicion"><?= htmlspecialchars($clima['descripcion']) ?></span>
                </div>
                <p class="clima-info__mensaje">
                    <?= htmlspecialchars($clima['mensaje_poetico']) ?>
                </p>
                <small class="clima-info__tiempo">
                    Hora local: <?= htmlspecialchars($clima['hora_tokyo']) ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Festivos Japoneses Próximos -->
    <?php if (!empty($festivosProximos)): ?>
        <div class="contexto-card contexto-card--festivos">
            <div class="contexto-card__header">
                <span class="contexto-card__icono"><i class="bi bi-flag" aria-hidden="true"></i></span>
                <div>
                    <h3 class="contexto-card__titulo">Festivos Japoneses</h3>
                    <p class="contexto-card__subtitulo">Próximos 60 días</p>
                </div>
            </div>

            <div class="contexto-card__contenido">
                <div class="festivos-lista">
                    <?php foreach ($festivosProximos as $festivo): ?>
                        <?php
                        $fecha = new DateTime($festivo['fecha']);
                        $permisoClass = $festivo['permite_reservas'] ? 'permite' : 'restringido';
                        ?>
                        <div class="festivo-item festivo-item--<?= $permisoClass ?>">
                            <span class="festivo-item__icono"><?= $festivo['icono'] ?></span>
                            <div class="festivo-item__info">
                                <div class="festivo-item__nombre">
                                    <?= htmlspecialchars($festivo['nombre_es']) ?>
                                    <span class="festivo-item__japones"><?= htmlspecialchars($festivo['nombre_ja']) ?></span>
                                </div>
                                <div class="festivo-item__fecha">
                                    <?= $fecha->format('d/m/Y') ?> (<?= $fecha->format('l') ?>)
                                </div>
                                <?php if (!$festivo['permite_reservas']): ?>
                                    <div class="festivo-item__nota">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        Reservas cerradas este día
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="contexto-card__footer">
                    <small>
                        <i class="bi bi-lightbulb" aria-hidden="true"></i> Los festivos nacionales pueden afectar disponibilidad y horarios
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .reserva-contexto {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .contexto-card {
        background: var(--color-superficie);
        border-radius: var(--radio-md);
        box-shadow: var(--sombra-sm);
        overflow: hidden;
        border-left: 4px solid var(--color-acento);
    }

    .contexto-card--clima {
        border-left-color: #4A90E2;
    }

    .contexto-card--festivos {
        border-left-color: #D0021B;
    }

    .contexto-card__header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        background: var(--color-fondo-alt);
        border-bottom: 1px solid var(--color-borde);
    }

    .contexto-card__icono {
        font-size: 1.75rem;
        line-height: 1;
    }

    .contexto-card__titulo {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-primario);
        margin: 0;
    }

    .contexto-card__subtitulo {
        font-size: 0.75rem;
        color: var(--color-texto-suave);
        margin: 0.125rem 0 0;
    }

    .contexto-card__contenido {
        padding: 1.25rem;
    }

    /* Clima Info */
    .clima-info__principal {
        display: flex;
        align-items: baseline;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .clima-info__temperatura {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--color-primario);
        font-variant-numeric: tabular-nums;
    }

    .clima-info__condicion {
        font-size: 1.125rem;
        color: var(--color-texto);
    }

    .clima-info__mensaje {
        font-size: 0.9375rem;
        color: var(--color-texto-suave);
        line-height: 1.6;
        margin-bottom: 0.75rem;
        font-style: italic;
    }

    .clima-info__tiempo {
        display: block;
        font-size: 0.75rem;
        color: var(--color-texto-suave);
    }

    /* Festivos Lista */
    .festivos-lista {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .festivo-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--color-fondo);
        border-radius: var(--radio-sm);
        border: 1px solid var(--color-borde);
    }

    .festivo-item--restringido {
        background: rgba(208, 2, 27, 0.05);
        border-color: rgba(208, 2, 27, 0.2);
    }

    .festivo-item__icono {
        font-size: 1.5rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .festivo-item__info {
        flex: 1;
    }

    .festivo-item__nombre {
        font-weight: 600;
        color: var(--color-primario);
        margin-bottom: 0.25rem;
    }

    .festivo-item__japones {
        font-weight: 400;
        color: var(--color-texto-suave);
        margin-left: 0.5rem;
        font-size: 0.875rem;
    }

    .festivo-item__fecha {
        font-size: 0.875rem;
        color: var(--color-texto);
        margin-bottom: 0.25rem;
    }

    .festivo-item__nota {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        color: #D0021B;
        margin-top: 0.5rem;
    }

    .festivo-item__nota svg {
        flex-shrink: 0;
    }

    .contexto-card__footer {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--color-borde);
    }

    .contexto-card__footer small {
        font-size: 0.75rem;
        color: var(--color-texto-suave);
        display: block;
        line-height: 1.4;
    }

    @media (max-width: 48em) {
        .reserva-contexto {
            grid-template-columns: 1fr;
        }

        .clima-info__principal {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }

        .clima-info__temperatura {
            font-size: 2rem;
        }
    }
</style>
