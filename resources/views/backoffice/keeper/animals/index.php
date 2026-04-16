<?php

declare(strict_types=1);

/**
 * Lista de Animales - Módulo Keeper
 *
 * Variables disponibles (escapadas por View::render):
 * @var array $animals  Array de animales con café info (getAnimalsWithCafeInfoOptimized)
 */

$getStatusBadgeClass = function (string $status): string {
    return match ($status) {
        'active' => 'success',
        'resting' => 'warning',
        'sick' => 'danger',
        'retired' => 'secondary',
        default => 'secondary'
    };
};

$getStatusLabel = function (string $status): string {
    return match ($status) {
        'active' => 'Activo',
        'resting' => 'Reposo',
        'sick' => 'Enfermo',
        'retired' => 'Retirado',
        default => ucfirst($status)
    };
};
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-heart-pulse text-success"></i>
                        Gestión de Animales
                    </h1>
                    <p class="text-muted mb-0">Listado de todos los animales bajo tu cuidado</p>
                </div>
                <div>
                    <a href="/keeper/dashboard" class="btn btn-outline-secondary">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flashSuccess = \App\Core\Flash::get('success'); ?>
    <?php if ($flashSuccess !== null): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php $flashError = \App\Core\Flash::get('error'); ?>
    <?php if ($flashError !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabla de animales -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-primary">
                <i class="bi bi-table"></i> Animales (<?= count($animals) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($animals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-emoji-frown fs-1 d-block mb-3"></i>
                    <p class="mb-0">No hay animales registrados.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Especie</th>
                                <th scope="col">Café</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Chequeos hoy</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($animals as $animal): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <?php if (!empty($animal['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($animal['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                alt=""
                                                aria-hidden="true"
                                                class="rounded-circle me-2"
                                                width="32" height="32"
                                                style="object-fit:cover;"
                                                onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <span class="me-2 text-muted"><i class="bi bi-image"></i></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><?= htmlspecialchars($animal['species_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($animal['cafe_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td x-data="{
                                            status: '<?= htmlspecialchars($animal['current_status'] ?? 'active', ENT_QUOTES) ?>',
                                            loading: false,
                                            async toggle() {
                                                this.loading = true;
                                                try {
                                                    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';
                                                    const body = new FormData();
                                                    body.append('csrf_token', csrfToken);
                                                    const res = await fetch('/keeper/animals/<?= (int) $animal['id'] ?>/toggle', {
                                                        method: 'POST',
                                                        body
                                                    });
                                                    const json = await res.json();
                                                    if (json.ok) {
                                                        this.status = this.status === 'active' ? 'inactive' : 'active';
                                                    }
                                                } finally {
                                                    this.loading = false;
                                                }
                                            }
                                        }">
                                        <span :class="status === 'active' ? 'badge bg-success' : 'badge bg-secondary'"
                                            x-text="status === 'active' ? 'Activo' : 'Inactivo'">
                                        </span>
                                        <?php if (($animal['current_status'] ?? '') !== 'retired'): ?>
                                            <button class="btn btn-sm ms-1"
                                                :class="status === 'active' ? 'btn-outline-secondary' : 'btn-outline-success'"
                                                :disabled="loading"
                                                @click.stop="toggle()"
                                                :title="status === 'active' ? 'Desactivar' : 'Activar'">
                                                <span x-show="loading" class="spinner-border spinner-border-sm" x-cloak></span>
                                                <i x-show="!loading" :class="status === 'active' ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php $logsToday = (int) ($animal['logs_today'] ?? 0); ?>
                                        <span class="badge bg-<?= $logsToday > 0 ? 'info' : 'light text-dark' ?>">
                                            <?= $logsToday ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="/keeper/animals/<?= (int) $animal['id'] ?>"
                                            class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
