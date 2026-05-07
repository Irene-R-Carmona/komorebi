<?php

declare(strict_types=1);

/**
 * Vista: Editar Usuario
 * Ruta: GET /admin/users/{id}/edit
 *
 * Variables disponibles (escapadas por View::render):
 * @var string $titulo
 * @var array  $user           - Datos del usuario: id, name, email, is_active
 * @var int|null $current_role_id - ID del rol actual del usuario
 * @var array  $roles          - Roles disponibles con conteo de usuarios
 * @var string $csrf_token
 */

$roles ??= [];
$user ??= [];
$current_role_id ??= null;
$userId = (int) ($user['id'] ?? 0);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-person-gear text-primary"></i>
                        Editar Usuario
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item">
                                <a href="/admin/users">Usuarios</a>
                            </li>
                            <li class="breadcrumb-item active">Editar</li>
                        </ol>
                    </nav>
                </div>
                <a href="/admin/users" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flashError = \App\Core\Flash::get('error'); ?>
    <?php if ($flashError !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-person-badge me-2 text-primary"></i>
                        Datos del usuario
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/admin/users/<?= $userId ?>/edit"
                        x-data="{ submitting: false }"
                        @submit.prevent="
                            submitting = true;
                            const payload = {
                                name:    document.getElementById('name').value,
                                email:   document.getElementById('email').value,
                                role_id: parseInt(document.getElementById('role_id').value)
                            };
                            const pwd = document.getElementById('password').value;
                            if (pwd !== '') { payload.password = pwd; }
                            fetch('/api/v1/admin/users/<?= $userId ?>', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': document.querySelector('[name=_csrf]').value
                                },
                                body: JSON.stringify(payload)
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.ok || data.id) {
                                    window.location.href = '/admin/users';
                                } else {
                                    alert(data.message || 'Error al actualizar el usuario.');
                                    submitting = false;
                                }
                            })
                            .catch(() => { alert('Error de red.'); submitting = false; })
                          ">

                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Nombre -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">
                                Nombre completo <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="<?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Ej. Yuki Tanaka"
                                required maxlength="255"
                                autocomplete="name">
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="usuario@ejemplo.com"
                                required maxlength="255"
                                autocomplete="email">
                        </div>

                        <!-- Contraseña (opcional) -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Nueva contraseña
                                <span class="text-muted fw-normal">(dejar en blanco para no cambiar)</span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Mínimo 8 caracteres"
                                minlength="8" maxlength="255"
                                autocomplete="new-password">
                            <div class="form-text">Solo rellenar si se desea cambiar la contraseña actual.</div>
                        </div>

                        <!-- Rol -->
                        <div class="mb-4">
                            <label for="role_id" class="form-label fw-semibold">
                                Rol <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Selecciona un rol…</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>"
                                        <?= ((int) $role['id'] === (int) $current_role_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= (int) ($role['user_count'] ?? $role['users_count'] ?? 0) ?> usuarios)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Acciones -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="/admin/users" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary" :disabled="submitting">
                                <span x-show="!submitting">
                                    <i class="bi bi-floppy me-1"></i> Guardar cambios
                                </span>
                                <span x-show="submitting">
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Guardando…
                                </span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
