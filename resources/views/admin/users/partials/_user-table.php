<?php

/**
 * Partial: Tabla de usuarios
 *
 * Incluye ordenamiento, paginación y acciones
 */

use App\Core\View;

?>

<div class="card-admin">
    <!-- Tabla -->
    <div class="table-responsive">
        <table class="table table-admin user-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <!-- ID -->
                    <th class="th-sortable"
                        :class="{ 'th-sortable--active': isSortedBy('id') }"
                        @click="sortBy('id')">
                        <span class="th-sortable__content">
                            ID
                            <i class="bi th-sortable__icon" :class="getSortIcon('id')"></i>
                        </span>
                    </th>

                    <!-- Usuario -->
                    <th class="th-sortable"
                        :class="{ 'th-sortable--active': isSortedBy('name') }"
                        @click="sortBy('name')">
                        <span class="th-sortable__content">
                            Usuario
                            <i class="bi th-sortable__icon" :class="getSortIcon('name')"></i>
                        </span>
                    </th>

                    <!-- Email (oculto en móvil) -->
                    <th class="th-sortable d-none d-lg-table-cell"
                        :class="{ 'th-sortable--active': isSortedBy('email') }"
                        @click="sortBy('email')">
                        <span class="th-sortable__content">
                            Email
                            <i class="bi th-sortable__icon" :class="getSortIcon('email')"></i>
                        </span>
                    </th>

                    <!-- Roles -->
                    <th>Roles</th>

                    <!-- Estado -->
                    <th class="th-sortable"
                        :class="{ 'th-sortable--active': isSortedBy('is_active') }"
                        @click="sortBy('is_active')">
                        <span class="th-sortable__content">
                            Estado
                            <i class="bi th-sortable__icon" :class="getSortIcon('is_active')"></i>
                        </span>
                    </th>

                    <!-- Registro -->
                    <th class="th-sortable d-none d-xl-table-cell"
                        :class="{ 'th-sortable--active': isSortedBy('created_at') }"
                        @click="sortBy('created_at')">
                        <span class="th-sortable__content">
                            Registrado
                            <i class="bi th-sortable__icon" :class="getSortIcon('created_at')"></i>
                        </span>
                    </th>

                    <!-- Acciones -->
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Empty state -->
                <template x-if="filteredUsers.length === 0">
                    <tr>
                        <td colspan="7">
                            <?= View::componentToString('components/admin/empty-state', [
                                'icon' => 'people',
                                'title' => 'No encontramos usuarios',
                                'message' => 'Prueba ajustando los filtros o crea uno nuevo',
                                'compact' => true,
                            ]) ?>
                        </td>
                    </tr>
                </template>

                <!-- Filas de usuarios -->
                <template x-for="user in paginatedUsers" :key="user.id">
                    <tr class="user-row" :class="{ 'user-row--inactive': !user.is_active }">
                        <!-- ID -->
                        <td>
                            <span class="fw-semibold text-muted" x-text="'#' + user.id"></span>
                        </td>

                        <!-- Usuario (avatar + nombre) -->
                        <td>
                            <div class="user-info">
                                <div class="user-avatar" :class="getAvatarClass(user.roles)">
                                    <span x-text="getInitial(user.name)"></span>
                                </div>
                                <div class="user-info__details">
                                    <span class="user-info__name" x-text="user.name"></span>
                                    <span class="user-info__email d-lg-none" x-text="user.email"></span>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td class="d-none d-lg-table-cell">
                            <span class="text-muted" x-text="user.email"></span>
                        </td>

                        <!-- Roles -->
                        <td>
                            <div class="role-badges">
                                <template x-for="(role, index) in user.roles" :key="user.id + '-role-' + index">
                                    <span
                                        class="role-badge"
                                        :class="getRoleBadgeClass(role)"
                                        x-text="role"></span>
                                </template>
                                <template x-if="!user.roles || user.roles.length === 0">
                                    <span class="role-badge role-badge--user">Sin rol</span>
                                </template>
                            </div>
                        </td>

                        <!-- Estado -->
                        <td>
                            <span
                                class="user-status"
                                :class="user.is_active ? 'user-status--active' : 'user-status--inactive'">
                                <span class="user-status__dot"></span>
                                <span x-text="user.is_active ? 'Activo' : 'Inactivo'"></span>
                            </span>
                        </td>

                        <!-- Fecha registro -->
                        <td class="d-none d-xl-table-cell">
                            <span class="text-muted small" x-text="formatDate(user.created_at)"></span>
                        </td>

                        <!-- Acciones -->
                        <td class="text-end">
                            <div class="table-actions">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    @click="openEditModal(user)"
                                    :aria-label="'Editar usuario ' + user.name"
                                    title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="user.is_active ? 'btn-outline-warning' : 'btn-outline-success'"
                                    @click="toggleUserStatus(user.id)"
                                    :aria-label="(user.is_active ? 'Desactivar' : 'Activar') + ' usuario ' + user.name"
                                    :title="user.is_active ? 'Desactivar' : 'Activar'">
                                    <i class="bi" :class="user.is_active ? 'bi-pause' : 'bi-play'"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    @click="deleteUser(user.id)"
                                    :disabled="!canDelete(user.id)"
                                    :aria-label="'Eliminar usuario ' + user.name"
                                    title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <template x-if="filteredUsers.length > 0">
        <div class="card-admin__footer d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted small">
                Mostrando
                <strong x-text="startIndex + 1"></strong> -
                <strong x-text="endIndex"></strong>
                de
                <strong x-text="filteredUsers.length"></strong> usuarios
            </div>

            <?= \App\Core\View::componentToString('components/admin/pagination', [
                'alpine' => true,
            ]) ?>
        </div>
    </template>
</div>
