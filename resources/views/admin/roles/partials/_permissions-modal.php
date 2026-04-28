<?php
/**
 * Partial: Modal asignar permisos
 */
?>

<div
    class="modal fade"
    id="permissionsModal"
    tabindex="-1"
    aria-labelledby="permissionsModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="permissionsModalLabel">
                    <i class="bi bi-shield-check me-2"></i>
                    Permisos de <span x-text="selectedRole?.name"></span>
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
                <template x-if="selectedRole">
                    <div>
                        <!-- Búsqueda -->
                        <div class="permission-search">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    x-model="permissionSearch"
                                    placeholder="Buscar permisos..."
                                >
                            </div>
                        </div>

                        <!-- Info si es rol de sistema -->
                        <div
                            x-show="selectedRole.isSystem"
                            class="alert alert-warning py-2 mb-3 small"
                        >
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Este es un rol de sistema. Los cambios se aplicarán pero pueden afectar el funcionamiento básico.
                        </div>

                        <!-- Lista de permisos por grupo -->
                        <div class="permission-groups">
                            <template x-for="(permissions, module) in filteredPermissionsByModule" :key="module">
                                <div class="permission-group">
                                    <h6 class="permission-group__title" x-text="formatModuleName(module)"></h6>
                                    <div class="permission-list">
                                        <template x-for="permission in permissions" :key="permission.id">
                                            <label class="permission-item">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input permission-item__checkbox"
                                                    :checked="hasPermission(selectedRole.id, permission.id)"
                                                    @change="togglePermissionInModal(permission.id, $event.target)"
                                                    :disabled="savingPermission"
                                                >
                                                <div class="permission-item__label">
                                                    <span class="permission-item__name" x-text="permission.name"></span>
                                                    <code class="permission-item__code" x-text="permission.code"></code>
                                                </div>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty state de búsqueda -->
                            <template x-if="permissionSearch && Object.keys(filteredPermissionsByModule).length === 0">
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-search display-6 opacity-25"></i>
                                    <p class="mb-0">No se encontraron permisos</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>