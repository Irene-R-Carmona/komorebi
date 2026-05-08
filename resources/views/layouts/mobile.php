<!DOCTYPE html>
<html lang="es">

<head>
    <?php
    // CSP Nonce para scripts inline
    $cspNonce = $GLOBALS['cspNonce'] ?? '';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $titulo ?? 'App' ?></title>

    <link href="/css/global.css" rel="stylesheet">
    <link href="/css/mobile.css" rel="stylesheet">

    <!-- CSS Específico Inyectado -->
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
            <link href="/css/<?= $css ?>" rel="stylesheet">
    <?php endforeach;
    endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1" defer></script>
    <!-- Componentes centralizados -->
    <script src="/js/components/fallbacks.js"></script>
    <script src="/js/components/catalogo.js"></script>
    <script src="/js/components/detalleCafe.js"></script>
    <script src="/js/components/reviewForm.js"></script>
    <script src="/js/components/loyaltyRewards.js"></script>
    <script src="/js/components/quizFilosofico.js"></script>
    <script nonce="<?= $cspNonce ?? '' ?>" src="/js/init/alpine-components.js"></script>
    <script defer src="/js/vendor/alpine.min.js"></script>
</head>

<body class="mobile-body">

    <!-- TOP BAR -->
    <header class="mobile-top">
        <h1 class="page-title"><?= $titulo ?></h1>
        <div class="user-avatar-xs">
            <?= e(strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1))) ?>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="mobile-content">
        <?= $content ?>
        <div class="spacer-bottom"></div>
    </main>

    <!-- BOTTOM NAV -->
    <nav class="mobile-bottom-nav">
        <?php if (!empty($mobileMenu)): foreach ($mobileMenu as $item):
            $isActive = ($_SERVER['REQUEST_URI'] == $item['url']) ? 'active' : '';
            ?>
                <a href="<?= $item['url'] ?>" class="nav-icon <?= $isActive ?>">
                    <i class="ph ph-<?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                </a>
        <?php endforeach;
        endif; ?>

        <form method="POST" action="/logout" style="margin:0;">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="nav-icon logout" style="background:none;border:none;cursor:pointer;width:100%;">
                <i class="ph ph-sign-out"></i>
                <span>Salir</span>
            </button>
        </form>
    </nav>

</body>

</html>
