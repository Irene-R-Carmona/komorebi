<?php

declare(strict_types=1);

/**
 * Newsletter Popup Component
 *
 * BUG-13 FIX: Deshabilitar popup en rutas backoffice y login
 * para evitar bloqueo de interacciones críticas.
 */

// Detectar si estamos en rutas backoffice o login
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$isBackofficeRoute = false;
foreach (\App\Core\Middleware::BACKOFFICE_URL_PREFIXES as $prefix) {
    if (str_starts_with($currentPath, $prefix)) {
        $isBackofficeRoute = true;
        break;
    }
}

// No renderizar popup en rutas backoffice
if ($isBackofficeRoute) {
    return;
}
?>

<!-- Newsletter Popup Modal -->
<div x-data="newsletterPopup()"
    x-show="showPopup"
    x-cloak
    @keydown.escape.window="closePopup(false)"
    class="newsletter-popup-overlay"
    style="display: none;">

    <div class="newsletter-popup-modal"
        @click.away="closePopup(false)"
        x-transition:enter="newsletter-popup-enter"
        x-transition:enter-start="newsletter-popup-enter-start"
        x-transition:enter-end="newsletter-popup-enter-end"
        x-transition:leave="newsletter-popup-leave"
        x-transition:leave-start="newsletter-popup-leave-start"
        x-transition:leave-end="newsletter-popup-leave-end">

        <!-- Botón cerrar -->
        <button type="button"
            class="newsletter-popup__close"
            @click="closePopup(false)"
            aria-label="Cerrar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>

        <!-- Icono de newsletter -->
        <div class="newsletter-popup__icon">
            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="8" y="12" width="48" height="36" rx="4" stroke="currentColor" stroke-width="2" fill="none" />
                <path d="M8 20L32 36L56 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M8 16L32 28L56 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.5" />
            </svg>
        </div>

        <!-- Contenido -->
        <div class="newsletter-popup__content">
            <h3 class="newsletter-popup__title">Únete a la comunidad Komorebi</h3>
            <p class="newsletter-popup__description">
                Recibe nuestras novedades sobre cafés de especialidad, eventos exclusivos
                y ofertas especiales directamente en tu correo.
            </p>

            <!-- Formulario -->
            <form class="newsletter-popup__form" @submit.prevent="subscribe()">
                <div class="newsletter-popup__input-group">
                    <label for="newsletter-email" class="sr-only">Tu correo electrónico</label>
                    <input
                        type="email"
                        id="newsletter-email"
                        x-model="email"
                        placeholder="tu@email.com"
                        required
                        class="newsletter-popup__input">
                    <button type="submit"
                        class="newsletter-popup__submit"
                        :disabled="loading">
                        <span x-show="!loading">Suscribirme</span>
                        <span x-show="loading" x-cloak>
                            <svg class="spinner" width="20" height="20" viewBox="0 0 20 20">
                                <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" opacity="0.25" />
                                <path d="M10 2a8 8 0 0 1 8 8" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" />
                            </svg>
                        </span>
                    </button>
                </div>

                <!-- Mensaje de éxito/error -->
                <div x-show="message" x-cloak class="newsletter-popup__message" :class="messageType">
                    <svg x-show="messageType === 'success'" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" />
                    </svg>
                    <svg x-show="messageType === 'error'" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                        <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z" />
                    </svg>
                    <span x-text="message"></span>
                </div>
            </form>

            <!-- Botón "No volver a mostrar" -->
            <button type="button"
                class="newsletter-popup__dismiss"
                @click="closePopup(true)">
                No volver a mostrar este mensaje
            </button>
        </div>
    </div>
</div>

<style>
    /* Overlay */
    .newsletter-popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 1rem;
    }

    /* Modal */
    .newsletter-popup-modal {
        position: relative;
        background: white;
        border-radius: 16px;
        max-width: 500px;
        width: 100%;
        padding: 2.5rem 2rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    /* Botón cerrar */
    .newsletter-popup__close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        color: var(--color-texto-suave, #666);
        cursor: pointer;
        border-radius: 50%;
        transition: all 0.2s;
    }

    .newsletter-popup__close:hover {
        background: var(--color-fondo-suave, #f5f5f5);
        color: var(--color-texto, #2c2c2c);
    }

    /* Icono principal */
    .newsletter-popup__icon {
        display: flex;
        justify-content: center;
        margin-bottom: 1.5rem;
        color: var(--color-acento, #c9a959);
    }

    /* Contenido */
    .newsletter-popup__content {
        text-align: center;
    }

    .newsletter-popup__title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-primario, #8b4513);
        margin: 0 0 1rem 0;
    }

    .newsletter-popup__description {
        font-size: 1rem;
        line-height: 1.6;
        color: var(--color-texto-suave, #666);
        margin: 0 0 2rem 0;
    }

    /* Formulario */
    .newsletter-popup__form {
        margin-bottom: 1.5rem;
    }

    .newsletter-popup__input-group {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .newsletter-popup__input {
        flex: 1;
        padding: 0.875rem 1rem;
        border: 2px solid var(--color-borde, #ddd);
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.2s;
    }

    .newsletter-popup__input:focus {
        outline: none;
        border-color: var(--color-acento, #c9a959);
        box-shadow: 0 0 0 3px rgba(201, 169, 89, 0.1);
    }

    .newsletter-popup__submit {
        padding: 0.875rem 1.5rem;
        background: var(--color-acento, #c9a959);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 140px;
    }

    .newsletter-popup__submit:hover:not(:disabled) {
        background: var(--color-primario, #8b4513);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(201, 169, 89, 0.3);
    }

    .newsletter-popup__submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Mensaje de estado */
    .newsletter-popup__message {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        margin-top: 1rem;
    }

    .newsletter-popup__message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .newsletter-popup__message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .newsletter-popup__message svg {
        flex-shrink: 0;
    }

    /* Botón dismiss */
    .newsletter-popup__dismiss {
        width: 100%;
        padding: 0.75rem;
        background: transparent;
        border: 1px solid var(--color-borde, #ddd);
        border-radius: 8px;
        color: var(--color-texto-suave, #666);
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .newsletter-popup__dismiss:hover {
        background: var(--color-fondo-suave, #f5f5f5);
        border-color: var(--color-texto-suave, #666);
    }

    /* Spinner */
    .spinner {
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Animaciones */
    .newsletter-popup-enter {
        transition: opacity 0.3s ease-out;
    }

    .newsletter-popup-enter-start {
        opacity: 0;
    }

    .newsletter-popup-enter-end {
        opacity: 1;
    }

    .newsletter-popup-leave {
        transition: opacity 0.2s ease-in;
    }

    .newsletter-popup-leave-start {
        opacity: 1;
    }

    .newsletter-popup-leave-end {
        opacity: 0;
    }

    /* Responsive */
    @media (max-width: 600px) {
        .newsletter-popup-modal {
            padding: 2rem 1.5rem;
        }

        .newsletter-popup__title {
            font-size: 1.5rem;
        }

        .newsletter-popup__input-group {
            flex-direction: column;
        }

        .newsletter-popup__submit {
            width: 100%;
        }
    }

    /* Screen reader only */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>
