<!DOCTYPE html>
<html lang="es" class="dark">

<head>
    <?php

    use App\Core\Csrf;

    // CSP Nonce para scripts inline
    $cspNonce = $GLOBALS['cspNonce'] ?? '';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <title>KDS | <?= e($cafe_name ?? 'Cocina') ?></title>
    <link rel="icon" type="image/svg+xml" href="/images/logos/komorebi-logo-icon.svg">
    <link rel="alternate icon" href="/favicon.ico">

    <!-- Fuentes Técnicas (Space Grotesk + Roboto Mono) -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;700&family=Noto+Sans:wght@400;700&display=swap" as="style" data-preload-style crossorigin>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;700&family=Noto+Sans:wght@400;700&display=swap" rel="stylesheet">
    </noscript>

    <script nonce="<?= $cspNonce ?? '' ?>">
        (function() {
            document.querySelectorAll('link[data-preload-style]').forEach(function(link) {
                try {
                    var ss = document.createElement('link');
                    ss.rel = 'stylesheet';
                    ss.href = link.href;
                    if (link.crossOrigin) ss.crossOrigin = link.crossOrigin;
                    document.head.appendChild(ss);
                } catch (e) {
                    /* noop */
                }
            });
        })();
    </script>

    <!-- Iconos Material Symbols -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1"
        rel="stylesheet">

    <!-- CSS Específico KDS + SOP -->
    <link href="/css/workspaces/kds.css" rel="stylesheet">
    <link href="/css/workspaces/kds-sop.css" rel="stylesheet">

    <!-- Scripts Alpine (local vendor) -->
    <!-- Componentes centralizados -->
    <script src="/js/components/fallbacks.js"></script>
    <script defer src="/js/init/event-delegation.js"></script>
    <script src="/js/components/catalogo.js"></script>
    <script src="/js/components/detalleCafe.js"></script>
    <script src="/js/components/reviewForm.js"></script>
    <script src="/js/components/loyaltyRewards.js"></script>
    <script src="/js/components/quizFilosofico.js"></script>
    <script nonce="<?= $cspNonce ?? '' ?>" src="/js/init/alpine-components.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <script src="/js/sections/kds.js"></script>

    <script nonce="<?= $cspNonce ?? '' ?>">
        window.__MERCURE__ = {
            cafeId: <?= (int) ($cafe_id ?? 0) ?>,
            hub: '/.well-known/mercure'
        };
    </script>
</head>

<body class="kds-mode">

    <!-- FONDO REJILLA (Effect) -->
    <div class="blueprint-bg"></div>

    <!-- HEADER -->
    <header class="kds-header">
        <div class="kds-brand">
            <div>
                <h1 style="margin:0; font-size:1.25rem;">KITCHEN CONTROL</h1>
                <div class="kds-subtitle">SYSTEM ONLINE // <?= e($cafe_name ?? '') ?></div>
            </div>
        </div>

        <div class="kds-meta">
            <div class="meta-item">
                <span class="meta-lbl">Open Tickets</span>
                <span class="meta-val" style="color:var(--color-warn);" id="kdsOpenTickets"><?= $total_tickets ?? 0 ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-lbl">Avg Time</span>
                <span class="meta-val"><?= e($avg_prep_time_formatted ?? '--:--') ?></span>
            </div>
        </div>

        <div style="display:flex; gap:1.5rem; align-items:center;">
            <div class="kds-clock-box" id="kdsClock">--:--</div>
            <form method="POST" action="/logout" style="margin:0;">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn-out" title="Cerrar sesión" aria-label="Cerrar sesión"><span class="material-symbols-outlined">power_settings_new</span></button>
            </form>
        </div>
    </header>

    <!-- CONTENIDO -->
    <?= $content ?>

    <script src="/js/pages/kdsClock.js" nonce="<?= $cspNonce ?? '' ?>"></script>
</body>

</html>
