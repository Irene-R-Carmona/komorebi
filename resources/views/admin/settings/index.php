<?php

/**
 * Vista: Configuración del Sistema
 * Ruta: GET /admin/settings
 */

use App\Core\View;

// Opcional: determinar tab activo inicial desde URL
$activeTab = $_GET['tab'] ?? 'general';
?>

<div class="container-fluid py-4" x-data="settingsManagement({ activeTab: '<?= e($activeTab) ?>' })" x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'gear',
        'title' => 'Configuración',
        'subtitle' => 'Ajusta el comportamiento del sistema',
    ]) ?>

    <!-- Loading -->
    <div x-show="loading" class="text-center py-5">
        <output class="spinner-border text-primary" aria-busy="true">
            <span class="visually-hidden">Cargando...</span>
        </output>
        <p class="text-muted mt-2">Cargando configuración...</p>
    </div>

    <!-- Content -->
    <div x-show="!loading" x-cloak>

        <!-- Tabs -->
        <div class="settings-tabs">
            <button
                type="button"
                class="settings-tab"
                :class="{ 'settings-tab--active': activeTab === 'general' }"
                @click="activeTab = 'general'">
                <i class="bi bi-gear settings-tab__icon"></i>
                <span>General</span>
            </button>

            <button
                type="button"
                class="settings-tab"
                :class="{ 'settings-tab--active': activeTab === 'email' }"
                @click="activeTab = 'email'">
                <i class="bi bi-envelope settings-tab__icon"></i>
                <span>Email</span>
            </button>

            <button
                type="button"
                class="settings-tab"
                :class="{ 'settings-tab--active': activeTab === 'reservations' }"
                @click="activeTab = 'reservations'">
                <i class="bi bi-calendar-check settings-tab__icon"></i>
                <span>Reservas</span>
            </button>

            <button
                type="button"
                class="settings-tab"
                :class="{ 'settings-tab--active': activeTab === 'security' }"
                @click="activeTab = 'security'">
                <i class="bi bi-shield-lock settings-tab__icon"></i>
                <span>Seguridad</span>
            </button>
        </div>

        <!-- Tab Contents -->
        <div class="settings-form">
            <!-- General -->
            <div x-show="activeTab === 'general'" x-transition.opacity>
                <?php include __DIR__ . '/partials/_tab-general.php'; ?>
            </div>

            <!-- Email -->
            <div x-show="activeTab === 'email'" x-transition.opacity>
                <?php include __DIR__ . '/partials/_tab-email.php'; ?>
            </div>

            <!-- Reservations -->
            <div x-show="activeTab === 'reservations'" x-transition.opacity>
                <?php include __DIR__ . '/partials/_tab-reservations.php'; ?>
            </div>

            <!-- Security -->
            <div x-show="activeTab === 'security'" x-transition.opacity>
                <?php include __DIR__ . '/partials/_tab-security.php'; ?>
            </div>
        </div>
    </div>
</div>
