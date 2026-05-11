<?php

/**
 * Partial: Grid de cafés (HDA — PHP foreach, static HTML)
 */

use App\Core\View;
use App\Support\CurrencyFormatting;
use App\Support\ViewHelpers;

$cafes ??= [];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$categoryMap = [
    'lounge' => ['label' => 'Lounge',   'icon' => 'bi bi-house-heart', 'class' => 'category-badge--lounge'],
    'playroom' => ['label' => 'Playroom', 'icon' => 'bi bi-controller',  'class' => 'category-badge--playroom'],
    'farm' => ['label' => 'Farm',     'icon' => 'bi bi-tree',         'class' => 'category-badge--farm'],
    'zen' => ['label' => 'Zen',      'icon' => 'bi bi-flower1',      'class' => 'category-badge--zen'],
];

$animalMap = [
    'cat' => ['label' => 'Gatos',     'icon' => 'bi bi-cat'],
    'dog' => ['label' => 'Perros',    'icon' => 'bi bi-heart-pulse'],
    'rabbit' => ['label' => 'Conejos',   'icon' => 'bi bi-flower2'],
    'bird' => ['label' => 'Aves',      'icon' => 'bi bi-feather'],
    'hedgehog' => ['label' => 'Erizos',    'icon' => 'bi bi-circle'],
    'capybara' => ['label' => 'Capibaras', 'icon' => 'bi bi-cursor'],
    'mixed' => ['label' => 'Mixto',     'icon' => 'bi bi-grid'],
];
?>

<?php if ($cafes === []): ?>
    <div class="card-admin">
        <div class="card-admin__body">
            <?= View::componentToString('components/admin/empty-state', [
                'icon' => 'shop',
                'title' => 'No encontramos cafés',
                'message' => 'Prueba ajustando los filtros o añade una nueva sede',
                'actionLabel' => 'Crear Café',
                'actionClick' => 'openCreateModal()',
            ]) ?>
        </div>
    </div>
<?php else: ?>

    <div class="cafe-grid">
        <?php foreach ($cafes as $cafe): ?>
            <?php
            $cafeId = (int) $cafe['id'];
            $isActive = !empty($cafe['is_active']);
            $hasRes = !empty($cafe['has_reservations']);
            $category = (string) ($cafe['category'] ?? '');
            $animal = (string) ($cafe['animal_type'] ?? '');
            $rating = (float) ($cafe['rating_avg'] ?? 0);
            $fullStars = (int) $rating;

            $catInfo = $categoryMap[$category] ?? ['label' => $category, 'icon' => 'bi bi-geo-alt', 'class' => ''];
            $animalInfo = $animalMap[$animal] ?? ['label' => $animal];

            $cafeName = htmlspecialchars((string) ($cafe['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $editData = htmlspecialchars(json_encode([
                'id' => $cafeId,
                'name' => (string) ($cafe['name'] ?? ''),
                'japanese_name' => (string) ($cafe['japanese_name'] ?? ''),
                'slug' => (string) ($cafe['slug'] ?? ''),
                'location' => (string) ($cafe['location'] ?? ''),
                'category' => $category,
                'animal_type' => $animal,
                'description' => (string) ($cafe['description'] ?? ''),
                'price_per_hour' => (int) ($cafe['price_per_hour'] ?? 0),
                'capacity_max' => (int) ($cafe['capacity_max'] ?? 0),
                'opening_time' => (string) ($cafe['opening_time'] ?? ''),
                'closing_time' => (string) ($cafe['closing_time'] ?? ''),
                'image_url' => (string) ($cafe['image_url'] ?? ''),
                'is_active' => $isActive,
                'has_reservations' => $hasRes,
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
            ?>
            <article class="cafe-card<?= $isActive ? '' : ' cafe-card--inactive' ?>">

                <!-- Imagen -->
                <div class="cafe-card__image-wrapper">
                    <?php if (!empty($cafe['image_url'])): ?>
                        <img
                            src="<?= htmlspecialchars((string) $cafe['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= $cafeName ?>"
                            class="cafe-card__image"
                            loading="lazy">
                    <?php else: ?>
                        <div class="cafe-card__image-placeholder">
                            <i class="bi bi-image" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>

                    <div class="cafe-card__badges">
                        <span class="category-badge <?= htmlspecialchars($catInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="<?= htmlspecialchars($catInfo['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($catInfo['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="cafe-status-badge <?= $isActive ? 'cafe-status-badge--active' : 'cafe-status-badge--inactive' ?>">
                            <?= $isActive ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="cafe-card__body">
                    <h3 class="cafe-card__name"><?= $cafeName ?></h3>
                    <?php if (!empty($cafe['japanese_name'])): ?>
                        <p class="cafe-card__name-jp"><?= htmlspecialchars((string) $cafe['japanese_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <p class="cafe-card__location">
                        <i class="bi bi-geo-alt"></i>
                        <span><?= htmlspecialchars((string) ($cafe['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </p>

                    <div class="mb-2">
                        <span class="animal-badge">
                            <span class="animal-badge__icon"><i class="<?= htmlspecialchars($animalInfo['icon'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                            <span><?= htmlspecialchars($animalInfo['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </div>

                    <!-- Rating -->
                    <div class="rating-display mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= $fullStars ? 'bi-star-fill rating-display__star--filled' : 'bi-star' ?> rating-display__star"></i>
                        <?php endfor; ?>
                        <span class="rating-display__value">(<?= CurrencyFormatting::rating($rating) ?>)</span>
                    </div>

                    <!-- Reservas -->
                    <div class="reservation-indicator <?= $hasRes ? 'reservation-indicator--enabled' : 'reservation-indicator--disabled' ?>">
                        <i class="bi <?= $hasRes ? 'bi-check-circle' : 'bi-x-circle' ?>"></i>
                        <span><?= $hasRes ? 'Acepta reservas' : 'Solo walk-ins' ?></span>
                    </div>

                    <!-- Meta -->
                    <div class="cafe-card__meta">
                        <div class="cafe-card__meta-item">
                            <i class="bi bi-people"></i>
                            <span><?= (int) ($cafe['capacity_max'] ?? 0) ?> personas</span>
                        </div>
                        <div class="cafe-card__meta-item">
                            <i class="bi bi-currency-euro"></i>
                            <span>Desde <strong><?= number_format((int) ($cafe['price_per_hour'] ?? 0)) ?></strong>¥/h</span>
                        </div>
                    </div>
                </div>

                <!-- Footer (acciones) -->
                <div class="cafe-card__footer">
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        @click="openEditModal(<?= $editData ?>)"
                        aria-label="Editar <?= $cafeName ?>"
                        title="Editar">
                        <i class="bi bi-pencil me-1"></i>
                        Editar
                    </button>
                    <button
                        type="button"
                        class="btn <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                        @click="toggleCafeStatus(<?= $cafeId ?>, '<?= $cafeName ?>', <?= $isActive ? 'true' : 'false' ?>)"
                        aria-label="<?= $isActive ? 'Desactivar' : 'Activar' ?> <?= $cafeName ?>"
                        title="<?= $isActive ? 'Desactivar' : 'Activar' ?>">
                        <i class="bi <?= $isActive ? 'bi-pause' : 'bi-play' ?>"></i>
                        <span><?= $isActive ? 'Pausar' : 'Activar' ?></span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        @click="deleteCafe(<?= $cafeId ?>, '<?= $cafeName ?>')"
                        aria-label="Eliminar <?= $cafeName ?>"
                        title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($cafes !== []): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top mt-2">
            <div class="text-muted small">Página <?= (int) $meta['page'] ?></div>
            <?= ViewHelpers::paginationLinks($meta, $currentParams) ?>
        </div>
    <?php endif; ?>

<?php endif; ?>
