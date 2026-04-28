<?php

/**
 * Vista parcial: Matriz de permisos
 */

use App\Core\View;

?>

<div class="matrix-info">
    <i class="bi bi-info-circle"></i>
    <span>
        <strong>Matriz de Permisos:</strong> Permisos asignados a cada rol del sistema.
        Los cambios se aplican inmediatamente. Los roles de sistema no se pueden modificar.
    </span>
</div>

<!-- Búsqueda y paginación -->
<div class="d-flex justify-content-between align-items-center mb-3" x-show="paginatedPermissions !== undefined">
    <div class="flex-grow-1 me-3">
        <input
            type="text"
            class="form-control"
            placeholder="Buscar permisos..."
            x-model="permissionSearch"
            @input.debounce.300ms="currentPermissionPage = 1">
    </div>
    <div class="text-muted">
        <span x-text="Object.keys(permissionsByModule).length"></span> módulos,
        <span x-text="totalFilteredPermissions"></span> permisos
    </div>
</div>

<!-- Estado de carga -->
<div x-show="paginatedPermissions === undefined" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <p class="text-muted mt-3">Cargando permisos...</p>
</div>

<div class="permission-matrix__container" x-show="paginatedPermissions !== undefined">
    <div class="permission-matrix">
        <table class="table permission-matrix__table">
            <thead>
                <tr>
                    <th style="min-width: 250px;">Módulo / Permiso</th>
                    <template x-for="role in roles" :key="role.id">
                        <th>
                            <div x-text="role.name"></div>
                            <small class="text-muted d-block" x-text="role.code"></small>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <!-- Módulos y permisos -->
                <template x-for="(permissions, module) in paginatedPermissions" :key="module">
            <tbody>
                <!-- Cabecera del módulo -->
                <tr class="permission-matrix__module">
                    <td :colspan="roles.length + 1">
                        <strong x-text="formatModuleName(module)"></strong>
                    </td>
                </tr>

                <!-- Permissions del módulo -->
                <template x-for="permission in permissions" :key="permission.id">
                    <tr>
                        <td>
                            <div class="permission-matrix__permission">
                                <span class="permission-matrix__permission-name" x-text="permission.name"></span>
                                <code class="permission-matrix__permission-code" x-text="permission.code"></code>
                            </div>
                        </td>
                        <template x-for="role in roles" :key="role.id">
                            <td class="permission-matrix__checkbox">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="hasPermission(role.id, permission.id)"
                                    @change="togglePermission(role.id, permission.id, $event.target.checked, $event.target)"
                                    :disabled="role.isSystem || savingPermission">
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
            </template>
            </tbody>

            <!-- Estado vacío -->
            <tbody>
                <template x-if="Object.keys(permissionsByModule).length === 0">
                    <tr>
                        <td :colspan="roles.length + 1" class="text-center py-5">
                            <?= View::componentToString('components/admin/empty-state', [
                                'icon' => 'shield-exclamation',
                                'title' => 'Sin permisos',
                                'message' => 'No hay permisos registrados en el sistema',
                                'compact' => true,
                            ]) ?>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginación -->
<div class="d-flex justify-content-center mt-4" x-show="paginatedPermissions !== undefined && totalPermissionPages > 1">
    <nav>
        <ul class="pagination">
            <li class="page-item" :class="{ 'disabled': currentPermissionPage === 1 }">
                <button class="page-link" @click="currentPermissionPage = Math.max(1, currentPermissionPage - 1)" :disabled="currentPermissionPage === 1">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
            <template x-for="page in visiblePermissionPages" :key="page">
                <li class="page-item" :class="{ 'active': page === currentPermissionPage }">
                    <button class="page-link" @click="currentPermissionPage = page" x-text="page"></button>
                </li>
            </template>
            <li class="page-item" :class="{ 'disabled': currentPermissionPage === totalPermissionPages }">
                <button class="page-link" @click="currentPermissionPage = Math.min(totalPermissionPages, currentPermissionPage + 1)" :disabled="currentPermissionPage === totalPermissionPages">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        </ul>
    </nav>
</div>
