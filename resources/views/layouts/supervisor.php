<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;

// CSP Nonce para scripts inline
$cspNonce = $GLOBALS['cspNonce'] ?? '';

$cafeName = $_SESSION['user_cafe_name'] ?? 'Café';
$userName = $_SESSION['user_name'] ?? 'Supervisor';
$content ??= '';
$extraCss ??= [];
$assetVersion = Env::get('APP_VERSION', '1');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <title>Supervisor | <?= e($cafeName) ?></title>
    <link rel="icon" type="image/svg+xml" href="/images/logos/komorebi-logo-icon.svg">
    <link rel="alternate icon" href="/favicon.ico">

    <!-- Tipografía -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link rel="preload"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        as="style" data-preload-style crossorigin>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </noscript>
    <script nonce="<?= $cspNonce ?>">
        (function() {
            document.querySelectorAll('link[data-preload-style]').forEach(function(link) {
                try {
                    var ss = document.createElement('link');
                    ss.rel = 'stylesheet';
                    ss.href = link.href;
                    if (link.crossOrigin) {
                        ss.crossOrigin = link.crossOrigin;
                    }
                    document.head.appendChild(ss);
                } catch (e) {
                    /* noop */ }
            });
        }());
    </script>

    <!-- Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1"
        rel="stylesheet">

    <!-- Design System -->
    <link href="/css/design-tokens.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/components/badge.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/layouts/supervisor.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/backoffice/supervisor-dashboard.css?v=<?= e($assetVersion) ?>" rel="stylesheet">

    <!-- CSS específico por vista -->
    <?php foreach ($extraCss as $css): ?>
        <link href="/css/sections/<?= e($css) ?>?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>

<body class="supervisor-mode">

    <!-- TOP-BAR -->
    <header class="supervisor-header">

        <div class="supervisor-header__brand">
            <img src="/images/logos/komorebi-logo-icon.svg"
                alt="Komorebi"
                class="supervisor-header__logo">
            <div>
                <div class="supervisor-header__cafe"><?= e($cafeName) ?></div>
                <div class="supervisor-header__role">Supervisor</div>
            </div>
        </div>

        <!-- Reloj en tiempo real -->
        <div class="supervisor-header__clock" id="supervisorClock">--:--</div>

        <div class="supervisor-header__actions">
            <span class="supervisor-header__cafe" style="font-size:0.8rem; font-weight:400;">
                <?= e($userName) ?>
            </span>
            <form method="POST" action="/logout" style="margin:0;">
                <?= Csrf::field() ?>
                <button type="submit"
                    class="btn-supervisor-logout"
                    title="Cerrar sesión"
                    aria-label="Cerrar sesión">
                    <span class="material-symbols-outlined" aria-hidden="true"
                        style="font-size:1rem;">power_settings_new</span>
                    Salir
                </button>
            </form>
        </div>

    </header>

    <!-- CONTENIDO -->
    <main class="supervisor-content" id="main-content">
        <?= $content ?>
    </main>

    <!-- Reloj inline (sin fichero separado para minimizar dependencias) -->
    <script nonce="<?= $cspNonce ?>">
        (function() {
            var el = document.getElementById('supervisorClock');
            if (!el) {
                return;
            }

            function tick() {
                var now = new Date();
                var h = String(now.getHours()).padStart(2, '0');
                var m = String(now.getMinutes()).padStart(2, '0');
                var s = String(now.getSeconds()).padStart(2, '0');
                el.textContent = h + ':' + m + ':' + s;
            }
            tick();
            setInterval(tick, 1000);
        }());
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

</body>

</html>
