<?php

declare(strict_types=1);

/**
 * PANEL DE PREFERENCIAS DE COOKIES
 * Componente para gestionar consentimiento desde el perfil del usuario
 *
 * Uso: <?php include __DIR__ . '/../components/cookie-preferences-panel.php'; ?>
 */

use App\Core\CookieManager;

$cookieManager = new CookieManager();
$consentJson = $cookieManager->get(CookieManager::COOKIE_CONSENT, '{}');
$consent = json_decode($consentJson, true) ?: [
    'essential' => true,
    'functional' => false,
    'analytics' => false,
];
?>

<div class="profile-section" x-data="cookiePreferences(<?= htmlspecialchars(json_encode($consent), ENT_QUOTES, 'UTF-8') ?>)">
    <div class="profile-section__header">
        <h3 class="profile-section__title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none" />
                <circle cx="8" cy="10" r="1.5" fill="currentColor" />
                <circle cx="16" cy="9" r="1" fill="currentColor" />
                <circle cx="9" cy="15" r="1" fill="currentColor" />
                <circle cx="16" cy="14" r="1.5" fill="currentColor" />
            </svg>
            Gestión de cookies y privacidad
        </h3>
        <p class="profile-section__subtitle">
            Controla qué cookies utiliza Komorebi Café en tu navegador. Puedes modificar estas preferencias en cualquier momento.
            Los cambios se aplicarán inmediatamente en todas las páginas del sitio.
            <a href="/legal/cookies" target="_blank" class="profile-section__link">Política completa de cookies</a>
        </p>
    </div>

    <div class="profile-section__body">
        <!-- Cookies Esenciales -->
        <div class="cookie-pref-item">
            <div class="cookie-pref-item__header">
                <div class="cookie-pref-item__info">
                    <h4 class="cookie-pref-item__title">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                            <rect x="3" y="8" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5" fill="none" />
                            <path d="M6 8V5C6 3.34315 7.34315 2 9 2H11C12.6569 2 14 3.34315 14 5V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            <circle cx="10" cy="12" r="1" fill="currentColor" />
                        </svg>
                        Cookies Esenciales y de Seguridad
                        <span class="cookie-pref-item__badge cookie-pref-item__badge--required">REQUERIDAS</span>
                    </h4>
                    <p class="cookie-pref-item__description">
                        Cookies estrictamente necesarias para el funcionamiento, seguridad y autenticación del sitio web.
                        Incluyen gestión de sesión, protección CSRF y almacenamiento de consentimiento.
                        <strong>No pueden desactivarse</strong> sin afectar la funcionalidad básica del sitio.
                    </p>
                </div>
                <div class="cookie-pref-item__toggle">
                    <input
                        type="checkbox"
                        class="toggle-switch"
                        checked
                        disabled
                        id="cookie-essential-profile">
                    <label for="cookie-essential-profile" class="toggle-label"></label>
                </div>
            </div>
        </div>

        <!-- Cookies Funcionales -->
        <div class="cookie-pref-item">
            <div class="cookie-pref-item__header">
                <div class="cookie-pref-item__info">
                    <h4 class="cookie-pref-item__title">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                            <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5" fill="none" />
                            <path d="M10 6V10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M16 4L18 2M4 16L2 18M16 16L18 18M4 4L2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        Cookies Funcionales y de Experiencia
                    </h4>
                    <p class="cookie-pref-item__description">
                        Mejoran tu experiencia recordando preferencias: filtros de búsqueda en el catálogo,
                        productos vistos recientemente, preferencias dietéticas/alergias para formularios,
                        y control de popups. Toda la información se almacena localmente y no se comparte con terceros.
                    </p>
                </div>
                <div class="cookie-pref-item__toggle">
                    <input
                        type="checkbox"
                        class="toggle-switch"
                        x-model="preferences.functional"
                        @change="onToggle"
                        id="cookie-functional-profile">
                    <label for="cookie-functional-profile" class="toggle-label"></label>
                </div>
            </div>

            <div class="cookie-pref-item__details" x-show="preferences.functional">
                <p class="cookie-pref-item__detail-text">
                    <strong>Cookies almacenadas:</strong>
                </p>
                <ul class="cookie-pref-item__list">
                    <li><code>filter_preferences</code> - Filtros aplicados en catálogos (duración: 90 días)</li>
                    <li><code>recently_viewed</code> - Últimos productos visitados, máximo 10 (duración: 30 días)</li>
                    <li><code>dietary_preferences</code> - Alergias y preferencias dietéticas (duración: 180 días)</li>
                    <li><code>newsletter_prompted</code> - Control de visualización de popup newsletter (duración: 180 días)</li>
                </ul>
            </div>
        </div>

        <!-- Cookies de Análisis (futuro) -->
        <div class="cookie-pref-item cookie-pref-item--disabled">
            <div class="cookie-pref-item__header">
                <div class="cookie-pref-item__info">
                    <h4 class="cookie-pref-item__title">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px; vertical-align: middle;">
                            <rect x="2" y="13" width="3" height="5" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                            <rect x="8" y="8" width="3" height="10" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                            <rect x="14" y="3" width="3" height="15" rx="1" stroke="currentColor" stroke-width="1.5" fill="none" />
                        </svg>
                        Cookies de Análisis y Rendimiento
                        <span class="cookie-pref-item__badge cookie-pref-item__badge--soon">PRÓXIMAMENTE</span>
                    </h4>
                    <p class="cookie-pref-item__description">
                        Nos ayudan a entender cómo usas el sitio para mejorarlo.
                        Actualmente no implementadas.
                    </p>
                </div>
                <div class="cookie-pref-item__toggle">
                    <input
                        type="checkbox"
                        class="toggle-switch"
                        disabled
                        id="cookie-analytics-profile">
                    <label for="cookie-analytics-profile" class="toggle-label"></label>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="cookie-pref-actions">
            <button
                @click="deleteAllCookies"
                class="btn btn--outline btn--danger"
                type="button">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                    <path d="M2 4H14M6 2H10M6 7V12M10 7V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M3 4L4 14H12L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Eliminar todas las cookies funcionales
            </button>

            <div class="cookie-pref-actions__info" x-show="showSaved">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px; vertical-align: middle;">
                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none" />
                    <path d="M5 8L7 10L11 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Preferencias guardadas correctamente
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos específicos del panel de cookies */
    .cookie-pref-item {
        background: var(--color-fondo-alt);
        border-radius: var(--radio-md);
        padding: 1.5rem;
        margin-bottom: 1rem;
    }

    .cookie-pref-item--disabled {
        opacity: 0.6;
    }

    .cookie-pref-item__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }

    .cookie-pref-item__info {
        flex: 1;
    }

    .cookie-pref-item__title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-primario);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .cookie-pref-item__icon {
        font-size: 1.25rem;
    }

    .cookie-pref-item__badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: var(--radio-sm);
        font-weight: 600;
    }

    .cookie-pref-item__badge--required {
        background: var(--color-primario);
        color: white;
    }

    .cookie-pref-item__badge--soon {
        background: var(--color-texto-suave);
        color: white;
    }

    .cookie-pref-item__description {
        font-size: 0.9rem;
        color: var(--color-texto);
        line-height: 1.5;
        margin: 0;
    }

    .cookie-pref-item__toggle {
        position: relative;
        width: 50px;
        height: 28px;
        flex-shrink: 0;
    }

    .toggle-switch {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-label {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #ccc;
        border-radius: 28px;
        transition: 0.3s;
    }

    .toggle-label::before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background: white;
        border-radius: 50%;
        transition: 0.3s;
    }

    .toggle-switch:checked+.toggle-label {
        background: var(--color-acento);
    }

    .toggle-switch:checked+.toggle-label::before {
        transform: translateX(22px);
    }

    .toggle-switch:disabled+.toggle-label {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .cookie-pref-item__details {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--color-borde);
    }

    .cookie-pref-item__detail-text {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .cookie-pref-item__list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .cookie-pref-item__list li {
        font-size: 0.85rem;
        color: var(--color-texto);
        padding: 0.25rem 0;
    }

    .cookie-pref-item__list code {
        background: white;
        padding: 0.125rem 0.375rem;
        border-radius: 3px;
        font-size: 0.8rem;
        color: var(--color-acento);
    }

    .cookie-pref-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--color-borde);
    }

    .cookie-pref-actions__info {
        color: var(--color-exito);
        font-size: 0.9rem;
        font-weight: 600;
    }

    .btn--outline {
        background: transparent;
        border: 2px solid var(--color-borde);
    }

    .btn--danger {
        color: var(--color-error);
        border-color: var(--color-error);
    }

    .btn--danger:hover {
        background: var(--color-error);
        color: white;
    }

    @media (max-width: 768px) {
        .cookie-pref-item__header {
            flex-direction: column;
        }

        .cookie-pref-item__toggle {
            align-self: flex-end;
        }

        .cookie-pref-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
</style>
