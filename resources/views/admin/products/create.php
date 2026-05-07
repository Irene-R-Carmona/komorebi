<?php
/**
 * Vista: Crear Producto
 * Ruta: GET /admin/productos/crear
 *
 * @var array $categories - Categorías disponibles
 * @var array $allergens - Alérgenos del sistema
 */

use App\Core\Csrf;
use App\Core\View;

$categories ??= [];
$allergens ??= [];

// Config para Alpine.js
$alpineConfig = json_encode([
    'isEdit' => false,
    'submitUrl' => '/admin/productos/crear',
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="product-form-container">
    <!-- Breadcrumb -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'plus-circle',
        'title' => 'Nuevo Producto',
        'breadcrumbs' => [
            ['label' => 'Productos', 'url' => '/admin/menu', 'icon' => 'box-seam'],
            ['label' => 'Nuevo Producto'],
        ],
    ]) ?>

    <!-- Formulario -->
    <form
            method="POST"
            action="/admin/productos/crear"
            x-data='productForm(<?= $alpineConfig ?>)'
            @submit.prevent="submitForm"
            :class="{ 'form-submitting': isSubmitting }"
    >
        <?= Csrf::field() ?>

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

                <!-- Acciones -->
                <div class="form-actions form-actions--sticky">
                    <button
                            type="submit"
                            class="btn btn-primary btn-lg w-100"
                            :disabled="isSubmitting"
                    >
                        <template x-if="!isSubmitting">
                            <span><i class="bi bi-check-lg me-2"></i>Crear Producto</span>
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