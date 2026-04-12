<?php

declare(strict_types=1);

/**
 * Vista: FAQ - Preguntas Frecuentes (flat visible, estilo Stripe/Vercel)
 *
 * @var array $datos
 */

$hero = $datos['hero'] ?? [];
$categorias = $datos['categorias'] ?? [];
?>

<div class="static-page static-page--narrow">
    <!-- Hero -->
    <header class="static-hero">
        <span class="static-hero__icon" aria-hidden="true"><i class="bi bi-question-circle"></i></span>
        <h1 class="static-hero__title"><?= htmlspecialchars($hero['titulo'] ?? 'Preguntas Frecuentes', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="static-hero__subtitle"><?= htmlspecialchars($hero['subtitulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <!-- FAQ Flat (sin acordeones) -->
    <div class="static-faq">
        <?php foreach ($categorias as $categoria): ?>
            <section class="static-faq__section">
                <h2 class="static-faq__section-title"><?= htmlspecialchars($categoria['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>

                <?php foreach ($categoria['preguntas'] as $item): ?>
                    <details class="static-faq__item">
                        <summary class="static-faq__question">
                            <?= htmlspecialchars($item['pregunta'], ENT_QUOTES, 'UTF-8') ?>
                        </summary>
                        <div class="static-faq__answer">
                            <?php if (is_array($item['respuesta'])): ?>
                                <?php foreach ($item['respuesta'] as $parrafo): ?>
                                    <p><?= htmlspecialchars($parrafo, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?= htmlspecialchars($item['respuesta'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- CTA -->
    <div class="static-cta">
        <h3 class="static-cta__title">¿No encuentras lo que buscas?</h3>
        <p class="static-cta__text">Contacta con nosotros y te ayudaremos personalmente.</p>
        <a href="/contacto" class="btn">Contactar</a>
    </div>
</div>
