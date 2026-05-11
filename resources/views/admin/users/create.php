<?php

declare(strict_types=1);

/**
 * Vista: Crear Usuario
 * Ruta: GET /admin/users/create
 *
 * Variables disponibles (escapadas por View::render):
 * @var string $titulo
 * @var array  $roles       - Roles disponibles con conteo de usuarios
 * @var string $csrf_token
 */

$roles ??= [];
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-person-plus text-primary"></i>
                        Nuevo Usuario
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item">
                                <a href="/admin/users">Usuarios</a>
                            </li>
                            <li class="breadcrumb-item active">Nuevo</li>
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
                        Datos del nuevo usuario
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="/admin/users"
                        x-data="{ submitting: false }"
                        @submit.prevent="
                            submitting = true;
                            fetch(window.AppRoutes.adminUsers, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': document.querySelector('[name=csrf_token]').value
                                },
                                body: JSON.stringify({
                                    name:     document.getElementById('name').value,
                                    email:    document.getElementById('email').value,
                                    password: document.getElementById('password').value,
                                    role_id:  parseInt(document.getElementById('role_id').value)
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.ok || data.id) {
                                    window.location.href = '/admin/users';
                                } else {
                                    alert(data.message || 'Error al crear el usuario.');
                                    submitting = false;
                                }
                            })
                            .catch(() => { alert('Error de red.'); submitting = false; })
                          ">

                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Nombre -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">
                                Nombre completo <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name"
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
                                placeholder="usuario@ejemplo.com"
                                required maxlength="255"
                                autocomplete="email">
                        </div>

                        <!-- Contraseña -->
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Contraseña <span class="text-danger">*</span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Mínimo 8 caracteres"
                                required minlength="8" maxlength="255"
                                autocomplete="new-password">
                            <div class="form-text">Al menos 8 caracteres.</div>
                        </div>

                        <!-- Rol -->
                        <div class="mb-4">
                            <label for="role_id" class="form-label fw-semibold">
                                Rol <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Selecciona un rol…</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>">
                                        <?= htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= (int) ($role['user_count'] ?? 0) ?> usuarios)
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
                                    <i class="bi bi-person-plus me-1"></i> Crear usuario
                                </span>
                                <span x-show="submitting">
                                    <span class="spinner-border spinner-border-sm me-1"></span>
                                    Creando…
                                </span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
