<?php

declare(strict_types=1);

/**
 * Crear Animal - Módulo Admin (CRUD)
 *
 * Variables disponibles (escapadas por View::render):
 * @var string $titulo
 * @var string $csrf_token
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="bi bi-plus-circle text-success"></i>
                        Nuevo Animal
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item"><a href="/admin/animals">Animales</a></li>
                            <li class="breadcrumb-item active">Nuevo</li>
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
                    <form method="POST" action="/admin/animals">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
$selectedSpecies = $_POST['species'] ?? '';
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
                                value="<?= htmlspecialchars($_POST['breed'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="age_years" class="form-label fw-semibold">Edad (años)</label>
                            <input type="number" class="form-control" id="age_years" name="age_years"
                                value="<?= htmlspecialchars($_POST['age_years'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                min="0" max="50" style="max-width: 120px;">
                        </div>

                        <div class="mb-3">
                            <label for="personality" class="form-label fw-semibold">Personalidad</label>
                            <textarea class="form-control" id="personality" name="personality"
                                rows="3" maxlength="500"><?= htmlspecialchars($_POST['personality'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="cafe_id" class="form-label fw-semibold">Café</label>
                            <input type="number" class="form-control" id="cafe_id" name="cafe_id"
                                value="<?= htmlspecialchars($_POST['cafe_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                min="1" style="max-width: 120px;">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-lg me-1"></i> Crear animal
                            </button>
                            <a href="/admin/animals" class="btn btn-outline-secondary px-4">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
