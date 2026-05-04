<?php

/**
 * Partial: Información básica del producto
 * Usado en create.php y edit.php
 *
 * Requiere que exista en el scope:
 * - $categories (array)
 * - $product (array, opcional en create)
 */

$product ??= [];
$categories ??= [];
?>

<div class="form-section">
    <div class="form-section__header">
        <h2 class="form-section__title">
            <i class="bi bi-info-circle"></i>
            Información Básica
        </h2>
    </div>
    <div class="form-section__body">
        <!-- Nombre -->
        <div class="mb-3">
            <label class="form-label required" for="name">Nombre</label>
            <input
                type="text"
                class="form-control"
                id="name"
                name="name"
                x-model="form.name"
                @input="generateSlug"
                required
                autocomplete="off"
                maxlength="100"
                placeholder="Ej: Matcha Latte">
        </div>

        <!-- Nombre Japonés -->
        <div class="mb-3">
            <label class="form-label" for="japanese_name">Nombre en Japonés</label>
            <input
                type="text"
                class="form-control"
                id="japanese_name"
                name="japanese_name"
                x-model="form.japanese_name"
                maxlength="100"
                placeholder="抹茶ラテ">
            <p class="form-hint">Opcional. Se mostrará junto al nombre principal.</p>
        </div>

        <!-- Slug -->
        <div class="mb-3">
            <label class="form-label" for="slug">Slug (URL)</label>
            <div class="slug-field">
                <input
                    type="text"
                    class="form-control"
                    id="slug"
                    name="slug"
                    x-model="form.slug"
                    @input="onSlugInput"
                    pattern="[a-z0-9-]+"
                    placeholder="matcha-latte">
                <span class="slug-field__indicator" x-show="!slugManuallyEdited">
                    <i class="bi bi-magic"></i> Auto
                </span>
            </div>
            <p class="form-hint">
                Se genera automáticamente del nombre.
                <button
                    type="button"
                    class="btn btn-link btn-sm p-0"
                    x-show="slugManuallyEdited"
                    @click="resetSlugMode">
                    Restaurar auto-generación
                </button>
            </p>
        </div>

        <!-- Descripción -->
        <div class="mb-3">
            <label class="form-label" for="description">Descripción</label>
            <textarea
                class="form-control"
                id="description"
                name="description"
                x-model="form.description"
                rows="3"
                maxlength="500"
                placeholder="Describe el producto brevemente..."></textarea>
        </div>

        <!-- Categoría y Precio -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label required" for="category_id">Categoría</label>
                <select
                    class="form-select"
                    id="category_id"
                    name="category_id"
                    x-model="form.category_id"
                    required>
                    <option value="">Seleccionar categoría...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>">
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label required" for="price">Precio (¥)</label>
                <input
                    type="number"
                    class="form-control"
                    id="price"
                    name="price"
                    x-model="form.price"
                    min="0"
                    step="1"
                    required
                    placeholder="0">
            </div>
        </div>
    </div>
</div>
