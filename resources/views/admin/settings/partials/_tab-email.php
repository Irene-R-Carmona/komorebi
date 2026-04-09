<?php

/**
 * Partial: Tab Email - Configuración SMTP
 */
?>

<div class="settings-section">
    <div class="settings-section__header">
        <h2 class="settings-section__title">
            <i class="bi bi-envelope"></i>
            Configuración de Email
        </h2>
    </div>
    <div class="settings-section__body">
        <!-- Enable SMTP -->
        <div class="form-check form-switch form-switch-lg mb-4">
            <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="smtpEnabled"
                x-model="settings.smtp_enabled">
            <label class="form-check-label" for="smtpEnabled">
                <strong>Activar Envío de Emails</strong>
                <span class="d-block text-muted small">
                    Habilita el envío de notificaciones por correo
                </span>
            </label>
        </div>

        <div class="row g-3" x-show="settings.smtp_enabled" x-transition>
            <!-- SMTP Host -->
            <div class="col-md-6">
                <label class="form-label" for="smtpHost">Servidor SMTP</label>
                <input
                    type="text"
                    class="form-control"
                    id="smtpHost"
                    x-model="settings.smtp_host"
                    placeholder="smtp.gmail.com">
            </div>

            <!-- SMTP Port -->
            <div class="col-md-3">
                <label class="form-label" for="smtpPort">Puerto</label>
                <input
                    type="number"
                    class="form-control"
                    id="smtpPort"
                    x-model.number="settings.smtp_port"
                    placeholder="587">
            </div>

            <!-- Encryption -->
            <div class="col-md-3">
                <label class="form-label" for="smtpEncryption">Encriptación</label>
                <select class="form-select" id="smtpEncryption" x-model="settings.smtp_encryption">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">Ninguna</option>
                </select>
            </div>

            <!-- Nombre de usuario -->
            <div class="col-md-6">
                <label class="form-label" for="smtpUsername">Usuario SMTP</label>
                <input
                    type="text"
                    class="form-control"
                    id="smtpUsername"
                    x-model="settings.smtp_username"
                    autocomplete="off">
            </div>

            <!-- Password -->
            <div class="col-md-6">
                <label class="form-label" for="smtpPassword">Contraseña SMTP</label>
                <div class="input-group">
                    <input
                        :type="showSmtpPassword ? 'text' : 'password'"
                        class="form-control"
                        id="smtpPassword"
                        x-model="settings.smtp_password"
                        placeholder="••••••••"
                        autocomplete="new-password">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="showSmtpPassword = !showSmtpPassword">
                        <i class="bi" :class="showSmtpPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                </div>
                <p class="form-hint">Se almacena de forma segura</p>
            </div>

            <!-- From Email -->
            <div class="col-md-6">
                <label class="form-label" for="fromEmail">Email Remitente</label>
                <input
                    type="email"
                    class="form-control"
                    id="fromEmail"
                    x-model="settings.from_email"
                    placeholder="noreply@komorebi.cafe">
            </div>

            <!-- From Name -->
            <div class="col-md-6">
                <label class="form-label" for="fromName">Nombre Remitente</label>
                <input
                    type="text"
                    class="form-control"
                    id="fromName"
                    x-model="settings.from_name"
                    placeholder="Komorebi Café">
            </div>

            <!-- Support Email -->
            <div class="col-md-6">
                <label class="form-label" for="supportEmail">Email de Soporte</label>
                <input
                    type="email"
                    class="form-control"
                    id="supportEmail"
                    x-model="settings.support_email"
                    placeholder="soporte@komorebi.cafe">
                <p class="form-hint">Email visible públicamente para contacto</p>
            </div>
        </div>

        <!-- SMTP Test -->
        <div class="smtp-test" x-show="settings.smtp_enabled" x-transition>
            <div class="smtp-test__status">
                <i class="bi bi-envelope-check smtp-test__status-icon--pending"></i>
                <span>Probar configuración SMTP</span>
            </div>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                @click="testEmail"
                :disabled="testingEmail">
                <span x-show="!testingEmail">
                    <i class="bi bi-send me-1"></i>
                    Enviar Email de Prueba
                </span>
                <span x-show="testingEmail">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Enviando...
                </span>
            </button>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button
                type="button"
                class="btn btn-outline-secondary"
                @click="resetGroup('email')"
                :disabled="saving">
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Restaurar
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveGroup('email')"
                :disabled="saving">
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
