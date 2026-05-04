<?php

declare(strict_types=1);

/**
 * Formulario de Edición de Incidente
 *
 * @var array $incident
 */

$severityLabels = [
    'low'      => 'Baja — Situación menor, sin urgencia',
    'medium'   => 'Media — Requiere seguimiento',
    'high'     => 'Alta — Necesita atención pronto',
    'critical' => 'Crítica — Atención inmediata',
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2">
                <a href="/keeper/incidents/<?= (int) ($incident['id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="bi bi-pencil-square text-warning"></i>
                    Editar Incidente #<?= (int) ($incident['id'] ?? 0) ?>
                </h1>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Datos del incidente</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="/keeper/incidents/<?= (int) ($incident['id'] ?? 0) ?>">
                        <?= \App\Core\Csrf::field() ?>

                        <?php if (!empty($incident['animal_name'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Animal</label>
                                <p class="form-control-plaintext">
                                    <strong><?= e($incident['animal_name']) ?></strong>
                                    <?php if (!empty($incident['species'])): ?>
                                        <small class="text-muted">(<?= e($incident['species']) ?>)</small>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="severity" class="form-label fw-semibold">
                                Severidad <span class="text-danger">*</span>
                            </label>
                            <select id="severity" name="severity" class="form-select" required>
                                <option value="" disabled>Selecciona severidad…</option>
                                <?php foreach ($severityLabels as $value => $label): ?>
                                    <option value="<?= e($value) ?>"
                                        <?= ($incident['severity'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">
                                Descripción <span class="text-danger">*</span>
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                class="form-control"
                                rows="4"
                                minlength="10"
                                required><?= e($incident['description'] ?? '') ?></textarea>
                            <div class="form-text">Mínimo 10 caracteres.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                            <a href="/keeper/incidents/<?= (int) ($incident['id'] ?? 0) ?>" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
