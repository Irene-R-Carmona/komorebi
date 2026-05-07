<?php

declare(strict_types=1);

/**
 * Editar Animal - Módulo Admin (CRUD)
 *
 * Variables disponibles (escapadas por View::render):
 * @var array  $animal       Datos del animal
 * @var string $titulo
 * @var string $csrf_token
 */

$animal ??= [];
$animalId = (int) ($animal['id'] ?? 0);
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-pencil-square text-warning"></i>
                        Editar: <?= htmlspecialchars($animal['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item"><a href="/admin/animals">Animales</a></li>
                            <li class="breadcrumb-item active">Editar</li>
                        </ol>
                    </nav>
                </div>
                <a href="/admin/animals" class="btn btn-outline-secondary btn-sm">
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
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="/admin/animals/<?= $animalId ?>">
                        <?= \App\Core\Csrf::field() ?>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="<?= htmlspecialchars($animal['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="species" class="form-label fw-semibold">Especie <span class="text-danger">*</span></label>
                            <?php
                            $speciesLabels = [
                                'cat' => 'Gato',
                                'dog' => 'Perro',
                                'rabbit' => 'Conejo',
                                'bird' => 'Pájaro',
                                'hedgehog' => 'Erizo',
                                'capybara' => 'Capibara',
                                'hamster' => 'Hámster',
                                'other' => 'Otro',
                            ];
$selectedSpecies = $_POST['species'] ?? ($animal['species'] ?? '');
?>
                            <select class="form-select" id="species" name="species" required>
                                <option value="">Selecciona especie…</option>
                                <?php foreach (\App\Domain\AnimalVocabulary::SPECIES as $s): ?>
                                    <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $selectedSpecies === $s ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($speciesLabels[$s] ?? ucfirst($s), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="breed" class="form-label fw-semibold">Raza</label>
                            <input type="text" class="form-control" id="breed" name="breed"
                                value="<?= htmlspecialchars($animal['breed'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="age_years" class="form-label fw-semibold">Edad (años)</label>
                            <input type="number" class="form-control" id="age_years" name="age_years"
                                value="<?= htmlspecialchars((string) ($animal['age_years'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                min="0" max="50" style="max-width: 120px;">
                        </div>

                        <div class="mb-3">
                            <label for="personality" class="form-label fw-semibold">Personalidad</label>
                            <textarea class="form-control" id="personality" name="personality"
                                rows="3" maxlength="500"><?= htmlspecialchars($animal['personality'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="cafe_id" class="form-label fw-semibold">Café</label>
                            <input type="number" class="form-control" id="cafe_id" name="cafe_id"
                                value="<?= htmlspecialchars((string) ($animal['cafe_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                min="1" style="max-width: 120px;">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning px-4">
                                <i class="bi bi-check-lg me-1"></i> Guardar cambios
                            </button>
                            <a href="/admin/animals" class="btn btn-outline-secondary px-4">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($animalId > 0): ?>
        <div class="row justify-content-center mt-4"
            x-data="{
             imageUrl: <?= json_encode($animal['image_url'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
             uploading: false,
             error: '',
             success: '',
             async upload(input) {
                 const file = input.files[0];
                 if (!file) return;
                 this.uploading = true;
                 this.error = '';
                 this.success = '';
                 const fd = new FormData();
                 fd.append('photo', file);
                 try {
                     const resp = await fetch('/api/v1/keeper/animals/<?= $animalId ?>/photo', {
                         method: 'POST',
                         body: fd,
                         credentials: 'same-origin'
                     });
                     const data = await resp.json();
                     if (resp.ok) {
                         this.imageUrl = data.image_url ?? this.imageUrl;
                         this.success = 'Foto actualizada correctamente.';
                     } else {
                         this.error = data.detail ?? data.message ?? 'Error al subir la foto.';
                     }
                 } catch {
                     this.error = 'Error de red al subir la foto.';
                 } finally {
                     this.uploading = false;
                     input.value = '';
                 }
             }
         }">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-image text-secondary me-1"></i> Foto del animal</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Foto actual -->
                        <div x-show="imageUrl" class="mb-3">
                            <p class="form-label fw-semibold mb-2">Foto actual</p>
                            <img :src="imageUrl"
                                alt="Foto actual del animal"
                                class="rounded border"
                                style="max-width: 200px; max-height: 200px; object-fit: cover;">
                        </div>
                        <div x-show="!imageUrl" class="mb-3 text-muted small" x-cloak>
                            <i class="bi bi-image me-1"></i> Sin foto. Sube una imagen a continuación.
                        </div>

                        <!-- Input de subida -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subir nueva foto</label>
                            <input type="file"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp"
                                :disabled="uploading"
                                @change="upload($event.target)"
                                style="max-width: 350px;">
                            <div class="form-text">JPG, PNG o WebP · máx. 5 MB</div>
                        </div>

                        <!-- Indicador de carga -->
                        <div x-show="uploading" class="text-muted small mb-2" x-cloak>
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Subiendo foto…
                        </div>

                        <!-- Mensajes de estado -->
                        <div x-show="success" x-text="success" class="alert alert-success py-2 small mb-0" x-cloak></div>
                        <div x-show="error" x-text="error" class="alert alert-danger py-2 small mb-0" x-cloak></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
