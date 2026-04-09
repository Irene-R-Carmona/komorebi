<?php
/**
 * Partial: Modal crear/editar rol
 */
?>

<div
    class="modal fade"
    id="roleFormModal"
    tabindex="-1"
    aria-labelledby="roleFormModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form @submit.prevent="submitRole">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="roleFormModalLabel">
                        <i class="bi me-2" :class="editingRole ? 'bi-pencil' : 'bi-plus-circle'"></i>
                        <span x-text="editingRole ? 'Editar Rol' : 'Nuevo Rol'"></span>
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
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

                    <!-- Código (solo al crear) -->
                    <div class="mb-3" x-show="!editingRole">
                        <label class="form-label form-label-required" for="roleCode">
                            Código
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="roleCode"
                            x-model="form.code"
                            required
                            pattern="[a-z_]+"
                            placeholder="ej: manager"
                            :disabled="editingRole"
                        >
                        <p class="form-hint">Solo letras minúsculas y guiones bajos. No se puede cambiar después.</p>
                    </div>

                    <!-- Nombre -->
                    <div class="mb-3">
                        <label class="form-label form-label-required" for="roleName">
                            Nombre
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="roleName"
                            x-model="form.name"
                            required
                            maxlength="100"
                            placeholder="Ej: Gerente"
                        >
                    </div>

                    <!-- Descripción -->
                    <div class="mb-3">
                        <label class="form-label" for="roleDescription">
                            Descripción
                        </label>
                        <textarea
                            class="form-control"
                            id="roleDescription"
                            x-model="form.description"
                            rows="3"
                            maxlength="255"
                            placeholder="Descripción del rol y sus responsabilidades..."
                        ></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="saving"
                    >
                        <template x-if="!saving">
                            <span>Guardar</span>
                        </template>
                        <template x-if="saving">
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