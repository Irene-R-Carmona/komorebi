<?php

/**
 * Partial: Tab Seguridad - Configuración de seguridad y autenticación
 */
?>

<div class="settings-section">
    <div class="settings-section__header">
        <h2 class="settings-section__title">
            <i class="bi bi-shield-lock"></i>
            Configuración de Seguridad
        </h2>
    </div>
    <div class="settings-section__body">
        <div class="row g-3">
            <!-- Session Lifetime -->
            <div class="col-md-6">
                <label class="form-label" for="sessionLifetime">
                    Duración de Sesión
                </label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="sessionLifetime"
                        x-model.number="settings.session_lifetime"
                        min="15"
                        max="480"
                        step="15">
                    <div class="range-input__value">
                        <span x-text="sessionHours"></span>
                        <span class="range-input__unit">horas</span>
                    </div>
                </div>
                <p class="form-hint">Tiempo antes de que la sesión expire por inactividad</p>
            </div>

            <!-- Max Login Attempts -->
            <div class="col-md-6">
                <label class="form-label" for="maxLoginAttempts">Intentos de Login</label>
                <input
                    type="number"
                    class="form-control"
                    id="maxLoginAttempts"
                    x-model.number="settings.max_login_attempts"
                    min="3"
                    max="10">
                <p class="form-hint">Intentos fallidos antes de bloquear la cuenta</p>
            </div>

            <!-- Lockout Duration -->
            <div class="col-md-6">
                <label class="form-label" for="lockoutDuration">Duración del Bloqueo</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="lockoutDuration"
                        x-model.number="settings.lockout_duration"
                        min="5"
                        max="120"
                        step="5">
                    <div class="range-input__value">
                        <span x-text="settings.lockout_duration"></span>
                        <span class="range-input__unit">min</span>
                    </div>
                </div>
            </div>

            <!-- Password Min Length -->
            <div class="col-md-6">
                <label class="form-label" for="passwordMinLength">Longitud Mínima de Contraseña</label>
                <div class="range-input">
                    <input
                        type="range"
                        class="form-range range-input__slider"
                        id="passwordMinLength"
                        x-model.number="settings.password_min_length"
                        min="6"
                        max="32">
                    <div class="range-input__value">
                        <span x-text="settings.password_min_length"></span>
                        <span class="range-input__unit">chars</span>
                    </div>
                </div>
            </div>

            <!-- Email Verification -->
            <div class="col-md-6">
                <div class="form-check form-switch form-switch-lg">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="requireEmailVerification"
                        x-model="settings.require_email_verification">
                    <label class="form-check-label" for="requireEmailVerification">
                        <strong>Verificación de Email</strong>
                        <span class="d-block text-muted small">
                            Requerir confirmación de email al registrarse
                        </span>
                    </label>
                </div>
            </div>

            <!-- Password Special Chars -->
            <div class="col-md-6">
                <div class="form-check form-switch form-switch-lg">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="passwordRequireSpecial"
                        x-model="settings.password_require_special">
                    <label class="form-check-label" for="passwordRequireSpecial">
                        <strong>Caracteres Especiales</strong>
                        <span class="d-block text-muted small">
                            Requerir símbolos en contraseñas (!@#$%^&*)
                        </span>
                    </label>
                </div>
            </div>

            <!-- 2FA -->
            <div class="col-12">
                <div class="form-check form-switch form-switch-lg">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="enable2fa"
                        x-model="settings.enable_2fa">
                    <label class="form-check-label" for="enable2fa">
                        <strong>Autenticación de Dos Factores (2FA)</strong>
                        <span class="d-block text-muted small">
                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Experimental - Requiere configuraci&oacute;n adicional de servidor
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Warning -->
        <div class="alert alert-warning mt-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Precaución:</strong> Los cambios en seguridad pueden afectar sesiones activas.
            Los usuarios podrían necesitar iniciar sesión nuevamente.
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button
                type="button"
                class="btn btn-outline-secondary"
                @click="resetGroup('security')"
                :disabled="saving">
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Restaurar
            </button>
            <button
                type="button"
                class="btn btn-primary"
                @click="saveGroup('security')"
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
