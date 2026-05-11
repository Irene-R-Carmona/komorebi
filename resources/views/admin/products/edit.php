<?php

/**
 * Vista: Editar Producto
 * Ruta: GET /admin/menu/{id}/edit
 *
 * @var array $product - Datos del producto
 * @var array $categories - Categorías disponibles
 * @var array $allergens - Alérgenos del sistema
 * @var array $product_allergens - Alérgenos del producto
 */

use App\Core\Csrf;
use App\Core\View;

$product ??= [];
$categories ??= [];
$allergens ??= [];
$product_allergens ??= [];

$productId = (int) ($product['id'] ?? 0);
$productAllergenIds = array_map('intval', array_column($product_allergens, 'id'));

// Config para Alpine.js
$alpineConfig = json_encode([
    'isEdit' => true,
    'productId' => $productId,
    'submitUrl' => "/api/v1/admin/menu/$productId",
    'name' => $product['name'] ?? '',
    'japanese_name' => $product['name_jp'] ?? $product['japanese_name'] ?? '',
    'slug' => $product['slug'] ?? '',
    'description' => $product['description'] ?? '',
    'image_url' => $product['image_url'] ?? '',
    'category_id' => (string) ($product['category_id'] ?? ''),
    'price' => (string) ($product['price'] ?? ''),
    'calories' => (string) ($product['calories'] ?? ''),
    'prep_time' => (string) ($product['prep_time'] ?? ''),
    'is_active' => !empty($product['is_active']),
    'allergens' => $productAllergenIds,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="product-form-container">
    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'pencil-square',
        'title' => 'Editar Producto',
        'breadcrumbs' => [
            ['label' => 'Productos', 'url' => '/admin/menu', 'icon' => 'box-seam'],
            ['label' => e($product['name'] ?? 'Editar')],
        ],
    ]) ?>

    <!-- Formulario -->
    <form
        method="POST"
        action="/api/v1/admin/menu/<?= $productId ?>"
        x-data='productForm(<?= $alpineConfig ?>)'
        @submit.prevent="submitForm"
        :class="{ 'form-submitting': isSubmitting }">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $productId ?>">

        <div class="row">
            <!-- Columna Principal -->
            <div class="col-lg-8">
                <?php include __DIR__ . '/partials/_form-basic-info.php'; ?>
                <?php include __DIR__ . '/partials/_form-allergens.php'; ?>
            </div>

            <!-- Columna Lateral -->
            <div class="col-lg-4">
                <?php include __DIR__ . '/partials/_form-image.php'; ?>
                <?php include __DIR__ . '/partials/_form-details.php'; ?>

                <!-- Metadatos -->
                <div class="form-section">
                    <div class="form-section__header">
                        <h2 class="form-section__title">
                            <i class="bi bi-clock-history"></i>
                            Información del Registro
                        </h2>
                    </div>
                    <div class="form-section__body">
                        <dl class="metadata-list">
                            <dt>Creado</dt>
                            <dd><?= e($product['created_at'] ?? 'N/A') ?></dd>

                            <dt>Última modificación</dt>
                            <dd><?= e($product['updated_at'] ?? 'N/A') ?></dd>
                        </dl>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="form-actions form-actions--sticky">
                    <button
                        type="submit"
                        class="btn btn-primary btn-lg w-100"
                        :disabled="isSubmitting">
                        <template x-if="!isSubmitting">
                            <span><i class="bi bi-check-lg me-2"></i>Guardar Cambios</span>
                        </template>
                        <template x-if="isSubmitting">
                            <span>
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Guardando...
                            </span>
                        </template>
                    </button>
                    <a href="/admin/menu" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-2"></i>Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
