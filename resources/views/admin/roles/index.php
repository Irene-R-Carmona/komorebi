<?php

/**
 * Vista: Gestión de Roles y Permisos
 * Ruta: GET /admin/roles
 *
 * @var array $roles - Lista de roles
 * @var array $permissions - Lista de permisos
 * @var array $rolePermissions - Permisos asignados por rol
 * @var array $stats - Estadísticas
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Csrf;
use App\Core\View;

$roles ??= [];
$permissions ??= [];
$rolePermissions ??= [];
$stats ??= [];
$csrfToken = Csrf::token();

$alpineConfig = json_encode([
    'roles' => $roles,
    'permissions' => $permissions,
    'rolePermissions' => $rolePermissions,
    'stats' => $stats,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='roleManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'people',
        'title' => 'Roles y Permisos',
        'subtitle' => 'Define quién puede hacer qué',
        'actionLabel' => 'Nuevo Rol',
        'actionClick' => 'openCreateModal()',
        'actionIcon' => 'plus-circle',
    ]) ?>

    <!-- Estadísticas -->
    <?php include __DIR__ . '/partials/_stats.php'; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs roles-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                :class="{ 'active': activeTab === 'roles' }"
                @click="activeTab = 'roles'"
                type="button"
                role="tab">
                <i class="bi bi-list-ul me-2"></i>
                Lista de Roles
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                :class="{ 'active': activeTab === 'matrix' }"
                @click="activeTab = 'matrix'"
                type="button"
                role="tab">
                <i class="bi bi-grid-3x3 me-2"></i>
                Matriz de Permisos
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Lista de Roles -->
        <div x-show="activeTab === 'roles'">
            <?php include __DIR__ . '/partials/_role-list.php'; ?>
        </div>

        <!-- Matriz de Permisos -->
        <div x-show="activeTab === 'matrix'">
            <?php include __DIR__ . '/partials/_permissions-matrix.php'; ?>
        </div>
    </div>

    <!-- Modals -->
    <?php include __DIR__ . '/partials/_role-modal.php'; ?>
    <?php include __DIR__ . '/partials/_permissions-modal.php'; ?>

</div>

<script nonce="<?= $cspNonce ?? '' ?>">
    // Scroll automático al ancla #permisos si está en la URL
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash === '#permisos') {
            // Cambiar a tab de matriz
            const alpineScope = Alpine.$data(document.querySelector('[x-data]'));
            if (alpineScope) {
                alpineScope.activeTab = 'matrix';
            }
            // Scroll suave después de que Alpine renderice
            setTimeout(() => {
                const target = document.querySelector('.nav-tabs');
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }, 100);
        }
    });
</script>
