<?php

declare(strict_types=1);

/**
 * Componente: Cookie Consent Banner
 * Responsabilidad: Renderizar banner de consentimiento de cookies
 * Ubicación: Incluir en layout principal antes de </body>
 */
?>
<!-- Cookie Consent Banner (GDPR) -->
<div x-data="cookieConsent()"
    x-show="shouldShow()"
    x-cloak
    class="cookie-banner"
    role="alertdialog"
    aria-labelledby="cookie-banner-title"
    aria-describedby="cookie-banner-description">

    <div class="cookie-banner__container">
        <div class="cookie-banner__text">
            <p id="cookie-banner-description">
                Utilizamos cookies esenciales para el funcionamiento del sitio (sesión y seguridad).
                <a href="/legal/cookies" class="cookie-banner__link">Más información</a>
            </p>
        </div>

        <div class="cookie-banner__actions">
            <button
                @click="accept()"
                class="cookie-banner__btn cookie-banner__btn--accept"
                aria-label="Aceptar cookies esenciales">
                Aceptar
            </button>

            <button
                @click="reject()"
                class="cookie-banner__btn cookie-banner__btn--reject"
                aria-label="Continuar sin aceptar">
                Solo esenciales
            </button>
        </div>
    </div>
</div>
