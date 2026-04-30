<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Layout principal público.
 *
 * Incluye header, navegación, contenido principal y footer.
 * Variables esperadas: $titulo, $css, $js
 */

// CSP Nonce para scripts inline
$cspNonce = $GLOBALS['cspNonce'] ?? '';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

$content ??= '';
$extraCss ??= [];
$extraJs ??= [];
?>
<!DOCTYPE html>
<html lang="es" <?= isset($_SESSION['user_id']) ? 'data-authenticated="1"' : '' ?> x-data="{
    tema: localStorage.getItem('komorebi_tema') || 'claro',
    menuMovil: false,
    usuarioMenu: false
}" :data-tema="tema">

<head>
    <meta charset="UTF-8">
    <!-- FOUC prevention: preload dark theme antes de que Alpine.js hidrate (T11.3) -->
    <script nonce="<?= $cspNonce ?>">
        (function() {
            try {
                var t = localStorage.getItem('komorebi_tema');
                if (t === 'oscuro') {
                    document.documentElement.dataset.tema = 'oscuro';
                }
            } catch (e) {
                /* localStorage bloqueado (incognito/CSP) */
            }
        })();
    </script>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <title><?= $titulo ?? 'Komorebi Café' ?> | 木漏れ日カフェ</title>

    <link rel="icon" type="image/svg+xml" href="/images/logos/komorebi-logo-icon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Kaisei+Decol&family=Shippori+Mincho:wght@400;600&family=Zen+Maru+Gothic:wght@400;500;700&display=swap" as="style" data-preload-style crossorigin>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Kaisei+Decol&family=Shippori+Mincho:wght@400;600&family=Zen+Maru+Gothic:wght@400;500;700&display=swap" rel="stylesheet">
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

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Bootstrap Icons (CDN fallback) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="" crossorigin="anonymous">
    <!-- Estilos del proyecto -->
    <link href="/css/global.css" rel="stylesheet">
    <!-- Design System Components -->
    <link href="/css/components/focus.css" rel="stylesheet">
    <link href="/css/components/buttons.css" rel="stylesheet">
    <link href="/css/components/cards.css" rel="stylesheet">
    <link href="/css/components/forms.css" rel="stylesheet">
    <link href="/css/components/toast.css" rel="stylesheet">
    <link href="/css/components/skeleton.css" rel="stylesheet">
    <link href="/css/components/empty-state.css" rel="stylesheet">
    <link href="/css/sections/fusuma-layout.css" rel="stylesheet">
    <link href="/css/sections/cookie-banner.css" rel="stylesheet">

    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link href="/css/sections/<?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    // Si por alguna razón la vista inyectó <link rel="stylesheet"> dentro del contenido,
    // mover esos enlaces al <head> para asegurar que los estilos se carguen correctamente.
    if (isset($content) && $content instanceof \App\Core\Raw) {
        $contentStr = (string) $content;
        $found = [];
        if (preg_match_all('#<link\s+href="(/css/sections/[^"]+)"\s+rel="stylesheet"[^>]*>#i', $contentStr, $m)) {
            $found = $m[1] ?? [];
        }

        if (!empty($found)) {
            foreach ($found as $href) {
                echo '<link href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
            }
            // Eliminar los enlaces encontrados del contenido para evitar duplicados
            $content = new \App\Core\Raw(preg_replace('#<link\\s+href="(/css/sections/[^"]+)"\\s+rel="stylesheet"[^>]*>#i', '', $contentStr));
        }
    }

?>

    <!-- Event delegation helper: replace inline handlers with `data-action` -->
    <script defer src="/js/init/event-delegation.js"></script>

</head>

<body>
    <!-- Skip link para accesibilidad -->
    <a href="#main" class="skip-link">Saltar al contenido principal</a>

    <!-- HEADER — Minimalista y funcional -->
    <header class="header" id="header">
        <div class="header__container">
            <!-- Logo izquierda -->
            <a class="header__logo" href="/" aria-label="Komorebi Café - Ir a inicio">
                <span class="header__logo-icon" aria-hidden="true">
                    <img src="/images/logos/komorebi-logo-icon.svg" width="32" height="32" alt="">
                </span>
                <span class="header__logo-text">Komorebi</span>
            </a>

            <!-- Navegación centrada — simple sin dropdowns -->
            <nav aria-label="Navegación principal" class="header__nav">
                <a class="header__link <?= str_starts_with($currentPath, '/cafes') ? 'header__link--activo' : '' ?>" href="/cafes">Cafés</a>
                <a class="header__link <?= str_starts_with($currentPath, '/menu') ? 'header__link--activo' : '' ?>" href="/menu">Carta</a>
                <a class="header__link <?= (in_array($currentPath, ['/historia', '/faq'], true) || str_starts_with($currentPath, '/quiz')) ? 'header__link--activo' : '' ?>" href="/historia">Nosotros</a>
                <a class="header__link <?= ($currentPath === '/contacto') ? 'header__link--activo' : '' ?>" href="/contacto">Contacto</a>
            </nav>

            <!-- Acciones derecha (tema + login + reservar) -->
            <div class="header__actions">
                <!-- Toggle de tema claro/oscuro -->
                <button
                    @click="tema = (tema === 'claro' ? 'oscuro' : 'claro'); localStorage.setItem('komorebi_tema', tema)"
                    class="header__icon-btn"
                    :title="tema === 'claro' ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro'"
                    type="button"
                    aria-label="Cambiar tema">
                    <svg x-show="tema === 'claro'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                    <svg x-show="tema === 'oscuro'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/profile" class="header__icon-btn" title="Mi perfil" aria-label="Mi perfil">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </a>
                    <form method="POST" action="/logout" style="display:inline;margin:0;">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="header__icon-btn" title="Cerrar sesion" style="background:none;border:none;cursor:pointer;color:inherit;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="/login" class="header__icon-btn" title="Iniciar sesión" aria-label="Iniciar sesión">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                    </a>
                <?php endif; ?>

                <a href="/reservas" class="header__cta <?= str_starts_with($currentPath, '/reservas') ? 'header__cta--activo' : '' ?>">
                    Reservar mesa
                </a>
            </div>

            <!-- Hamburger menu mobile -->
            <div class="header__mobile-toggle">
                <button class="header__mobile-btn"
                    :class="{ 'is-open': menuMovil }"
                    @click="menuMovil = !menuMovil"
                    :aria-expanded="menuMovil ? 'true' : 'false'"
                    aria-label="Abrir menú de navegación"
                    type="button">
                    <svg class="icon-hamburger" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                    <svg class="icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="header__mobile-menu"
            x-show="menuMovil"
            x-cloak
            @click.away="menuMovil = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2">
            <nav class="header__mobile-nav" aria-label="Navegación móvil">
                <a class="header__mobile-link" href="/cafes">Cafés</a>
                <a class="header__mobile-link" href="/menu">Carta</a>
                <a class="header__mobile-link" href="/historia">Nosotros</a>
                <a class="header__mobile-link" href="/contacto">Contacto</a>
                <a class="header__mobile-link" href="/faq">FAQ</a>
                <a class="header__mobile-link" href="/reservas">Reservar mesa</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a class="header__mobile-link" href="/login">Iniciar sesión</a>
                <?php else: ?>
                    <a class="header__mobile-link" href="/user/waitlists">Mis listas de espera</a>
                    <a class="header__mobile-link" href="/perfil">Mi perfil</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1">
        <!-- Aquí se inyecta el contenido específico de cada página -->
        <?= $content ?>
    </main>

    <!-- FOOTER BLUE BOTTLE STYLE — 3 columnas + Newsletter integrado -->
    <footer class="footer">
        <div class="footer__container">
            <!-- Newsletter integrado (NO popup) -->
            <div class="footer__newsletter"
                x-data="{ email: '', state: 'idle', message: '' }"
                @submit.prevent="
                     state = 'loading';
                     fetch('/api/v1/newsletter/subscriptions', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/json' },
                         body: JSON.stringify({ email })
                     })
                     .then(function(r) { return r.json(); })
                     .then(function(d) {
                         state = 'success';
                         message = (d.data && d.data.message) ? d.data.message : 'Suscripción realizada. ¡Gracias!';
                         email = '';
                     })
                     .catch(function() {
                         state = 'error';
                         message = 'Error al suscribirse. Inténtalo de nuevo.';
                     })
                     .finally(function() {
                         if (state === 'loading') state = 'idle';
                     })
                 ">
                <h2 class="footer__newsletter-title">Únete a la comunidad Komorebi</h2>
                <p class="footer__newsletter-desc">Recibe nuestras novedades sobre cafés de especialidad y eventos</p>
                <form class="footer__newsletter-form">
                    <input type="email" x-model="email" class="footer__newsletter-input" placeholder="tu@email.com" required>
                    <button type="submit" class="footer__newsletter-btn" :disabled="state === 'loading'"
                        x-text="state === 'loading' ? 'Enviando…' : 'Suscribir'">Suscribir</button>
                </form>
                <p x-show="message" x-text="message" role="alert"
                    :class="state === 'error' ? 'footer__newsletter-status footer__newsletter-status--error' : 'footer__newsletter-status'"></p>
            </div>

            <!-- Grid de 3 columnas -->
            <div class="footer__columns">
                <!-- Columna 1: KOMOREBI CAFÉ -->
                <div class="footer__column">
                    <h3 class="footer__column-title">Komorebi Café</h3>
                    <nav class="footer__links" aria-label="Enlaces principales">
                        <a href="/cafes" class="footer__link">Catálogo de Cafés</a>
                        <a href="/menu" class="footer__link">Carta</a>
                        <a href="/reservas" class="footer__link">Reservar Mesa</a>
                        <a href="/quiz" class="footer__link">Quiz Café Ideal</a>
                    </nav>
                </div>

                <!-- Columna 2: INFORMACIÓN -->
                <div class="footer__column">
                    <h3 class="footer__column-title">Información</h3>
                    <nav class="footer__links" aria-label="Enlaces informativos">
                        <a href="/historia" class="footer__link">Nuestra Historia</a>
                        <a href="/faq" class="footer__link">FAQ</a>
                        <a href="/contacto" class="footer__link">Contacto</a>
                        <a href="/legal/privacidad" class="footer__link">Privacidad</a>
                        <a href="/legal/cookies" class="footer__link">Cookies</a>
                        <a href="/legal/terminos" class="footer__link">Términos</a>
                    </nav>
                </div>

                <!-- Columna 3: CONECTA -->
                <div class="footer__column">
                    <h3 class="footer__column-title">Conecta</h3>
                    <nav class="footer__links" aria-label="Redes sociales">
                        <a href="https://instagram.com/komorebi" class="footer__link" target="_blank" rel="noopener">Instagram</a>
                        <a href="https://facebook.com/komorebi" class="footer__link" target="_blank" rel="noopener">Facebook</a>
                        <a href="mailto:info@komorebi.cafe" class="footer__link">Email</a>
                    </nav>
                </div>
            </div>

            <!-- Badges informativos (sin decoración) -->
            <div class="footer__badges">
                <span class="footer__badge">
                    <span class="footer__badge-icon"><i class="bi bi-cup-hot" aria-hidden="true"></i></span>
                    Café de Especialidad
                </span>
                <span class="footer__badge">
                    <span class="footer__badge-icon"><i class="bi bi-paw" aria-hidden="true"></i></span>
                    Apto Mascotas
                </span>
                <span class="footer__badge">
                    <span class="footer__badge-icon"><i class="bi bi-leaf" aria-hidden="true"></i></span>
                    Sostenible
                </span>
            </div>

            <!-- Bottom bar -->
            <div class="footer__bottom">
                <div class="footer__copyright">
                    <span>© 2025-2026 Komorebi Café</span>
                    <span>·</span>
                    <span>Madrid, España</span>
                </div>

                <div class="footer__social">
                    <a href="https://instagram.com/komorebi" class="footer__social-link" title="Instagram" target="_blank" rel="noopener" aria-label="Síguenos en Instagram">
                        <span class="bi bi-instagram" aria-hidden="true"></span>
                    </a>
                    <a href="https://facebook.com/komorebi" class="footer__social-link" title="Facebook" target="_blank" rel="noopener" aria-label="Síguenos en Facebook">
                        <span class="bi bi-facebook" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Cookie Banner (RGPD) -->
    <?php require_once __DIR__ . '/../components/cookie-banner.php'; ?>

    <!-- Newsletter Popup -->
    <?php require_once __DIR__ . '/../components/newsletter-popup.php'; ?>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <!-- Scripts específicos y Alpine.js -->
    <script defer src="/js/cookie-helper.js"></script>
    <script defer src="/js/ux-enhancements.js"></script>

    <!-- Componentes centralizados (deben cargarse antes de los scripts de sección y del registro) -->
    <script src="/js/components/toastManager.js"></script>
    <script src="/js/components/fallbacks.js"></script>
    <script src="/js/components/catalogo.js"></script>
    <script src="/js/components/page-data.js"></script>
    <script src="/js/components/cookieBanner.js"></script>
    <script src="/js/components/cookiePreferences.js"></script>
    <script src="/js/components/loadingState.js"></script>
    <script src="/js/components/dataTable.js"></script>
    <script src="/js/components/recentlyViewedWidget.js"></script>
    <script src="/js/components/climaWidget.js"></script>
    <script src="/js/components/detalleCafe.js"></script>
    <script src="/js/components/newsletterPopup.js"></script>
    <script src="/js/components/reviewForm.js"></script>
    <script src="/js/components/loyaltyRewards.js"></script>
    <script src="/js/components/quizFilosofico.js"></script>
    <script src="/js/components/recentlyViewed-tracker.js"></script>

    <!-- Scripts de sección (ligeros, deben usar las fábricas expuestas por components) -->
    <script src="/js/sections/catalogo.js"></script>
    <script src="/js/sections/reservas.js"></script>
    <script src="/js/sections/menu.js"></script>
    <script src="/js/sections/detalle-cafe.js"></script>
    <script src="/js/sections/quiz-component.js"></script>
    <script src="/js/sections/avatar-upload.js"></script>
    <script src="/js/sections/perfil.js"></script>

    <!-- Alpine components registry (central) -->
    <script nonce="<?= $cspNonce ?? '' ?>" src="/js/init/alpine-components.js"></script>

    <!-- Sticky header scroll watcher -->
    <script nonce="<?= $cspNonce ?? '' ?>">
        (function() {
            var header = document.getElementById('header');
            if (!header) return;
            var sentinel = document.createElement('div');
            sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none';
            document.body.prepend(sentinel);
            new IntersectionObserver(function(entries) {
                header.classList.toggle('header--scrolled', !entries[0].isIntersecting);
            }).observe(sentinel);
        })();
    </script>

    <!-- Toast Container — escucha $dispatch('toast', { message, type }) desde cualquier componente Alpine -->
    <div
        x-data="toastManager()"
        class="toast-container"
        aria-live="polite"
        aria-label="Notificaciones"
        role="region">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                class="toast-komorebi"
                :class="'toast-komorebi--' + toast.type"
                x-transition:enter="toast-enter-active"
                x-transition:enter-start="toast-enter"
                x-transition:leave="toast-leave-active"
                x-transition:leave-end="toast-leave-to"
                role="alert">
                <span class="toast-komorebi__icon" :class="toast.icon" aria-hidden="true"></span>
                <div class="toast-komorebi__body">
                    <p x-show="toast.title" class="toast-komorebi__title" x-text="toast.title"></p>
                    <p class="toast-komorebi__message" x-text="toast.message"></p>
                </div>
                <button
                    class="toast-komorebi__close"
                    @click="dismiss(toast.id)"
                    :aria-label="'Cerrar notificación: ' + toast.message"
                    type="button">
                    <span class="bi bi-x" aria-hidden="true"></span>
                </button>
                <span
                    class="toast-komorebi__progress"
                    :style="'--toast-duration:' + toast.duration + 'ms'"
                    aria-hidden="true"></span>
            </div>
        </template>
    </div>

    <!-- Alpine.js debe cargarse AL FINAL para que todos los componentes se registren primero -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</body>

</html>
