<!-- Componente de Loading States y Skeleton Loaders -->
<!-- Uso: Include en vistas que necesiten feedback visual de carga -->

<!-- Skeleton Loader para Card de Producto -->
<template x-if="loading" x-cloak>
    <div class="skeleton-card">
        <div class="skeleton-card__image"></div>
        <div class="skeleton-card__content">
            <div class="skeleton-line skeleton-line--title"></div>
            <div class="skeleton-line skeleton-line--text"></div>
            <div class="skeleton-line skeleton-line--text skeleton-line--short"></div>
            <div class="skeleton-line skeleton-line--price"></div>
        </div>
    </div>
    </style>
    }

    .skeleton-card__image {
    width: 100%;
    height: 200px;
    background: linear-gradient(90deg,
    var(--color-fondo) 0%,
    var(--color-fondo-alt) 50%,
    var(--color-fondo) 100%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s ease-in-out infinite;
    }

    .skeleton-card__content {
    padding: var(--espaciado-md);
    display: flex;
    flex-direction: column;
    gap: var(--espaciado-sm);
    }

    .skeleton-line {
    height: 1rem;
    background: linear-gradient(90deg,
    var(--color-fondo) 0%,
    var(--color-fondo-alt) 50%,
    var(--color-fondo) 100%);
    background-size: 200% 100%;
    border-radius: var(--radio-sm);
    animation: skeleton-loading 1.5s ease-in-out infinite;
    }

    .skeleton-line--title {
    height: 1.5rem;
    width: 70%;
    }

    .skeleton-line--text {
    height: 1rem;
    width: 100%;
    }

    .skeleton-line--short {
    width: 60%;
    }

    .skeleton-line--price {
    height: 1.25rem;
    width: 40%;
    margin-top: var(--espaciado-sm);
    }

    @keyframes skeleton-loading {
    0% {
    background-position: 200% 0;
    }

    100% {
    background-position: -200% 0;
    }
    }

    /* Skeleton Table */
    .skeleton-table {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    }

    .skeleton-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    padding: 1rem;
    background: var(--color-superficie);
    border-radius: var(--radio-sm);
    }

    .skeleton-cell {
    display: flex;
    align-items: center;
    }

    /* ========================================
    SPINNERS
    ======================================== */

    .spinner {
    width: 1.25rem;
    height: 1.25rem;
    animation: spinner-rotate 2s linear infinite;
    }

    .spinner--large {
    width: 3rem;
    height: 3rem;
    }

    .spinner__path {
    stroke: currentColor;
    stroke-linecap: round;
    stroke-dasharray: 1, 200;
    stroke-dashoffset: 0;
    animation: spinner-dash 1.5s ease-in-out infinite;
    }

    @keyframes spinner-rotate {
    100% {
    transform: rotate(360deg);
    }
    }

    @keyframes spinner-dash {
    0% {
    stroke-dasharray: 1, 200;
    stroke-dashoffset: 0;
    }

    50% {
    stroke-dasharray: 90, 200;
    stroke-dashoffset: -35px;
    }

    100% {
    stroke-dashoffset: -125px;
    }
    }

    /* ========================================
    LOADING OVERLAY
    ======================================== */

    .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
    }

    .loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--espaciado-md);
    padding: var(--espaciado-lg);
    background: var(--color-superficie);
    border-radius: var(--radio-lg);
    box-shadow: var(--sombra-lg);
    }

    .loading-text {
    margin: 0;
    color: var(--color-texto);
    font-weight: 500;
    }

    /* ========================================
    BOTONES CON LOADING
    ======================================== */

    .btn--loading {
    position: relative;
    pointer-events: none;
    }

    .btn--loading .btn__text {
    opacity: 0;
    }

    .btn--loading .spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: currentColor;
    }

    /* ========================================
    UTILIDADES ALPINE.JS
    ======================================== */

    [x-cloak] {
    display: none !important;
    }

    /* ========================================
    IMAGEN LAZY LOADING
    ======================================== */

    img[loading="lazy"] {
    background: linear-gradient(90deg,
    var(--color-fondo) 0%,
    var(--color-fondo-alt) 50%,
    var(--color-fondo) 100%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s ease-in-out infinite;
    }

    img[loading="lazy"].loaded {
    animation: none;
    background: none;
    }

    /* ========================================
    RESPONSIVE
    ======================================== */

    @media (max-width: 768px) {
    .skeleton-row {
    grid-template-columns: 1fr;
    }

    .loading-spinner {
    margin: var(--espaciado-md);
    }
    }
    </style>
