<?php
// Lógica de Menú
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Env;
use App\Services\NavigationService;

// CSP Nonce para scripts inline
$cspNonce = $GLOBALS['cspNonce'] ?? '';

$role = $_SESSION['user_role'] ?? 'user';
$userName = $_SESSION['user_name'] ?? 'Usuario';
$cafeName = $_SESSION['user_cafe_name'] ?? null;
$menu = Container::make(NavigationService::class)->getMenu($role);
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';

$content ??= '';
$extraCss ??= [];
$extraJs ??= [];
$assetVersion = Env::get('APP_VERSION', '1');
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light" data-tema="claro">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <script nonce="<?= $cspNonce ?>">
        (function() {
            try {
                var t = localStorage.getItem('komorebi_bo_tema');
                if (t === 'oscuro') {
                    document.documentElement.setAttribute('data-tema', 'oscuro');
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch (e) {
                /* localStorage bloqueado */
            }
        })();
    </script>
    <script nonce="<?= $cspNonce ?>">
        window.AppRoutes = {
            adminNewsletterSubscribers: '/api/v1/admin/newsletter/subscribers',
            adminLoyaltyCatalog:        '/api/v1/admin/loyalty/catalog',
            adminUsers:                 '/api/v1/admin/users',
            keeperAnimals:              '/api/v1/keeper/animals',
        };
    </script>
    <title>Komorebi OS | <?= $titulo ?? 'Panel' ?></title>

    <!-- Preconnect to CDNs for faster resource loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FontAwesome eliminado: Bootstrap Icons cubre todos los casos de uso -->

    <?php
    // Chart.js - Solo cargar en dashboards (tree-shaked bundle, -55% size)
    $needsCharts = str_contains($currentUri, '/dashboard');
if ($needsCharts): ?>
        <script defer src="/js/charts.min.js?v=<?= e($assetVersion) ?>"></script>
    <?php endif; ?>

    <!-- Tipografía Komorebi OS —— Zen Maru Gothic (fuente-cuerpo oficial) -->
    <link href="https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Modern Design System -->
    <link href="/css/design-tokens.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/backoffice-modern.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/backoffice-ux.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/sections/admin/admin-common.css?v=<?= e($assetVersion) ?>" rel="stylesheet">

    <!-- Component Library -->
    <link href="/css/components/button.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/components/card.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/components/badge.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/components/modal.css?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <link href="/css/components/stat-card.css?v=<?= e($assetVersion) ?>" rel="stylesheet">

    <?php
// Detectar dashboard actual y cargar CSS correspondiente
if (str_contains($currentUri, '/manager/dashboard')) {
    echo '<link href="/css/backoffice/manager-dashboard.css?v=' . e($assetVersion) . '" rel="stylesheet">' . "\n    ";
} elseif (str_contains($currentUri, '/supervisor/dashboard')) {
    echo '<link href="/css/backoffice/supervisor-dashboard.css?v=' . e($assetVersion) . '" rel="stylesheet">' . "\n    ";
}
?>

    <!-- CSS específico por vista -->
    <?php foreach ($extraCss as $css): ?>
        <link href="/css/sections/<?= e($css) ?>?v=<?= e($assetVersion) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>

<body class="d-flex" data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Skip Link (Accesibilidad) -->
    <a href="#main-content" class="skip-link">Saltar al contenido principal</a>

    <!-- SIDEBAR MODERNO KOMOREBI -->
    <aside class="sidebar" id="sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <h4>
                <i class="bi bi-cup-hot sidebar-brand__icon" aria-hidden="true"></i> Komorebi<span class="text-warning">OS</span>
            </h4>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav" aria-label="Navegación principal">
            <?php foreach ($menu as $group => $items): ?>
                <h6 class="sidebar-heading <?= $group !== 'Sistema' ? 'sidebar-heading--spaced' : '' ?>">
                    <?= $group ?>
                </h6>
                <ul class="nav flex-column">
                    <?php foreach ($items as $item):
                        $isActive = ($currentUri === $item['url']) ? 'active' : '';
                        ?>
                        <li class="nav-item">
                            <a href="<?= $item['url'] ?>" class="nav-link <?= $isActive ?>">
                                <i class="bi bi-<?= $item['icon'] ?>"></i>
                                <span><?= $item['label'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <!-- Info del Usuario -->
        <div class="sidebar-user">
            <div class="d-flex align-items-center">
                <div class="avatar">
                    <?= strtoupper($userName[0]) ?>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="fw-semibold"><?= e($userName) ?></div>
                    <small class="text-uppercase"><?= $role ?></small>
                </div>
                <form method="POST" action="/logout" class="d-inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-light btn-logout" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        <span class="d-none d-md-inline">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content flex-grow-1 d-flex flex-column">
        <!-- Header -->
        <header class="navbar border-bottom px-4 py-3 navbar-main">
            <div class="d-flex align-items-center">
                <!-- Mobile menu toggle -->
                <button class="btn btn-link d-lg-none me-3 p-0 navbar-mobile-toggle" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#mobileNav" aria-label="Abrir menú">
                    <i class="bi bi-list fs-3"></i>
                </button>

                <h1 class="h5 mb-0 navbar-page-title"><?= $title ?? $titulo ?? 'Komorebi OS' ?></h1>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?php if ($cafeName): ?>
                    <span class="badge-cafe">
                        <i class="bi bi-geo-alt-fill"></i> <?= e($cafeName) ?>
                    </span>
                <?php endif; ?>

                <!-- Tema claro/oscuro -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary navbar-theme-toggle"
                    id="boThemeToggle"
                    title="Cambiar tema"
                    aria-label="Cambiar tema claro/oscuro">
                    <i class="bi bi-moon-stars" id="boThemeIcon"></i>
                </button>

                <?php if (!empty($flash)): ?>
                    <div class="toast-container position-fixed top-0 end-0 p-3">
                        <div class="toast show" role="alert">
                            <div class="toast-header">
                                <strong class="me-auto"><?= $flash['type'] === 'success' ? 'Éxito' : 'Error' ?></strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                <?= e($flash['message']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Content -->
        <main id="main-content" class="flex-grow-1 overflow-auto p-4" role="main">
            <div class="container-fluid">
                <?= $content ?>
            </div>
        </main>
    </div>

    <!-- Global Delete Confirmation Modal -->
    <?php

    use App\Core\View;

echo View::componentToString('components/admin/delete-confirmation-modal');
?>

    <!-- Mobile Offcanvas Menu -->
    <div class="offcanvas offcanvas-start offcanvas-sidebar" tabindex="-1" id="mobileNav"
        aria-labelledby="mobileNavLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileNavLabel">
                <i class="bi bi-cup-hot sidebar-brand__icon" aria-hidden="true"></i> Komorebi<span class="text-warning">OS</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body">
            <nav aria-label="Navegación móvil">
                <?php foreach ($menu as $group => $items): ?>
                    <h6 class="sidebar-heading">
                        <?= $group ?>
                    </h6>
                    <ul class="nav flex-column">
                        <?php foreach ($items as $item):
                            $isActive = ($currentUri === $item['url']) ? 'active' : '';
                            ?>
                            <li class="nav-item">
                                <a href="<?= $item['url'] ?>" class="nav-link <?= $isActive ?>">
                                    <i class="bi bi-<?= $item['icon'] ?>"></i>
                                    <span><?= $item['label'] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="/js/backoffice-ux.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/sections/admin/admin-common.js?v=<?= e($assetVersion) ?>"></script>

    <!-- Global Components -->
    <script src="/js/components/fallbacks.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/notification-manager.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/delete-confirmation-modal.js?v=<?= e($assetVersion) ?>"></script>

    <!-- Componentes Alpine compartidos por todas las vistas -->
    <script src="/js/components/catalogo.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/detalleCafe.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/reviewForm.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/loyaltyRewards.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/components/quizFilosofico.js?v=<?= e($assetVersion) ?>"></script>
    <script src="/js/init/alpine-components.js?v=<?= e($assetVersion) ?>"></script>

    <!-- Scripts específicos de dashboard -->
    <?php
    if (str_contains($currentUri, '/manager/dashboard')) {
        echo '<script src="/js/backoffice/manager-dashboard.js?v=' . e($assetVersion) . '"></script>' . "\n";
    } elseif (str_contains($currentUri, '/supervisor/dashboard')) {
        echo '<script src="/js/backoffice/supervisor-dashboard.js?v=' . e($assetVersion) . '"></script>' . "\n";
    }
?>

    <!-- JS específico por vista (ANTES de Alpine) -->
    <?php foreach ($extraJs as $js): ?>
        <script src="/js/sections/<?= e($js) ?>?v=<?= e($assetVersion) ?>"></script>
    <?php endforeach; ?>

    <!-- Alpine.js plugins (deben cargarse ANTES de alpine.min.js) -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.14.9/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.14.9/dist/cdn.min.js"></script>

    <!-- Alpine.js se carga AL FINAL (después de los componentes) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <script nonce="<?= $cspNonce ?>">
        (function() {
            var html = document.documentElement;
            var btn = document.getElementById('boThemeToggle');
            var icon = document.getElementById('boThemeIcon');

            function applyTheme(tema) {
                if (tema === 'oscuro') {
                    html.setAttribute('data-tema', 'oscuro');
                    html.setAttribute('data-bs-theme', 'dark');
                    if (icon) {
                        icon.className = 'bi bi-sun';
                    }
                } else {
                    html.setAttribute('data-tema', 'claro');
                    html.setAttribute('data-bs-theme', 'light');
                    if (icon) {
                        icon.className = 'bi bi-moon-stars';
                    }
                }
            }

            // Restore on load
            try {
                var saved = localStorage.getItem('komorebi_bo_tema');
                if (saved) applyTheme(saved);
            } catch (e) {
                /* noop */
            }

            if (btn) {
                btn.addEventListener('click', function() {
                    var current = html.getAttribute('data-tema');
                    var next = current === 'oscuro' ? 'claro' : 'oscuro';
                    applyTheme(next);
                    try {
                        localStorage.setItem('komorebi_bo_tema', next);
                    } catch (e) {
                        /* noop */
                    }
                });
            }
        })();
    </script>

</body>

</html>
