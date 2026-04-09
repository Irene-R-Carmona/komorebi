<?php
/**
 * Partial: Tab General - Configuración básica del sitio
 */
?>

<div class="settings-section">
    <div class="settings-section__header">
        <h2 class="settings-section__title">
            <i class="bi bi-info-circle"></i>
            Información del Sitio
        </h2>
    </div>
    <div class="settings-section__body">
        <!-- Maintenance Mode Alert -->
        <div x-show="settings.maintenance_mode" class="maintenance-alert">
            <i class="bi bi-exclamation-triangle-fill maintenance-alert__icon"></i>
            <div class="maintenance-alert__content">
                <div class="maintenance-alert__title">Modo Mantenimiento Activo</div>
                <p class="maintenance-alert__text">
                    El sitio está inaccesible para usuarios públicos. Solo administradores pueden acceder.
                </p>
            </div>
        </div>

        <div class="row g-3">
            <!-- Site Name -->
            <div class="col-md-6">
                <label class="form-label" for="siteName">Nombre del Sitio</label>
                <input
                    type="text"
                    class="form-control"
                    id="siteName"
                    x-model="settings.site_name"
                    maxlength="100"
                    placeholder="Komorebi Café"
                >
                <p class="form-hint">Nombre público de la aplicación</p>
            </div>

            <!-- Timezone -->
            <div class="col-md-6">
                <label class="form-label" for="timezone">Zona Horaria</label>
                <select class="form-select timezone-select" id="timezone" x-model="settings.timezone">
                    <option value="Europe/Madrid">Europe/Madrid (GMT+1/+2)</option>
                    <option value="America/Mexico_City">America/Mexico City (GMT-6)</option>
                    <option value="America/New_York">America/New York (GMT-5/-4)</option>
                    <option value="America/Los_Angeles">America/Los Angeles (GMT-8/-7)</option>
                    <option value="Asia/Tokyo">Asia/Tokyo (GMT+9)</option>
                    <option value="UTC">UTC</option>
                </select>
            </div>

            <!-- Description -->
            <div class="col-12">
                <label class="form-label" for="siteDescription">Descripción</label>
                <textarea
                    class="form-control"
                    id="siteDescription"
                    x-model="settings.site_description"
                    rows="3"
                    maxlength="500"
                    placeholder="Descripción para meta tags y SEO..."
                ></textarea>
            </div>

            <!-- Language -->
            <div class="col-md-6">
                <label class="form-label" for="defaultLanguage">Idioma por Defecto</label>
                <select class="form-select" id="defaultLanguage" x-model="settings.default_language">
                    <option value="es">🇪🇸 Español</option>
                    <option value="en">🇬🇧 English</option>
                    <option value="ja">🇯🇵 日本語</option>
                </select>
            </div>

            <!-- Items per page -->
            <div class="col-md-6">
                <label class="form-label" for="itemsPerPage">Items por Página</label>
                <input
                    type="number"
                    class="form-control"
                    id="itemsPerPage"
                    x-model.number="settings.items_per_page"
                    min="5"
                    max="100"
                >
                <p class="form-hint">Elementos mostrados en listados</p>
            </div>

            <!-- Maintenance Mode -->
            <div class="col-12">
                <div class="form-check form-switch form-switch-lg">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="maintenanceMode"
                        x-model="settings.maintenance_mode"
                        @change="confirmMaintenanceMode($event)"
                    >
                    <label class="form-check-label" for="maintenanceMode">
                        <strong>Modo Mantenimiento</strong>
                        <span class="d-block text-muted small">
                            Desactiva el acceso público al sitio
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button
                type="button"
                class="btn btn-outline-secondary"
                @click="resetGroup('general')"
                :disabled="saving"
            >
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Restaurar
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveGroup('general')"
                :disabled="saving"
            >
                <span x-show="!saving">
                    <i class="bi bi-save me-1"></i>
                    Guardar Cambios
                </span>
                <span x-show="saving">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Guardando...
                </span>
            </button>
        </div>
    </div>
</div>