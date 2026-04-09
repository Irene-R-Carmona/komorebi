<?php

declare(strict_types=1);

/**
 * COOKIE BANNER COMPONENT
 * Banner de consentimiento RGPD con modal de personalización
 * Requiere: Alpine.js, cookie-banner.css
 */

use App\Core\CookieManager;

// No mostrar si ya hay consentimiento
$hasConsent = isset($_COOKIE[CookieManager::COOKIE_CONSENT]);
?>

<div
    x-data="cookieBanner"
    x-init="init()"
    data-initial-show="<?= $hasConsent ? 'false' : 'true' ?>"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="transform translate-y-full"
    x-transition:enter-end="transform translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="transform translate-y-0"
    x-transition:leave-end="transform translate-y-full"
    class="cookie-banner"
    :class="{ 'cookie-banner--visible': show }"
    style="display: none;">
    <div class="cookie-banner__container">
        <div class="cookie-banner__content">
            <h3 class="cookie-banner__title">
                <svg class="cookie-banner__icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none" />
                    <circle cx="7" cy="8" r="1.5" fill="currentColor" />
                    <circle cx="13" cy="7" r="1" fill="currentColor" />
                    <circle cx="8" cy="13" r="1" fill="currentColor" />
                    <circle cx="13" cy="12" r="1.5" fill="currentColor" />
                </svg>
                <span>Gestión de cookies y privacidad</span>
            </h3>
            <p class="cookie-banner__text">
                Este sitio utiliza <strong>cookies esenciales</strong> para garantizar el correcto funcionamiento (sesión, seguridad) y
                <strong>cookies funcionales</strong> para mejorar tu experiencia recordando tus preferencias (filtros de búsqueda, productos vistos recientemente, preferencias dietéticas).
                Puedes aceptar todas, rechazar las opcionales o personalizar tu selección.
                <a href="/legal/cookies" class="cookie-banner__link" target="_blank">Consulta nuestra política de cookies</a>
            </p>
        </div>

        <div class="cookie-banner__actions">
            <button
                @click="acceptAll"
                class="cookie-banner__btn cookie-banner__btn--accept"
                type="button"
                title="Aceptar todas las cookies funcionales">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                    <path d="M13.5 4.5L6 12L2.5 8.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Aceptar todas
            </button>

            <button
                @click="rejectOptional"
                class="cookie-banner__btn cookie-banner__btn--reject"
                type="button"
                title="Usar solo cookies esenciales">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                    <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Rechazar opcionales
            </button>

            <button
                @click="openModal"
                class="cookie-banner__btn cookie-banner__btn--customize"
                type="button"
                title="Personalizar preferencias de cookies">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                    <path d="M8 1V3M8 13V15M15 8H13M3 8H1M12.5 12.5L11.5 11.5M4.5 4.5L3.5 3.5M12.5 3.5L11.5 4.5M4.5 11.5L3.5 12.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    <circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.5" />
                </svg>
                Configurar
            </button>
        </div>
    </div>

    <!-- Modal de personalización -->
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="closeModal"
        class="cookie-modal"
        :class="{ 'cookie-modal--visible': showModal }"
        style="display: none;">
        <div class="cookie-modal__content">
            <div class="cookie-modal__header">
                <h2 class="cookie-modal__title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke="currentColor" stroke-width="1.5" fill="none" />
                        <circle cx="8" cy="10" r="1.5" fill="currentColor" />
                        <circle cx="15" cy="9" r="1" fill="currentColor" />
                        <circle cx="9" cy="15" r="1" fill="currentColor" />
                        <circle cx="15" cy="14" r="1.5" fill="currentColor" />
                    </svg>
                    Configuración de cookies
                </h2>
                <button
                    @click="closeModal"
                    class="cookie-modal__close"
                    type="button"
                    aria-label="Cerrar">
                    ×
                </button>
            </div>

            <div class="cookie-modal__body">
                <!-- Cookies Esenciales -->
                <div class="cookie-category">
                    <div class="cookie-category__header">
                        <h3 class="cookie-category__title">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                <rect x="3" y="8" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5" fill="none" />
                                <path d="M6 8V5C6 3.34315 7.34315 2 9 2H11C12.6569 2 14 3.34315 14 5V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <circle cx="10" cy="12" r="1" fill="currentColor" />
                            </svg>
                            <span>Cookies Esenciales y de Seguridad</span>
                            <span class="cookie-category__badge">REQUERIDAS</span>
                        </h3>
                        <div class="cookie-category__toggle">
                            <input
                                type="checkbox"
                                class="cookie-category__checkbox"
                                checked
                                disabled
                                id="cookie-essential">
                            <label for="cookie-essential" class="cookie-category__slider"></label>
                        </div>
                    </div>
                    <p class="cookie-category__description">
                        Estas cookies son <strong>estrictamente necesarias</strong> para el funcionamiento básico y la seguridad del sitio web.
                        Incluyen gestión de sesión de usuario, protección contra ataques CSRF (Cross-Site Request Forgery),
                        autenticación y almacenamiento de tus preferencias de consentimiento. No almacenan información personal identificable.
                        <strong>No pueden desactivarse</strong> ya que el sitio no funcionaría correctamente sin ellas.
                    </p>
                    <p class="cookie-category__examples">
                        <strong>Ejemplos:</strong> <code>PHPSESSID</code> (sesión), <code>csrf_token</code> (seguridad), <code>cookie_consent</code> (preferencias)
                    </p>
                </div>

                <!-- Cookies Funcionales -->
                <div class="cookie-category">
                    <div class="cookie-category__header">
                        <h3 class="cookie-category__title">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5" fill="none" />
                                <path d="M10 6V10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M16 4L18 2M4 16L2 18M16 16L18 18M4 4L2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            <span>Cookies Funcionales y de Experiencia</span>
                        </h3>
                        <div class="cookie-category__toggle">
                            <input
                                type="checkbox"
                                class="cookie-category__checkbox"
                                x-model="preferences.functional"
                                id="cookie-functional">
                            <label for="cookie-functional" class="cookie-category__slider"></label>
                        </div>
                    </div>
                    <p class="cookie-category__description">
                        Estas cookies <strong>mejoran significativamente tu experiencia de usuario</strong> al recordar tus preferencias y comportamiento de navegación:
                        filtros aplicados en el catálogo de cafés, productos vistos recientemente para recomendaciones personalizadas,
                        preferencias dietéticas y alergias para pre-rellenar formularios de reserva, y control de popups (newsletter) para evitar repeticiones molestas.
                        Toda la información es almacenada <strong>localmente en tu navegador</strong> y nunca se comparte con terceros.
                    </p>
                    <p class="cookie-category__examples">
                        <strong>Duración:</strong> Entre 30 y 180 días según tipo<br>
                        <strong>Cookies:</strong> <code>filter_preferences</code> (90d), <code>recently_viewed</code> (30d),
                        <code>dietary_preferences</code> (180d), <code>newsletter_prompted</code> (180d)
                    </p>
                </div>

                <!-- Cookies de Análisis (futuro) -->
                <div class="cookie-category">
                    <div class="cookie-category__header">
                        <h3 class="cookie-category__title">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                                <rect x="2" y="13" width="3" height="5" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                                <rect x="8" y="8" width="3" height="10" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                                <rect x="14" y="3" width="3" height="15" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                            </svg>
                            <span>Cookies de Análisis y Rendimiento</span>
                        </h3>
                        <div class="cookie-category__toggle">
                            <input
                                type="checkbox"
                                class="cookie-category__checkbox"
                                x-model="preferences.analytics"
                                id="cookie-analytics"
                                disabled>
                            <label for="cookie-analytics" class="cookie-category__slider"></label>
                        </div>
                    </div>
                    <p class="cookie-category__description">
                        Estas cookies nos permitirían comprender cómo interactúas con el sitio web para optimizar la experiencia del usuario:
                        páginas más visitadas, tiempo de permanencia, tasa de rebote, flujos de navegación y detección de errores técnicos.
                        Los datos recopilados son <strong>anónimos y agregados</strong>, sin identificación personal.
                        <strong>Esta funcionalidad está actualmente deshabilitada</strong> y se implementará en futuras versiones.
                    </p>
                    <p class="cookie-category__examples">
                        <strong>Estado:</strong> No implementado<br>
                        Ejemplos: Google Analytics (no implementado)
                    </p>
                </div>
            </div>

            <div class="cookie-modal__footer">
                <button
                    @click="closeModal"
                    class="cookie-banner__btn cookie-banner__btn--reject"
                    type="button">
                    Cancelar
                </button>
                <button
                    @click="saveCustom"
                    class="cookie-banner__btn cookie-banner__btn--accept"
                    type="button">
                    Guardar preferencias
                </button>
            </div>
        </div>
    </div>
</div>
