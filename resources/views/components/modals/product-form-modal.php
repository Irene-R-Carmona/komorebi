<?php

/**
 * Modal: Formulario de Producto
 *
 * Componente reutilizable para crear/editar productos del menú.
 * Utiliza Alpine.js para validación y preview de imagen.
 */
?>

<!-- Tabs de navegación -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active"
            id="product-basic-tab"
            data-bs-toggle="tab"
            data-bs-target="#product-basic"
            type="button"
            role="tab">
            <i class="bi bi-info-circle me-2"></i>
            Información Básica
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link"
            id="product-details-tab"
            data-bs-toggle="tab"
            data-bs-target="#product-details"
            type="button"
            role="tab">
            <i class="bi bi-card-list me-2"></i>
            Detalles
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link"
            id="product-kitchen-tab"
            data-bs-toggle="tab"
            data-bs-target="#product-kitchen"
            type="button"
            role="tab">
            <i class="bi bi-shop me-2"></i>
            Cocina (Opcional)
        </button>
    </li>
</ul>

<!-- Contenido de los tabs -->
<div class="tab-content">

    <!-- Tab 1: Información Básica -->
    <div class="tab-pane fade show active" id="product-basic" role="tabpanel">
        <form @submit.prevent="submitProduct">
            <input type="hidden" name="csrf_token" :value="form.csrf_token">
            <input type="hidden" name="id" :value="form.id" x-show="isEditMode">

            <div class="row g-3">
                <!-- Nombre -->
                <div class="col-md-6">
                    <label for="product_name" class="form-label">
                        Nombre del Producto <span class="text-danger">*</span>
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="product_name"
                        x-model="form.name"
                        @input="generateSlug()"
                        placeholder="Ej: Latte de Vainilla"
                        required
                        maxlength="100">
                </div>

                <!-- Nombre Japonés -->
                <div class="col-md-6">
                    <label for="product_japanese_name" class="form-label">
                        Nombre Japonés
                        <small class="text-muted">(Opcional)</small>
                    </label>
                    <input
                        type="text"
                        class="form-control"
                        id="product_japanese_name"
                        x-model="form.japanese_name"
                        placeholder="Ej: バニララテ"
                        maxlength="100">
                </div>

                <!-- Slug (auto-generado) -->
                <div class="col-12">
                    <label for="product_slug" class="form-label">
                        Slug <span class="text-danger">*</span>
                        <small class="text-muted">(generado automáticamente)</small>
                    </label>
                    <input
                        type="text"
                        class="form-control font-monospace"
                        id="product_slug"
                        x-model="form.slug"
                        placeholder="latte-de-vainilla"
                        pattern="[a-z0-9\-]+"
                        required
                        maxlength="100"
                        :readonly="!isEditMode">
                    <small class="text-muted">Solo letras minúsculas, números y guiones</small>
                </div>

                <!-- Descripción -->
                <div class="col-12">
                    <label for="product_description" class="form-label">
                        Descripción <span class="text-danger">*</span>
                    </label>
                    <textarea
                        class="form-control"
                        id="product_description"
                        x-model="form.description"
                        rows="3"
                        placeholder="Describe el producto, sus ingredientes o características especiales..."
                        required
                        maxlength="500"></textarea>
                    <small class="text-muted" x-text="`${form.description.length}/500 caracteres`"></small>
                </div>

                <!-- Categoría -->
                <div class="col-md-6">
                    <label for="product_category_id" class="form-label">
                        Categoría <span class="text-danger">*</span>
                    </label>
                    <select
                        class="form-select"
                        id="product_category_id"
                        x-model.number="form.category_id"
                        required>
                        <option value="">-- Selecciona una categoría --</option>
                        <template x-for="category in categories" :key="category.id">
                            <option :value="category.id" x-text="category.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Tipo de Producto -->
                <div class="col-md-6">
                    <label for="product_type" class="form-label">
                        Tipo <span class="text-danger">*</span>
                    </label>
                    <select
                        class="form-select"
                        id="product_type"
                        x-model="form.product_type"
                        required>
                        <option value="item">Item del Menú</option>
                        <option value="pass">Pase de Experiencia</option>
                    </select>
                </div>

                <!-- Precio -->
                <div class="col-md-6">
                    <label for="product_price" class="form-label">
                        Precio <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input
                            type="number"
                            class="form-control"
                            id="product_price"
                            x-model.number="form.price"
                            min="0"
                            step="50"
                            required>
                        <span class="input-group-text">€</span>
                    </div>
                    <small class="text-muted">Precio en euros (céntimos)</small>
                </div>

                <!-- Estado -->
                <div class="col-md-6">
                    <label class="form-label d-block">Estado</label>
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="product_is_active"
                            x-model="form.is_active"
                            role="switch">
                        <label class="form-check-label" for="product_is_active">
                            <span x-text="form.is_active ? 'Disponible' : 'No disponible'"></span>
                        </label>
                    </div>
                </div>

                <!-- URL de Imagen -->
                <div class="col-12">
                    <label for="product_image_url" class="form-label">
                        URL de Imagen
                        <small class="text-muted">(Opcional)</small>
                    </label>
                    <input
                        type="url"
                        class="form-control"
                        id="product_image_url"
                        x-model="form.image_url"
                        placeholder="https://ejemplo.com/imagen.jpg"
                        maxlength="255">

                    <!-- Preview de imagen -->
                    <div x-show="form.image_url" class="mt-2">
                        <img :src="form.image_url"
                            alt="Preview"
                            class="img-thumbnail"
                            style="max-height: 150px; object-fit: cover;"
                            @error="$event.target.src='/images/products/default.jpg'">
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Tab 2: Detalles -->
    <div class="tab-pane fade" id="product-details" role="tabpanel">
        <div class="row g-3">

            <!-- Tiempo de preparación (solo para items) -->
            <div class="col-md-6" x-show="form.product_type === 'item'">
                <label for="product_prep_time" class="form-label">
                    Tiempo de Preparación
                    <small class="text-muted">(minutos)</small>
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="product_prep_time"
                    x-model.number="form.prep_time"
                    min="0"
                    max="120"
                    placeholder="10">
            </div>

            <!-- Estación de preparación (solo para items) -->
            <div class="col-md-6" x-show="form.product_type === 'item'">
                <label for="product_station" class="form-label">
                    Estación de Preparación
                </label>
                <select
                    class="form-select"
                    id="product_station"
                    x-model="form.station">
                    <option value="">-- Sin asignar --</option>
                    <option value="bar">Bar</option>
                    <option value="kitchen_hot">Cocina Caliente</option>
                    <option value="kitchen_cold">Cocina Fría</option>
                    <option value="bakery">Repostería</option>
                    <option value="assembly">Ensamblaje</option>
                </select>
            </div>

            <!-- Duración (solo para pases) -->
            <div class="col-md-6" x-show="form.product_type === 'pass'">
                <label for="product_duration" class="form-label">
                    Duración
                    <small class="text-muted">(minutos)</small>
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="product_duration"
                    x-model.number="form.duration_minutes"
                    min="15"
                    step="15"
                    placeholder="60">
            </div>

            <!-- Capacidad mínima/máxima (solo para pases) -->
            <div class="col-md-3" x-show="form.product_type === 'pass'">
                <label for="product_min_pax" class="form-label">
                    Personas Mín.
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="product_min_pax"
                    x-model.number="form.min_pax"
                    min="1"
                    max="50">
            </div>

            <div class="col-md-3" x-show="form.product_type === 'pass'">
                <label for="product_max_pax" class="form-label">
                    Personas Máx.
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="product_max_pax"
                    x-model.number="form.max_pax"
                    min="1"
                    max="50">
            </div>

            <!-- Alérgenos (multi-select) -->
            <div class="col-12">
                <label class="form-label">Alérgenos</label>
                <div class="row g-2">
                    <template x-for="allergen in allergenOptions" :key="allergen">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :id="`allergen_${allergen}`"
                                    :value="allergen"
                                    x-model="form.allergens">
                                <label class="form-check-label" :for="`allergen_${allergen}`">
                                    <span x-text="allergen"></span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Atributos (multi-select) -->
            <div class="col-12">
                <label class="form-label">Atributos Especiales</label>
                <div class="row g-2">
                    <template x-for="attr in attributeOptions" :key="attr.value">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :id="`attr_${attr.value}`"
                                    :value="attr.value"
                                    x-model="form.attributes">
                                <label class="form-check-label" :for="`attr_${attr.value}`">
                                    <span x-text="attr.icon"></span>
                                    <span x-text="attr.label"></span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>

    <!-- Tab 3: Cocina (Opcional) -->
    <div class="tab-pane fade" id="product-kitchen" role="tabpanel">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Esta información es opcional y se utiliza en el KDS (Kitchen Display System)
        </div>

        <div class="row g-3">
            <!-- Pasos de la receta -->
            <div class="col-12">
                <label for="product_recipe_steps" class="form-label">
                    Pasos de Preparación
                </label>
                <textarea
                    class="form-control font-monospace"
                    id="product_recipe_steps"
                    x-model="form.recipe_steps"
                    rows="6"
                    placeholder="1. Calentar agua a 85°C&#10;2. Agregar 18g de café molido&#10;3. Esperar 4 minutos&#10;4. Servir en taza precalentada"></textarea>
                <small class="text-muted">Una instrucción por línea</small>
            </div>

            <!-- Lista de ingredientes -->
            <div class="col-12">
                <label for="product_ingredients" class="form-label">
                    Ingredientes
                    <small class="text-muted">(JSON array)</small>
                </label>
                <textarea
                    class="form-control font-monospace"
                    id="product_ingredients"
                    x-model="form.ingredients_list"
                    rows="4"
                    placeholder='["Café arábica 18g", "Agua 250ml", "Leche 100ml"]'></textarea>
                <small class="text-muted">Formato: array JSON con strings</small>
            </div>

            <!-- Verificación crítica -->
            <div class="col-12">
                <label for="product_critical_check" class="form-label">
                    Verificación Crítica
                    <small class="text-muted">(Punto de control de calidad)</small>
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="product_critical_check"
                    x-model="form.critical_check"
                    placeholder="Ej: Verificar temperatura de la leche (65-70°C)"
                    maxlength="255">
            </div>
        </div>
    </div>

</div>

<!-- Errores de validación -->
<div x-show="formErrors.length > 0" class="alert alert-danger mt-3" role="alert">
    <strong>
        <i class="bi bi-exclamation-triangle me-2"></i>
        Errores de validación:
    </strong>
    <ul class="mb-0 mt-2">
        <template x-for="error in formErrors" :key="error">
            <li x-text="error"></li>
        </template>
    </ul>
</div>

<!-- Botones de acción -->
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="bi bi-x-lg me-2"></i>
        Cancelar
    </button>
    <button type="button"
        class="btn btn-primary"
        @click="submitProduct()"
        :disabled="isSubmitting">
        <span x-show="!isSubmitting">
            <i class="bi me-2" :class="isEditMode ? 'bi-save' : 'bi-plus-lg'"></i>
            <span x-text="isEditMode ? 'Actualizar' : 'Crear'"></span>
        </span>
        <span x-show="isSubmitting">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Procesando...
        </span>
    </button>
</div>
