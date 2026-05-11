<?php

declare(strict_types=1);

/**
 * Layout minimalista para páginas de error e intersticial de redirección.
 *
 * Objetivo:
 * - No cargar navegación/JS innecesario.
 * - Mantener consistencia visual con design-tokens.css + global.css + errors.css.
 *
 * Variables esperadas:
 * - string|null    $titulo     Título de la página (escapado aquí)
 * - Raw            $content    Contenido inyectado por View::render
 * - array          $extraCss   CSS adicionales (nombres de archivo en /css/sections/)
 * - Raw|null       $extraHead  HTML extra en <head> (meta refresh, etc.)
 */

$titulo ??= null;
$extraCss ??= [];
$extraHead ??= null;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Error', ENT_QUOTES, 'UTF-8') ?> | Komorebi</title>
    <link rel="icon" type="image/svg+xml" href="/images/logos/komorebi-logo-icon.svg">
    <link rel="alternate icon" href="/favicon.ico">

    <!-- Google Fonts: font-display=swap para evitar FOIT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@400;700&family=Zen+Maru+Gothic:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Design tokens ANTES de global.css y errors.css -->
    <link href="/css/design-tokens.css" rel="stylesheet">
    <link href="/css/global.css" rel="stylesheet">
    <link href="/css/sections/errors.css" rel="stylesheet">

    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet" crossorigin="anonymous">

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link href="/css/sections/<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Head extra opcional: meta refresh u otros (pasado como Raw desde controller) -->
    <?php if ($extraHead !== null): ?>
        <?= $extraHead ?>
    <?php endif; ?>
</head>

<body class="error-body">
    <div class="error-layout">

        <header class="error-header" role="banner">
            <a href="/" class="error-logo" aria-label="Komorebi Café — Volver al inicio">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36"
                    viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                    <circle cx="32" cy="32" r="30" fill="var(--color-primary-500, #5C3D2E)" />
                    <text x="32" y="42" text-anchor="middle"
                        font-family="serif" font-size="28"
                        fill="var(--color-primary-50, #faf7f4)">光</text>
                </svg>
                <span class="error-logo__name">Komorebi</span>
            </a>
        </header>

        <main class="error-main" id="main-content">
            <?= $content ?>
        </main>

        <footer class="error-footer" role="contentinfo">
            <p class="error-footer__tagline"
                lang="ja"
                aria-label="Donde la luz descansa, en japonés">光が安らう場所</p>
            <p class="error-footer__brand">
                Komorebi Café · <span lang="es">Donde la luz descansa</span>
            </p>
        </footer>

    </div>
</body>

</html>
