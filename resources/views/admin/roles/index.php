<?php

/**
 * Vista: Gestión de Roles y Permisos
 * Ruta: GET /admin/roles
 *
 * @var array $roles           - Lista de roles (PHP foreach)
 * @var array $permissions     - Lista de permisos (Alpine matrix)
 * @var array $rolePermissions - Permisos por rol { roleId: [permId, ...] } (Alpine matrix)
 * @var array $stats           - Estadísticas (PHP-rendered)
 */

use App\Core\Csrf;
use App\Core\View;

$roles           ??= [];
$permissions     ??= [];
$rolePermissions ??= [];
$stats           ??= ['total_roles' => 0, 'total_permissions' => 0, 'total_modules' => 0, 'users_with_roles' => 0];

$alpineConfig = json_encode([
    'permissions'     => $permissions,
    'rolePermissions' => $rolePermissions,
    'csrfToken'       => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='roleManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon'        => 'people',
        'title'       => 'Roles y Permisos',
        'subtitle'    => 'Define quién puede hacer qué',
        'actionLabel' => 'Nuevo Rol',
        'actionClick' => 'openCreateModal()',
        'actionIcon'  => 'plus-circle',
    ]) ?>

    <!-- Estadísticas -->
    <?php include_once __DIR__ . '/partials/_stats.php'; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs roles-tabs mb-4">
        <li class="nav-item">
            <button
                class="nav-link"
                :class="{ 'active': activeTab === 'roles' }"
                @click="activeTab = 'roles'"
                type="button">
                <i class="bi bi-list-ul me-2" aria-hidden="true"></i>Lista de Roles
            </button>
        </li>
        <li class="nav-item">
            <button
                class="nav-link"
                :class="{ 'active': activeTab === 'matrix' }"
                @click="activeTab = 'matrix'"
                type="button">
                <i class="bi bi-grid-3x3 me-2" aria-hidden="true"></i>Matriz de Permisos
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <div x-show="activeTab === 'roles'">
            <?php include_once __DIR__ . '/partials/_role-list.php'; ?>
        </div>
        <div x-show="activeTab === 'matrix'">
            <?php include_once __DIR__ . '/partials/_permissions-matrix.php'; ?>
        </div>
    </div>

    <!-- Modals -->
    <?php include_once __DIR__ . '/partials/_role-modal.php'; ?>
    <?php include_once __DIR__ . '/partials/_permissions-modal.php'; ?>

</div>

<script nonce="<?= $cspNonce ?? '' ?>">
    document.addEventListener('DOMContentLoaded', function () {
        if (globalThis.location.hash === '#permisos') {
            const scope = Alpine.$data(document.querySelector('[x-data]'));
            if (scope) { scope.activeTab = 'matrix'; }
            setTimeout(() => {
                document.querySelector('.nav-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    });
</script>
