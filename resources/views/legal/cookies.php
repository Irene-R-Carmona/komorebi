<?php

declare(strict_types=1);

/**
 * Vista: Política de Cookies
 * Diseño: TOC lateral + contenido estrecho
 */
?>
<div class="static-page">
    <header class="static-hero">
        <span class="static-hero__icon">🍪</span>
        <h1 class="static-hero__title">Política de Cookies</h1>
        <p class="static-hero__subtitle">Información completa sobre cookies en Komorebi Café</p>
        <p class="static-hero__date">Última actualización: 1 de febrero de 2026</p>
    </header>

    <div class="static-layout">
        <aside class="static-sidebar">
            <h2 class="static-sidebar__title">En esta página</h2>
            <nav class="static-sidebar__nav">
                <a href="#que-son" class="static-sidebar__link">1. ¿Qué son las cookies?</a>
                <a href="#esenciales" class="static-sidebar__link">2. Cookies esenciales</a>
                <a href="#analisis" class="static-sidebar__link">3. Cookies de análisis</a>
                <a href="#gestion" class="static-sidebar__link">4. Gestión de preferencias</a>
                <a href="#navegadores" class="static-sidebar__link">5. Configuración navegadores</a>
                <a href="#legal" class="static-sidebar__link">6. Marco legal</a>
                <a href="#cambios" class="static-sidebar__link">7. Cambios</a>
            </nav>
        </aside>

        <article class="static-content">
            <h2 id="que-son">1. ¿Qué son las cookies?</h2>
            <p>Las cookies son pequeños archivos de texto que se almacenan en tu dispositivo cuando visitas un sitio web. Permiten al sitio recordar información sobre tu visita, como tus preferencias, idioma, sesión activa, etc.</p>

            <h2 id="esenciales">2. Cookies estrictamente necesarias</h2>
            <p>Estas cookies son imprescindibles para el funcionamiento del sitio. <strong>No requieren tu consentimiento</strong> (ePrivacy Directive Art. 5.3 excepción).</p>

            <table style="width: 100%; border-collapse: collapse; margin: 1.5rem 0;">
                <thead>
                    <tr style="background: var(--color-fondo-alt);">
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid var(--color-borde);">Cookie</th>
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid var(--color-borde);">Propósito</th>
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid var(--color-borde);">Duración</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);"><code>PHPSESSID</code></td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Mantiene sesión activa (login, carrito, reservas)</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Sesión (se elimina al cerrar navegador)</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);"><code>csrf_token</code></td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Protección contra ataques CSRF</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Sesión</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);"><code>cookie_consent</code></td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Almacena decisión sobre cookies (para no volver a preguntar)</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">1 año</td>
                    </tr>
                </tbody>
            </table>

            <div class="static-content__callout static-content__callout--info">
                <strong>📌 Importante:</strong> Si bloqueas estas cookies, algunas funcionalidades (login, reservas, formularios) no funcionarán correctamente.
            </div>

            <h2 id="analisis">3. Cookies de análisis y rendimiento</h2>
            <p>Actualmente <strong>NO utilizamos cookies de análisis ni de terceros</strong> (Google Analytics, Facebook Pixel, etc.).</p>
            <p>Si en el futuro implementamos herramientas de análisis, te solicitaremos <strong>consentimiento explícito</strong> mediante nuestro banner de cookies antes de activarlas.</p>

            <h2 id="gestion">4. Gestión de tus preferencias de cookies</h2>
            <p>Tienes varias opciones para gestionar cookies:</p>

            <h3>4.1. Banner de cookies</h3>
            <p>Al visitar el sitio por primera vez, verás un banner donde puedes elegir:</p>
            <ul>
                <li><strong>"Aceptar"</strong> → Solo cookies esenciales (configuración actual)</li>
                <li><strong>"Solo esenciales"</strong> → Igual que "Aceptar" (no hay cookies opcionales actualmente)</li>
            </ul>

            <h3>4.2. Panel de preferencias</h3>
            <p>Puedes cambiar tus preferencias en cualquier momento desde tu <a href="/perfil">perfil de usuario</a> → "Privacidad y cookies".</p>

            <h3>4.3. Navegador</h3>
            <p>Todos los navegadores modernos permiten gestionar cookies. Ten en cuenta que bloquear cookies esenciales afectará a la funcionalidad del sitio.</p>

            <h2 id="navegadores">5. Configuración por navegador</h2>

            <h3>Google Chrome</h3>
            <p>Menú (⋮) → <strong>Configuración</strong> → <strong>Privacidad y seguridad</strong> → <strong>Cookies y otros datos de sitios</strong></p>

            <h3>Mozilla Firefox</h3>
            <p>Menú (☰) → <strong>Opciones</strong> → <strong>Privacidad y seguridad</strong> → <strong>Cookies y datos del sitio</strong></p>

            <h3>Safari (macOS)</h3>
            <p><strong>Safari</strong> → <strong>Preferencias</strong> → <strong>Privacidad</strong> → <strong>Gestionar datos de sitios web</strong></p>

            <h3>Microsoft Edge</h3>
            <p>Menú (⋯) → <strong>Configuración</strong> → <strong>Cookies y permisos de sitio</strong> → <strong>Cookies y datos almacenados</strong></p>

            <div class="static-content__callout static-content__callout--warning">
                <strong>⚠️ Advertencia:</strong> Si configuras tu navegador para bloquear todas las cookies, no podrás iniciar sesión ni realizar reservas en Komorebi Café.
            </div>

            <h2 id="legal">6. Marco legal</h2>
            <p>Nuestra política de cookies cumple con:</p>
            <ul>
                <li><strong>RGPD</strong> (Reglamento General de Protección de Datos UE 2016/679)</li>
                <li><strong>ePrivacy Directive</strong> (Directiva 2002/58/CE modificada por 2009/136/CE)</li>
                <li><strong>LOPDGDD</strong> (Ley Orgánica 3/2018 de Protección de Datos y Garantía de los Derechos Digitales)</li>
                <li><strong>LSSI</strong> (Ley 34/2002 de Servicios de la Sociedad de la Información y Comercio Electrónico)</li>
            </ul>

            <h3>Cookies exentas de consentimiento</h3>
            <p>Según el Considerando 66 de la ePrivacy Directive, las cookies esenciales para "servicios expresamente solicitados por el usuario" están exentas de consentimiento previo.</p>

            <h2 id="cambios">7. Cambios en esta política</h2>
            <p>Esta política puede actualizarse periódicamente para reflejar:</p>
            <ul>
                <li>Cambios en nuestras prácticas de cookies</li>
                <li>Nuevas funcionalidades que requieran cookies adicionales</li>
                <li>Actualizaciones en la normativa legal</li>
            </ul>

            <p><strong>Notificación de cambios:</strong></p>
            <ul>
                <li>Cambios significativos → Banner de cookies actualizado + email a usuarios registrados</li>
                <li>Cambios menores → Solo actualización de esta página</li>
            </ul>

            <p><strong>Versión actual:</strong> 1.0 (1 de febrero de 2026)</p>
        </article>
    </div>

    <div class="static-cta">
        <h3 class="static-cta__title">¿Tienes dudas sobre cookies?</h3>
        <p class="static-cta__text">Contacta con nuestro equipo de privacidad.</p>
        <a href="/contacto" class="btn">Contactar</a>
    </div>
</div>
