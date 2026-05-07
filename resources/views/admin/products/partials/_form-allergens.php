<?php

/**
 * Partial: Sección de alérgenos
 * Usado en create.php y edit.php
 *
 * Requiere que exista en el scope:
 * - $allergens (array)
 * - $product_allergens (array, opcional)
 */

use App\Core\View;

$allergens ??= [];
$product_allergens ??= [];
$productAllergenIds = array_column($product_allergens, 'id');
?>

<div class="form-section">
    <div class="form-section__header">
        <h2 class="form-section__title">
            <i class="bi bi-exclamation-triangle text-warning"></i>
            Alérgenos
        </h2>
    </div>
    <div class="form-section__body">
        <?php if (!empty($allergens)): ?>
            <p class="text-muted mb-3">
                Selecciona los alérgenos presentes en este producto:
            </p>

            <!-- Acciones rápidas -->
            <div class="mb-3 d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="selectAllAllergens([<?= implode(',', array_column($allergens, 'id')) ?>])">
                    <i class="bi bi-check-all me-1"></i>
                    Seleccionar todos
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="clearAllAllergens()">
                    <i class="bi bi-x-lg me-1"></i>
                    Limpiar selección
                </button>
                <span class="text-muted small align-self-center ms-auto">
                    <span x-text="selectedAllergens.length"></span> seleccionados
                </span>
            </div>

            <div class="allergen-grid">
                <?php foreach ($allergens as $allergen): ?>
                    <?= View::componentToString('components/products/allergen-checkbox', [
                        'allergen' => $allergen,
                        'checked' => in_array($allergen['id'], $productAllergenIds, true),
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= View::componentToString('components/admin/empty-state', [
                'icon' => 'shield-exclamation',
                'title' => 'Sin alérgenos',
                'message' => 'No hay alérgenos registrados en el sistema.',
                'compact' => true,
            ]) ?>
        <?php endif; ?>
    </div>
</div>
