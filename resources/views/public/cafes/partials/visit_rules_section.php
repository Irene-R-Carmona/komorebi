<?php

declare(strict_types=1);

/** @var array $cafe */
?>
<section class="visit-rules" aria-labelledby="visit-rules-heading">
    <h2 id="visit-rules-heading" class="visit-rules__title">
        <span aria-hidden="true">📋</span> Antes de tu visita
    </h2>

    <ul class="visit-rules__list">
        <?php if (!empty($cafe['min_age_years'])): ?>
            <li class="visit-rules__item">
                <span class="visit-rules__icon" aria-hidden="true">🎂</span>
                <span>Edad mínima recomendada: <strong><?= e((string) $cafe['min_age_years']) ?> años</strong></span>
            </li>
        <?php endif; ?>

        <li class="visit-rules__item">
            <span class="visit-rules__icon" aria-hidden="true">🚫</span>
            <span>No se permite traer comida ni bebida del exterior</span>
        </li>

        <li class="visit-rules__item">
            <span class="visit-rules__icon" aria-hidden="true">📅</span>
            <span>Visita con <strong>reserva previa obligatoria</strong></span>
        </li>

        <li class="visit-rules__item">
            <span class="visit-rules__icon" aria-hidden="true">🐾</span>
            <span>Trata a los animales con calma y respeto — no los despiertes ni los persigas</span>
        </li>

        <li class="visit-rules__item">
            <span class="visit-rules__icon" aria-hidden="true">📸</span>
            <span>Está permitido fotografiar, pero <strong>sin flash</strong></span>
        </li>
    </ul>
</section>
