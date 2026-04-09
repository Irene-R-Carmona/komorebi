<?php
/**
 * Partial: Sección de imagen
 * Usado en create.php y edit.php
 *
 * Requiere que exista en el scope:
 * - $product (array, opcional en create)
 */

use App\Core\View;

$product ??= [];
?>

<div class="form-section">
    <div class="form-section__header">
        <h2 class="form-section__title">
            <i class="bi bi-image"></i>
            Imagen
        </h2>
    </div>
    <div class="form-section__body">
        <?= View::componentToString('products/image-preview', [
            'name' => 'image_url',
            'label' => 'URL de imagen',
            'value' => $product['image_url'] ?? '',
        ]) ?>
    </div>
</div>