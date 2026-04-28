<?php

/**
 * Partial: Tabla de usuarios (HDA — PHP foreach, sort links server-side)
 */

use App\Core\View;
use App\Support\ViewHelpers;

$users         ??= [];
$meta          ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$sl = static fn(string $label, string $field): string
    => ViewHelpers::sortLink($label, $field, $currentParams);
?>

<div class="card-admin">
    <div class="table-responsive">
        <table class="table table-admin user-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="th-sortable"><?= $sl('ID', 'id') ?></th>
                    <th class="th-sortable"><?= $sl('Usuario', 'name') ?></th>
                    <th class="th-sortable d-none d-lg-table-cell"><?= $sl('Email', 'email') ?></th>
                    <th>Roles</th>
                    <th class="th-sortable"><?= $sl('Estado', 'is_active') ?></th>
                    <th class="th-sortable d-none d-xl-table-cell"><?= $sl('Registrado', 'created_at') ?></th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr>
                    <td colspan="7">
                        <?= View::componentToString('components/admin/empty-state', [
                            'icon'    => 'people',
                            'title'   => 'No encontramos usuarios',
                            'message' => 'Prueba ajustando los filtros o crea uno nuevo',
                            'compact' => true,
                        ]) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <?php
                    $isActive   = !empty($user['is_active']);
                    $rolesArr   = array_filter(array_map('trim', explode(',', (string) ($user['roles'] ?? ''))));
                    $primaryRole = strtolower((string) ($rolesArr[0] ?? ''));
                    $avatarClass = match ($primaryRole) {
                        'admin'      => 'user-avatar--admin',
                        'manager'    => 'user-avatar--manager',
                        'supervisor' => 'user-avatar--supervisor',
                        default      => '',
                    };
                    $initial = strtoupper(substr((string) ($user['name'] ?? '?'), 0, 1));

                    $editData = htmlspecialchars(json_encode([
                        'id'        => (int) $user['id'],
                        'name'      => (string) $user['name'],
                        'email'     => (string) $user['email'],
                        'role_id'   => $user['role_id'] !== null ? (int) $user['role_id'] : null,
                        'is_active' => $isActive,
                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

                    $userName = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
                    $userId   = (int) $user['id'];
                ?>
                <tr class="user-row<?= $isActive ? '' : ' user-row--inactive' ?>">
                    <td><span class="fw-semibold text-muted">#<?= $userId ?></span></td>

                    <td>
                        <div class="user-info">
                            <div class="user-avatar <?= $avatarClass ?>">
                                <span><?= $initial ?></span>
                            </div>
                            <div class="user-info__details">
                                <span class="user-info__name"><?= $userName ?></span>
                                <span class="user-info__email d-lg-none">
                                    <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                    </td>

                    <td class="d-none d-lg-table-cell">
                        <span class="text-muted">
                            <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>

                    <td>
                        <div class="role-badges">
                        <?php if ($rolesArr === []): ?>
                            <span class="role-badge role-badge--user">Sin rol</span>
                        <?php else: ?>
                            <?php foreach ($rolesArr as $roleName): ?>
                            <?php
                                $rKey = strtolower(trim($roleName));
                                $badgeClass = match ($rKey) {
                                    'admin'      => 'role-badge--admin',
                                    'manager'    => 'role-badge--manager',
                                    'supervisor' => 'role-badge--supervisor',
                                    'reception'  => 'role-badge--reception',
                                    'kitchen'    => 'role-badge--kitchen',
                                    'keeper'     => 'role-badge--keeper',
                                    default      => 'role-badge--user',
                                };
                            ?>
                            <span class="role-badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </td>

                    <td>
                        <span class="user-status <?= $isActive ? 'user-status--active' : 'user-status--inactive' ?>">
                            <span class="user-status__dot"></span>
                            <?= $isActive ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>

                    <td class="d-none d-xl-table-cell">
                        <span class="text-muted small">
                            <?= htmlspecialchars(
                                date('d M Y', strtotime((string) ($user['created_at'] ?? 'now'))),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    </td>

                    <td class="text-end">
                        <div class="table-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                @click="openEditModal(<?= $editData ?>)"
                                aria-label="Editar <?= $userName ?>"
                                title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                @click="toggleUserStatus(<?= $userId ?>, '<?= $userName ?>', <?= $isActive ? 'true' : 'false' ?>)"
                                aria-label="<?= $isActive ? 'Desactivar' : 'Activar' ?> <?= $userName ?>"
                                title="<?= $isActive ? 'Desactivar' : 'Activar' ?>">
                                <i class="bi <?= $isActive ? 'bi-pause' : 'bi-play' ?>"></i>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="deleteUser(<?= $userId ?>, '<?= $userName ?>')"
                                <?= $userId === ($currentUserId ?? 0) ? 'disabled' : '' ?>
                                aria-label="Eliminar <?= $userName ?>"
                                title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($users !== []): ?>
    <div class="card-admin__footer d-flex justify-content-between align-items-center p-3 border-top">
        <div class="text-muted small">Página <?= (int) $meta['page'] ?></div>
        <?= ViewHelpers::paginationLinks($meta, $currentParams) ?>
    </div>
    <?php endif; ?>
</div>
