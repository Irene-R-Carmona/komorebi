<?php

/**
 * Partial: Lista de roles (PHP foreach — HDA)
 *
 * @var array $roles - Roles con counts
 */

use App\Core\View;

$roles ??= [];

$systemCodes  = ['admin', 'user'];
$badgeClasses = [
    'admin'      => 'role-list-badge--admin',
    'manager'    => 'role-list-badge--manager',
    'supervisor' => 'role-list-badge--supervisor',
    'reception'  => 'role-list-badge--reception',
    'kitchen'    => 'role-list-badge--kitchen',
    'keeper'     => 'role-list-badge--keeper',
    'user'       => 'role-list-badge--user',
];
?>

<div class="row g-4">
    <?php if ($roles === []): ?>
    <div class="col-12">
        <?= View::componentToString('components/admin/empty-state', [
            'icon'        => 'people',
            'title'       => 'No hay roles',
            'message'     => 'Crea el primer rol para comenzar',
            'actionLabel' => 'Crear Rol',
            'actionClick' => 'openCreateModal()',
        ]) ?>
    </div>
    <?php else: ?>
    <?php foreach ($roles as $role): ?>
    <?php
        $roleId    = (int) $role['id'];
        $roleCode  = (string) ($role['code']  ?? '');
        $roleName  = htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $isSystem  = \in_array($roleCode, $systemCodes, true);
        $badgeClass = $badgeClasses[$roleCode] ?? 'role-list-badge--user';

        $editData = htmlspecialchars(\json_encode([
            'id'          => $roleId,
            'code'        => $roleCode,
            'name'        => (string) ($role['name']        ?? ''),
            'description' => (string) ($role['description'] ?? ''),
        ], \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR), \ENT_QUOTES, 'UTF-8');

        $permData = htmlspecialchars(\json_encode([
            'id'       => $roleId,
            'code'     => $roleCode,
            'name'     => (string) ($role['name'] ?? ''),
            'isSystem' => $isSystem,
        ], \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR), \ENT_QUOTES, 'UTF-8');
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="role-card<?= $isSystem ? ' role-card--system' : '' ?>">

            <div class="role-card__header">
                <div>
                    <h5 class="role-card__title"><?= $roleName ?></h5>
                    <code class="role-card__code"><?= htmlspecialchars($roleCode, ENT_QUOTES, 'UTF-8') ?></code>
                </div>
                <?php if ($isSystem): ?>
                <span class="role-card__badge role-card__badge--system">Sistema</span>
                <?php endif; ?>
            </div>

            <p class="role-card__description">
                <?= htmlspecialchars((string) ($role['description'] ?? 'Sin descripción'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="role-card__meta">
                <div class="role-card__meta-item">
                    <i class="bi bi-shield-check"></i>
                    <span><?= (int) ($role['permissions_count'] ?? 0) ?> permisos</span>
                </div>
                <div class="role-card__meta-item">
                    <i class="bi bi-people"></i>
                    <span><?= (int) ($role['users_count'] ?? 0) ?> usuarios</span>
                </div>
            </div>

            <div class="role-card__actions">
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="openEditModal(<?= $editData ?>)"
                    <?= $isSystem ? 'disabled' : '' ?>
                    aria-label="Editar <?= $roleName ?>">
                    <i class="bi bi-pencil me-1"></i>Editar
                </button>
                <button
                    type="button"
                    class="btn btn-outline-info"
                    @click="openPermissionsModal(<?= $permData ?>)"
                    aria-label="Permisos de <?= $roleName ?>">
                    <i class="bi bi-shield-check me-1"></i>Permisos
                </button>
                <button
                    type="button"
                    class="btn btn-outline-danger"
                    @click="confirmDelete(<?= $roleId ?>, '<?= $roleName ?>', <?= $isSystem ? 'true' : 'false' ?>)"
                    <?= $isSystem ? 'disabled' : '' ?>
                    aria-label="Eliminar <?= $roleName ?>">
                    <i class="bi bi-trash"></i>
                </button>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
