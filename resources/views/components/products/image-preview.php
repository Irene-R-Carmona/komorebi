<?php
/**
 * Componente: Preview de Imagen con URL
 *
 * Campo de URL con preview de imagen en tiempo real.
 * Compatible con Alpine.js para validación reactiva.
 *
 * @var string $name - Nombre del campo
 * @var string $label - Etiqueta del campo
 * @var string $value - Valor actual (para edición)
 * @var string $placeholder - Placeholder del input
 */

$name ??= 'image_url';
$label ??= 'URL de imagen';
$value ??= '';
$placeholder ??= 'https://ejemplo.com/imagen.jpg';
?>

<!-- Input de URL -->
<div class="mb-3">
    <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
    <input
            type="url"
            class="form-control"
            id="<?= e($name) ?>"
            name="<?= e($name) ?>"
            x-model="form.image_url"
            @input.debounce.500ms="validateImageUrl"
            placeholder="<?= e($placeholder) ?>"
    >
</div>

<!-- Preview -->
<div
        class="image-preview"
        :class="{
        'image-preview--has-image': form.image_url && !imageError,
        'image-preview--error': form.image_url && imageError
    }"
>
    <!-- Estado: Sin imagen -->
    <template x-if="!form.image_url">
        <div class="image-preview__placeholder">
            <i class="bi bi-image image-preview__placeholder-icon"></i>
            <span class="image-preview__placeholder-text">La imagen aparecerá aquí</span>
        </div>
    </template>

    <!-- Estado: Error de carga -->
    <template x-if="form.image_url && imageError">
        <div class="image-preview__placeholder">
            <i class="bi bi-exclamation-triangle image-preview__placeholder-icon text-danger"></i>
            <span class="image-preview__placeholder-text text-danger">No se pudo cargar la imagen</span>
        </div>
    </template>

    <!-- Estado: Imagen cargada -->
    <template x-if="form.image_url && !imageError">
        <div class="position-relative w-100 text-center">
            <img
                    :src="form.image_url"
                    class="image-preview__img"
                    alt="Vista previa"
            >
            <button
                    type="button"
                    class="image-preview__remove"
                    @click="clearImage"
                    title="Quitar imagen"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </template>
</div>