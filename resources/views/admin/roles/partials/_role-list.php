<?php
/**
 * Partial: Lista de roles (cards)
 */

use App\Core\View;

?>

<!-- Loading -->
<div x-show="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<!-- Grid de Roles -->
<div x-show="!loading" class="row g-4">
    <template x-for="role in roles" :key="role.id">
        <div class="col-md-6 col-lg-4">
            <div class="role-card" :class="{ 'role-card--system': role.isSystem }">
                <!-- Header -->
                <div class="role-card__header">
                    <div>
                        <h5 class="role-card__title" x-text="role.name"></h5>
                        <code class="role-card__code" x-text="role.code"></code>
                    </div>
                    <span
                        x-show="role.isSystem"
                        class="role-card__badge role-card__badge--system"
                    >
                        Sistema
                    </span>
                </div>

                <!-- Description -->
                <p
                    class="role-card__description"
                    x-text="role.description || 'Sin descripción'"
                ></p>

                <!-- Meta -->
                <div class="role-card__meta">
                    <div class="role-card__meta-item">
                        <i class="bi bi-shield-check"></i>
                        <span x-text="role.permissions_count + ' permisos'"></span>
                    </div>
                    <div class="role-card__meta-item">
                        <i class="bi bi-people"></i>
                        <span x-text="(role.users_count || 0) + ' usuarios'"></span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="role-card__actions">
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        @click="openEditModal(role)"
                        :disabled="role.isSystem"
                    >
                        <i class="bi bi-pencil me-1"></i>
                        Editar
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-info"
                        @click="openPermissionsModal(role)"
                    >
                        <i class="bi bi-shield-check me-1"></i>
                        Permisos
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        @click="confirmDelete(role)"
                        :disabled="role.isSystem"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Empty State -->
    <template x-if="roles.length === 0">
        <div class="col-12">
            <?= View::componentToString('components/admin/empty-state', [
                'icon' => 'people',
                'title' => 'No hay roles',
                'message' => 'Crea el primer rol para comenzar',
                'actionLabel' => 'Crear Rol',
                'actionClick' => 'openCreateModal()',
            ]) ?>
        </div>
    </template>
</div>