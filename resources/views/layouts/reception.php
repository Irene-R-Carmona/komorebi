<!DOCTYPE html>
<html lang="es">

<head>
    <?php

    use App\Core\Csrf;

    // CSP Nonce para scripts inline
    $cspNonce = $GLOBALS['cspNonce'] ?? '';
    ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <title>Komorebi Reception</title>

    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Epilogue:ital,wght@0,100..900;1,100..900&display=swap" as="style" data-preload-style crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" as="style" data-preload-style crossorigin>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Epilogue:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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

    <link href="/css/workspaces/reception.css" rel="stylesheet">
    <?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
            <script src="/js/<?= $js ?>"></script>
    <?php endforeach;
    endif; ?>
    <!-- Componentes centralizados -->
    <script src="/js/components/fallbacks.js"></script>
    <script defer src="/js/init/event-delegation.js"></script>
    <script nonce="<?= $cspNonce ?? '' ?>" src="/js/init/alpine-components.js"></script>
    <script src="/js/sections/reception.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script nonce="<?= $cspNonce ?? '' ?>">
        window.__MERCURE__ = {
            cafeId: <?= (int) ($cafe_id ?? 0) ?>,
            hub: '/.well-known/mercure'
        };
    </script>
</head>

<body class="reception-mode">
    <?= $content ?>
</body>

</html>
