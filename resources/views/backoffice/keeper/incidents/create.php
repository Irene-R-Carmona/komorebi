<?php

declare(strict_types=1);

/**
 * Formulario de Reporte de Incidente
 *
 * Permite al keeper reportar un nuevo incidente de un animal.
 */
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2">
                <a href="/keeper/incidents" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="bi bi-plus-circle text-danger"></i>
                    Reportar Incidente
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
                    <form method="POST" action="/keeper/incidents">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Core\Csrf::token()) ?>">

                        <div class="mb-3">
                            <label for="animal_id" class="form-label fw-semibold">
                                Animal afectado <span class="text-danger">*</span>
                            </label>
                            <select
                                id="animal_id"
                                name="animal_id"
                                class="form-select"
                                required>
                                <option value="" disabled selected>— Selecciona un animal —</option>
                                <?php foreach ($animals ?? [] as $animal): ?>
                                    <option value="<?= e((string) $animal['id']) ?>">
                                        <?= e($animal['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecciona el animal afectado por el incidente.</div>
                        </div>

                        <div class="mb-3">
                            <label for="severity" class="form-label fw-semibold">
                                Severidad <span class="text-danger">*</span>
                            </label>
                            <select id="severity" name="severity" class="form-select" required>
                                <option value="" disabled selected>Selecciona severidad…</option>
                                <option value="low">Baja — Situación menor, sin urgencia</option>
                                <option value="medium">Media — Requiere seguimiento</option>
                                <option value="high">Alta — Necesita atención pronto</option>
                                <option value="critical">Crítica — Atención inmediata</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="incident_type" class="form-label fw-semibold">Tipo de incidente</label>
                            <?php
                            $incidentTypeLabels = [
                                'bite' => 'Mordedura',
                                'injury' => 'Lesión',
                                'escape' => 'Escape',
                                'illness' => 'Enfermedad',
                                'behavior' => 'Comportamiento',
                                'other' => 'Otro',
                            ];
                            ?>
                            <select id="incident_type" name="incident_type" class="form-select">
                                <option value="">Selecciona tipo (opcional)…</option>
                                <?php foreach (\App\Domain\AnimalVocabulary::INCIDENT_TYPES as $t): ?>
                                    <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($incidentTypeLabels[$t] ?? \ucfirst($t), ENT_QUOTES, 'UTF-8') ?>
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
                                required
                                placeholder="Describe el incidente con detalle…"></textarea>
                            <div class="form-text">Mínimo 10 caracteres.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-exclamation-triangle"></i> Reportar Incidente
                            </button>
                            <a href="/keeper/incidents" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
