<?php

/**
 * Vista parcial: Matriz de permisos (Bootstrap Accordion)
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

<!-- Búsqueda y contador -->
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

<!-- Accordion de módulos -->
<div class="accordion" id="permissionsAccordion" x-show="paginatedPermissions !== undefined">
    <template x-for="(permissions, module) in paginatedPermissions" :key="module">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button
                    class="accordion-button"
                    :class="{ 'collapsed': !expandedModules[module] }"
                    type="button"
                    @click="expandedModules[module] = !expandedModules[module]"
                    :aria-expanded="expandedModules[module] ? 'true' : 'false'">
                    <strong x-text="formatModuleName(module)"></strong>
                    <span class="badge bg-secondary ms-2" x-text="permissions.length"></span>
                </button>
            </h2>
            <div
                class="accordion-collapse"
                :class="{ 'show': expandedModules[module] }"
                x-show="expandedModules[module]"
                x-collapse>
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:220px;">Permiso</th>
                                    <template x-for="role in roles" :key="role.id">
                                        <th class="text-center">
                                            <div x-text="role.name"></div>
                                            <small class="text-muted" x-text="role.code"></small>
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="permission in permissions" :key="permission.id">
                                    <tr>
                                        <td>
                                            <div class="fw-semibold" x-text="permission.name"></div>
                                            <code class="text-muted small" x-text="permission.code"></code>
                                        </td>
                                        <template x-for="role in roles" :key="role.id">
                                            <td class="text-center align-middle">
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
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Estado vacío -->
    <template x-if="Object.keys(paginatedPermissions ?? {}).length === 0 && paginatedPermissions !== undefined">
        <div class="text-center py-5">
            <?= View::componentToString('components/admin/empty-state', [
                'icon' => 'shield-exclamation',
                'title' => 'Sin permisos',
                'message' => 'No hay permisos registrados en el sistema',
                'compact' => true,
            ]) ?>
        </div>
    </template>
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
