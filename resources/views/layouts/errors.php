<?php

declare(strict_types=1);

/**
 * Layout minimalista para páginas de error.
 *
 * Objetivo:
 * - No cargar navegación/JS innecesario.
 * - Mantener consistencia visual (CSS global + errors.css).
 *
 * Variables esperadas:
 * - string|null $titulo
 * - string $content (inyectado por View::render)
 * - array $extraCss (opcional)
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $titulo ?? 'Error' ?> | Komorebi</title>

    <!-- CSS base -->
    <link href="/css/global.css" rel="stylesheet">
    <link href="/css/sections/errors.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" crossorigin="anonymous">

    <!-- CSS adicional opcional -->
    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link href="/css/sections/<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body class="error-body">
    <?= $content ?>
</body>

</html>
