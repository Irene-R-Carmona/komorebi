<?php

declare(strict_types=1);

/**
 * Detalle de Animal - Módulo Keeper
 *
 * Variables disponibles (escapadas por View::render):
 * @var array      $animal       Datos del animal (findById)
 * @var array|null $careLogs     Últimos logs de cuidado (opcional)
 * @var array|null $healthChecks Últimos chequeos de salud (opcional)
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

$animalId = (int) $animal['id'];
$csrfToken = \App\Core\Csrf::token();
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-person-heart text-success"></i>
                        <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <p class="text-muted mb-0">Ficha de bienestar animal</p>
                </div>
                <div>
                    <a href="/keeper/animals" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver al listado
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

    <div class="row">
        <!-- Columna izquierda: Datos del animal -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm h-100">
                <?php if (!empty($animal['image_url'])): ?>
                    <img src="<?= htmlspecialchars($animal['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                        alt="Foto de <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="card-img-top img-fluid rounded-top"
                        style="max-height:300px; object-fit:cover; width:100%;"
                        x-data
                        x-on:error="$el.src='/images/ui/placeholder-animal.svg'">
                <?php else: ?>
                    <img src="/images/ui/placeholder-animal.svg"
                        alt="Sin foto de <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>"
                        class="card-img-top img-fluid rounded-top"
                        style="max-height:300px; object-fit:cover; width:100%;">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title">
                        <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="badge bg-<?= $getStatusBadgeClass($animal['current_status'] ?? '') ?> ms-2">
                            <?= $getStatusLabel($animal['current_status'] ?? '') ?>
                        </span>
                    </h5>
                    <?php /* Toggle de estado — solo para animales no retirados */ ?>
                    <?php if (($animal['current_status'] ?? '') !== 'retired'): ?>
                        <div class="mb-3"
                            x-data="{
                             status: '<?= htmlspecialchars($animal['current_status'] ?? 'active', ENT_QUOTES) ?>',
                             loading: false,
                             async toggle() {
                                 this.loading = true;
                                 try {
                                     const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';
                                     const body = new FormData();
                                     body.append('csrf_token', csrfToken);
                                     const res = await fetch('/api/v1/keeper/animals/<?= (int) $animal['id'] ?>/toggle', {
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
                            <span :class="status === 'active' ? 'badge bg-success fs-6' : 'badge bg-secondary fs-6'"
                                x-text="status === 'active' ? 'Activo' : 'Inactivo'">
                            </span>
                            <button class="btn btn-sm ms-2"
                                :class="status === 'active' ? 'btn-outline-secondary' : 'btn-outline-success'"
                                :disabled="loading"
                                @click="toggle()">
                                <span x-show="loading" class="spinner-border spinner-border-sm me-1" x-cloak></span>
                                <span x-text="status === 'active' ? 'Desactivar' : 'Activar'"></span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-1">
                            <i class="bi bi-tag me-1"></i>
                            <strong>Especie:</strong>
                            <?= htmlspecialchars($animal['species_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                        </li>
                        <?php if (!empty($animal['age'])): ?>
                            <li class="mb-1">
                                <i class="bi bi-calendar3 me-1"></i>
                                <strong>Edad:</strong> <?= (int) $animal['age'] ?> años
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($animal['personality'])): ?>
                            <li class="mb-1">
                                <i class="bi bi-emoji-smile me-1"></i>
                                <strong>Personalidad:</strong>
                                <?= htmlspecialchars($animal['personality'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($animal['interaction_level'])): ?>
                            <li class="mb-1">
                                <i class="bi bi-hand-index me-1"></i>
                                <strong>Interacción:</strong>
                                <?= htmlspecialchars($animal['interaction_level'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($animal['last_health_check'])): ?>
                            <li class="mb-1">
                                <i class="bi bi-clipboard2-pulse me-1"></i>
                                <strong>Último chequeo:</strong>
                                <?= htmlspecialchars($animal['last_health_check'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($animal['last_check_at'])): ?>
                            <li class="mb-1">
                                <i class="bi bi-clock-history me-1"></i>
                                <strong>Última actividad:</strong>
                                <?= htmlspecialchars($animal['last_check_at'], ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!empty($animal['description'])): ?>
                        <hr>
                        <p class="small mb-0">
                            <?= htmlspecialchars($animal['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Acciones y registros -->
        <div class="col-lg-8">
            <!-- Acción: Registrar Alimentación -->
            <div class="card shadow-sm mb-4" x-data="{ loading: false }">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-egg-fried"></i> Registrar Alimentación
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="/keeper/animals/<?= $animalId ?>/feeding"
                        @submit="loading = true">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="mood_before" class="form-label small">Estado antes</label>
                                <select class="form-select form-select-sm" id="mood_before" name="mood_before">
                                    <option value="">— Sin registrar —</option>
                                    <option value="happy">Contento</option>
                                    <option value="calm">Tranquilo</option>
                                    <option value="anxious">Ansioso</option>
                                    <option value="aggressive">Agresivo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mood_after" class="form-label small">Estado después</label>
                                <select class="form-select form-select-sm" id="mood_after" name="mood_after">
                                    <option value="">— Sin registrar —</option>
                                    <option value="happy">Contento</option>
                                    <option value="calm">Tranquilo</option>
                                    <option value="anxious">Ansioso</option>
                                    <option value="aggressive">Agresivo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="duration_minutes" class="form-label small">Duración (min)</label>
                                <input type="number" min="1" max="120"
                                    class="form-control form-control-sm"
                                    id="duration_minutes" name="duration_minutes"
                                    placeholder="Ej: 15">
                            </div>
                            <div class="col-md-8">
                                <label for="feeding_notes" class="form-label small">Notas</label>
                                <input type="text" maxlength="500"
                                    class="form-control form-control-sm"
                                    id="feeding_notes" name="notes"
                                    placeholder="Observaciones opcionales">
                            </div>
                            <div class="col-12">
                                <button type="submit"
                                    class="btn btn-success btn-sm"
                                    :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-check-lg"></i> Registrar alimentación
                                    </span>
                                    <span x-show="loading">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                        Guardando…
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Acción: Registrar Chequeo de Salud -->
            <div class="card shadow-sm mb-4" x-data="{ loading: false }">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard2-pulse"></i> Registrar Chequeo de Salud
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST"
                        action="/keeper/animals/<?= $animalId ?>/health"
                        @submit="loading = true">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="animal_id" value="<?= $animalId ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="weight_kg" class="form-label small">Peso (kg)</label>
                                <input type="number" step="0.01" min="0.1" max="100"
                                    class="form-control form-control-sm"
                                    id="weight_kg" name="weight_kg"
                                    placeholder="Ej: 4.50">
                            </div>
                            <div class="col-md-4">
                                <label for="temperature_c" class="form-label small">Temperatura (°C)</label>
                                <input type="number" step="0.1" min="30" max="45"
                                    class="form-control form-control-sm"
                                    id="temperature_c" name="temperature_c"
                                    placeholder="Ej: 38.5">
                            </div>
                            <div class="col-md-4">
                                <label for="appetite" class="form-label small">Apetito</label>
                                <select class="form-select form-select-sm" id="appetite" name="appetite">
                                    <option value="normal" selected>Normal</option>
                                    <option value="increased">Aumentado</option>
                                    <option value="decreased">Disminuido</option>
                                    <option value="none">Sin apetito</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="energy_level" class="form-label small">Nivel de energía</label>
                                <select class="form-select form-select-sm" id="energy_level" name="energy_level">
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">Alto</option>
                                    <option value="low">Bajo</option>
                                    <option value="lethargic">Letárgico</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="coat_condition" class="form-label small">Condición del pelaje</label>
                                <select class="form-select form-select-sm" id="coat_condition" name="coat_condition">
                                    <option value="excellent">Excelente</option>
                                    <option value="good" selected>Buena</option>
                                    <option value="fair">Regular</option>
                                    <option value="poor">Pobre</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox"
                                        id="eyes_clear" name="eyes_clear" value="1" checked>
                                    <label class="form-check-label small" for="eyes_clear">
                                        Ojos limpios
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                        id="breathing_normal" name="breathing_normal" value="1" checked>
                                    <label class="form-check-label small" for="breathing_normal">
                                        Respiración normal
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                        id="mobility_normal" name="mobility_normal" value="1" checked>
                                    <label class="form-check-label small" for="mobility_normal">
                                        Movilidad normal
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="health_notes" class="form-label small">Notas</label>
                                <textarea class="form-control form-control-sm" id="health_notes"
                                    name="notes" rows="2" maxlength="1000"
                                    placeholder="Observaciones adicionales…"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit"
                                    class="btn btn-primary btn-sm"
                                    :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-clipboard2-check"></i> Guardar chequeo
                                    </span>
                                    <span x-show="loading">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                        Guardando…
                                    </span>
                                </button>
                                <a href="/keeper/health-checks/create/<?= $animalId ?>"
                                    class="btn btn-outline-primary btn-sm ms-2">
                                    <i class="bi bi-clipboard2-plus"></i> Chequeo completo
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historial de health checks (si se pasan datos) -->
            <?php if (!empty($healthChecks)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-clipboard2-data text-primary"></i> Últimos chequeos de salud</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Peso (kg)</th>
                                    <th>Temperatura</th>
                                    <th>Apetito</th>
                                    <th>Energía</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($healthChecks as $hc): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($hc['check_date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $hc['weight_kg'] !== null ? htmlspecialchars((string) $hc['weight_kg'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
                                        <td><?= $hc['temperature_c'] !== null ? htmlspecialchars((string) $hc['temperature_c'], ENT_QUOTES, 'UTF-8') . '°C' : '-' ?></td>
                                        <td><?= htmlspecialchars($hc['appetite'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($hc['energy_level'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a href="/keeper/health-checks/<?= (int) $hc['id'] ?>"
                                                class="btn btn-xs btn-outline-secondary">Ver</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Historial de cuidados (si se pasan datos) -->
            <?php if (!empty($careLogs)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-journal-check text-success"></i> Últimos registros de cuidado</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Actividad</th>
                                    <th>Estado antes / después</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($careLogs as $log): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= htmlspecialchars($log['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($log['activity_type'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars($log['mood_before'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                            <i class="bi bi-arrow-right"></i>
                                            <?= htmlspecialchars($log['mood_after'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= htmlspecialchars($log['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Enlace al historial completo de chequeos -->
            <div class="text-end">
                <a href="/keeper/health-checks/history/<?= $animalId ?>"
                    class="btn btn-outline-info btn-sm">
                    <i class="bi bi-clock-history"></i> Ver historial completo de chequeos
                </a>
            </div>
        </div>
    </div>
</div>
