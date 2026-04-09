<?php

/**
 * Modal: Crear/Editar Usuario
 *
 * Formulario de usuario integrado con Alpine.js
 * Usado en admin/usuarios.php
 */
?>

<!-- Contenido del formulario (usado dentro de base-modal) -->
<form @submit.prevent="submitUser" id="userForm">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" x-model="form.csrf_token">

    <!-- Modo edición (hidden) -->
    <input type="hidden" name="user_id" x-model="form.id">

    <!-- Alerta de errores -->
    <div x-show="formErrors.length > 0"
        x-transition
        class="alert alert-danger alert-dismissible fade show mb-3"
        role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Error:</strong>
        <ul class="mb-0 mt-2">
            <template x-for="error in formErrors" :key="error">
                <li x-text="error"></li>
            </template>
        </ul>
        <button type="button" class="btn-close" @click="formErrors = []"></button>
    </div>

    <!-- Nombre -->
    <div class="mb-3">
        <label for="userName" class="form-label">
            Nombre completo <span class="text-danger">*</span>
        </label>
        <input
            type="text"
            class="form-control"
            id="userName"
            name="name"
            x-model="form.name"
            :class="{ 'is-invalid': formErrors.name }"
            required
            maxlength="100"
            placeholder="Ej: Juan Pérez García">
        <div class="invalid-feedback" x-show="formErrors.name" x-text="formErrors.name"></div>
    </div>

    <!-- Email -->
    <div class="mb-3">
        <label for="userEmail" class="form-label">
            Email <span class="text-danger">*</span>
        </label>
        <input
            type="email"
            class="form-control"
            id="userEmail"
            name="email"
            x-model="form.email"
            :class="{ 'is-invalid': formErrors.email }"
            required
            maxlength="255"
            placeholder="usuario@ejemplo.com">
        <div class="invalid-feedback" x-show="formErrors.email" x-text="formErrors.email"></div>
    </div>

    <!-- Rol -->
    <div class="mb-3">
        <label for="userRole" class="form-label">
            Rol <span class="text-danger">*</span>
        </label>
        <select
            class="form-select"
            id="userRole"
            name="role_id"
            x-model.number="form.role_id"
            :class="{ 'is-invalid': formErrors.role_id }"
            required>
            <option value="">-- Selecciona un rol --</option>
            <template x-for="role in availableRoles" :key="role.id">
                <option :value="role.id" x-text="role.name"></option>
            </template>
        </select>
        <div class="invalid-feedback" x-show="formErrors.role_id" x-text="formErrors.role_id"></div>
        <small class="form-text text-muted">
            Define los permisos del usuario en el sistema
        </small>
    </div>

    <!-- Password (solo crear o cambiar) -->
    <div class="mb-3">
        <label for="userPassword" class="form-label">
            Contraseña
            <span x-show="!isEditMode" class="text-danger">*</span>
            <span x-show="isEditMode" class="text-muted">(dejar en blanco para mantener actual)</span>
        </label>
        <div class="input-group">
            <input
                :type="showPassword ? 'text' : 'password'"
                class="form-control"
                id="userPassword"
                name="password"
                x-model="form.password"
                :class="{ 'is-invalid': formErrors.password }"
                :required="!isEditMode"
                minlength="8"
                placeholder="Mínimo 8 caracteres">
            <button
                class="btn btn-outline-secondary"
                type="button"
                @click="showPassword = !showPassword"
                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
            </button>
        </div>
        <div class="invalid-feedback d-block" x-show="formErrors.password" x-text="formErrors.password"></div>
        <small class="form-text text-muted">
            Debe contener al menos 8 caracteres
        </small>
    </div>

    <!-- Confirmar Password -->
    <div class="mb-3" x-show="form.password.length > 0">
        <label for="userPasswordConfirm" class="form-label">
            Confirmar contraseña <span class="text-danger">*</span>
        </label>
        <input
            :type="showPassword ? 'text' : 'password'"
            class="form-control"
            id="userPasswordConfirm"
            name="password_confirm"
            x-model="form.password_confirm"
            :class="{ 'is-invalid': formErrors.password_confirm }"
            :required="form.password.length > 0"
            placeholder="Repite la contraseña">
        <div class="invalid-feedback" x-show="formErrors.password_confirm" x-text="formErrors.password_confirm"></div>
    </div>

    <!-- Estado (solo en edición) -->
    <div x-show="isEditMode" class="mb-3">
        <div class="form-check form-switch">
            <input
                class="form-check-input"
                type="checkbox"
                id="userActive"
                x-model="form.is_active">
            <label class="form-check-label" for="userActive">
                Usuario activo
            </label>
        </div>
        <small class="form-text text-muted">
            Los usuarios inactivos no pueden iniciar sesión
        </small>
    </div>

    <!-- Botones del formulario -->
    <div class="modal-footer px-0 pb-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" :disabled="isSubmitting">
            Cancelar
        </button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
            <span x-show="!isSubmitting">
                <i class="bi" :class="isEditMode ? 'bi-save' : 'bi-plus-lg'"></i>
                <span x-text="isEditMode ? 'Actualizar Usuario' : 'Crear Usuario'"></span>
            </span>
            <span x-show="isSubmitting">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Guardando...
            </span>
        </button>
    </div>
</form>
