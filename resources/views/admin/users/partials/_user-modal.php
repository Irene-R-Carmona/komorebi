<?php

/**
 * Partial: Modal de usuario (crear/editar)
 */

$roles ??= [];
?>

<div
    class="modal fade"
    id="userModal"
    tabindex="-1"
    aria-labelledby="userModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form @submit.prevent="submitUser">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="bi me-2" :class="isEditMode ? 'bi-pencil' : 'bi-person-plus'"></i>
                        <span x-text="isEditMode ? 'Editar Usuario' : 'Nuevo Usuario'"></span>
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <!-- Errores -->
                    <template x-if="formErrors.length > 0">
                        <div class="alert alert-danger py-2 mb-3">
                            <ul class="mb-0 ps-3">
                                <template x-for="error in formErrors" :key="error">
                                    <li x-text="error" class="small"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <!-- Avatar Preview -->
                    <div class="user-modal__avatar-preview">
                        <div class="user-avatar user-avatar--lg" :class="getAvatarClass([])">
                            <span x-text="getInitial(form.name || 'U')"></span>
                        </div>
                    </div>

                    <!-- Información básica -->
                    <div class="user-modal__section">
                        <h6 class="user-modal__section-title">
                            <i class="bi bi-person"></i>
                            Información básica
                        </h6>

                        <!-- Nombre -->
                        <div class="mb-3">
                            <label class="form-label form-label-required" for="userName">
                                Nombre completo
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="userName"
                                x-model="form.name"
                                required
                                autocomplete="name"
                                maxlength="100"
                                placeholder="Ej: María García">
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label form-label-required" for="userEmail">
                                Email
                            </label>
                            <input
                                type="email"
                                class="form-control"
                                id="userEmail"
                                x-model="form.email"
                                required
                                autocomplete="email"
                                placeholder="usuario@ejemplo.com">
                        </div>
                    </div>

                    <!-- Seguridad -->
                    <div class="user-modal__section" x-data="{ expanded: !isEditMode }">
                        <h6 class="user-modal__section-title" @click="expanded = !expanded" style="cursor: pointer; user-select: none;">
                            <i class="bi" :class="expanded ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                            <i class="bi bi-shield-lock ms-1"></i>
                            Seguridad
                            <span x-show="isEditMode" class="text-muted fw-normal ms-2">(opcional, click para expandir)</span>
                        </h6>
                        <div x-show="expanded" x-collapse>

                            <!-- Password -->
                            <div class="mb-3">
                                <label class="form-label" :class="{ 'form-label-required': !isEditMode }" for="userPassword">
                                    Contraseña
                                </label>
                                <div class="password-field">
                                    <input
                                        :type="showPassword ? 'text' : 'password'"
                                        class="form-control password-field__input"
                                        id="userPassword"
                                        x-model="form.password"
                                        :required="!isEditMode"
                                        autocomplete="new-password"
                                        minlength="8"
                                        placeholder="Mínimo 8 caracteres">
                                    <button
                                        type="button"
                                        class="password-field__toggle"
                                        @click="showPassword = !showPassword"
                                        :title="showPassword ? 'Ocultar' : 'Mostrar'">
                                        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>

                                <!-- Indicador de fortaleza -->
                                <template x-if="form.password">
                                    <div>
                                        <div class="password-strength" :class="'password-strength--' + passwordStrength">
                                            <div class="password-strength__bar"></div>
                                            <div class="password-strength__bar"></div>
                                            <div class="password-strength__bar"></div>
                                            <div class="password-strength__bar"></div>
                                        </div>
                                        <p class="password-strength__text text-muted mb-0" x-text="'Fortaleza: ' + passwordStrengthText"></p>
                                    </div>
                                </template>

                                <p class="form-hint" x-show="isEditMode">
                                    Déjalo vacío para mantener la contraseña actual.
                                </p>
                            </div>

                            <!-- Confirmar Password -->
                            <div class="mb-3" x-show="form.password">
                                <label class="form-label" for="userPasswordConfirm">
                                    Confirmar contraseña
                                </label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="userPasswordConfirm"
                                    x-model="form.password_confirm"
                                    autocomplete="new-password"
                                    placeholder="Repite la contraseña">
                            </div>
                        </div>

                        <!-- Rol y Estado -->
                        <div class="user-modal__section">
                            <h6 class="user-modal__section-title">
                                <i class="bi bi-gear"></i>
                                Configuración
                            </h6>

                            <!-- Rol -->
                            <div class="mb-3">
                                <label class="form-label form-label-required" for="userRole">
                                    Rol
                                </label>
                                <select
                                    class="form-select"
                                    id="userRole"
                                    x-model="form.role_id"
                                    required>
                                    <option value="">Seleccionar rol...</option>
                                    <template x-for="role in availableRoles" :key="role.id">
                                        <option :value="role.id" x-text="role.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Estado -->
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    id="userActive"
                                    x-model="form.is_active">
                                <label class="form-check-label" for="userActive">
                                    <strong>Usuario activo</strong>
                                    <span class="d-block text-muted small">
                                        Los usuarios inactivos no pueden iniciar sesión
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="isSubmitting">
                            <template x-if="!isSubmitting">
                                <span>
                                    <i class="bi bi-check-lg me-1"></i>
                                    <span x-text="isEditMode ? 'Guardar Cambios' : 'Crear Usuario'"></span>
                                </span>
                            </template>
                            <template x-if="isSubmitting">
                                <span>
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Guardando...
                                </span>
                            </template>
                        </button>
                    </div>
            </form>
        </div>
    </div>
</div>
