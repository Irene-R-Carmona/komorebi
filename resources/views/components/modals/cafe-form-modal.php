<?php

/**
 * Modal: Crear/Editar Café
 *
 * Formulario de café integrado con Alpine.js
 * Usado en admin/cafes.php
 */
?>

<!-- Contenido del formulario (usado dentro de modal) -->
<form @submit.prevent="submitCafe" id="cafeForm">
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" x-model="form.csrf_token">

    <!-- Modo edición (hidden) -->
    <input type="hidden" name="cafe_id" x-model="form.id">

    <!-- Alerta de errores -->
    <div x-show="formErrors.length > 0"
        x-transition
        class="alert alert-danger alert-dismissible fade show mb-3"
        role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Error:</strong>
        <ul class="mb-0 mt-2">
            <template x-for="error in formErrors" :key="error">
                <li x-text="error"></li>
            </template>
        </ul>
        <button type="button" class="btn-close" @click="formErrors = []"></button>
    </div>

    <!-- Tabs de secciones -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#basicInfo">
                <i class="bi bi-info-circle me-1"></i>Información Básica
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#details">
                <i class="bi bi-gear me-1"></i>Detalles
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#schedule">
                <i class="bi bi-clock me-1"></i>Horarios
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Tab 1: Información Básica -->
        <div class="tab-pane fade show active" id="basicInfo">
            <!-- Nombre -->
            <div class="mb-3">
                <label for="cafeName" class="form-label">
                    Nombre del Café <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="cafeName"
                    name="name"
                    x-model="form.name"
                    @input="generateSlug"
                    required
                    maxlength="100"
                    placeholder="Ej: Neko Lounge Shibuya">
            </div>

            <!-- Nombre Japonés -->
            <div class="mb-3">
                <label for="cafeJapaneseName" class="form-label">
                    Nombre en Japonés
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="cafeJapaneseName"
                    name="japanese_name"
                    x-model="form.japanese_name"
                    maxlength="100"
                    placeholder="ねこラウンジ渋谷">
                <small class="form-text text-muted">Opcional</small>
            </div>

            <!-- Slug -->
            <div class="mb-3">
                <label for="cafeSlug" class="form-label">
                    Slug (URL amigable) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text">/cafes/</span>
                    <input
                        type="text"
                        class="form-control"
                        id="cafeSlug"
                        name="slug"
                        x-model="form.slug"
                        required
                        pattern="[a-z0-9\-]+"
                        maxlength="100"
                        placeholder="neko-lounge-shibuya">
                </div>
                <small class="form-text text-muted">
                    Solo letras minúsculas, números y guiones. Se genera automáticamente.
                </small>
            </div>

            <!-- Ubicación -->
            <div class="mb-3">
                <label for="cafeLocation" class="form-label">
                    Ubicación <span class="text-danger">*</span>
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="cafeLocation"
                    name="location"
                    x-model="form.location"
                    required
                    maxlength="255"
                    placeholder="Shibuya, Tokio">
            </div>

            <!-- Descripción -->
            <div class="mb-3">
                <label for="cafeDescription" class="form-label">
                    Descripción <span class="text-danger">*</span>
                </label>
                <textarea
                    class="form-control"
                    id="cafeDescription"
                    name="description"
                    x-model="form.description"
                    rows="4"
                    required
                    maxlength="1000"
                    placeholder="Describe el café, su ambiente y características únicas..."></textarea>
                <small class="form-text text-muted">
                    <span x-text="form.description.length"></span>/1000 caracteres
                </small>
            </div>
        </div>

        <!-- Tab 2: Detalles -->
        <div class="tab-pane fade" id="details">
            <!-- Categoría -->
            <div class="mb-3">
                <label for="cafeCategory" class="form-label">
                    Categoría <span class="text-danger">*</span>
                </label>
                <select
                    class="form-select"
                    id="cafeCategory"
                    name="category"
                    x-model="form.category"
                    required>
                    <option value="">-- Selecciona una categoría --</option>
                    <option value="lounge">🛋️ Lounge</option>
                    <option value="playroom">🎮 Playroom</option>
                    <option value="farm">🌾 Farm</option>
                    <option value="zen">🧘 Zen</option>
                </select>
                <small class="form-text text-muted">
                    Define el estilo y ambiente del café
                </small>
            </div>

            <!-- Tipo de Animal -->
            <div class="mb-3">
                <label for="cafeAnimalType" class="form-label">
                    Tipo de Animal <span class="text-danger">*</span>
                </label>
                <select
                    class="form-select"
                    id="cafeAnimalType"
                    name="animal_type"
                    x-model="form.animal_type"
                    required>
                    <option value="">-- Selecciona el tipo de animal --</option>
                    <option value="cat">🐱 Gatos</option>
                    <option value="dog">🐶 Perros</option>
                    <option value="rabbit">🐰 Conejos</option>
                    <option value="bird">🦜 Aves</option>
                    <option value="hedgehog">🦔 Erizos</option>
                    <option value="mixed">🐾 Mixto</option>
                </select>
            </div>

            <!-- Precio por Hora -->
            <div class="mb-3">
                <label for="cafePricePerHour" class="form-label">
                    Precio por Hora (¥) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text">¥</span>
                    <input
                        type="number"
                        class="form-control"
                        id="cafePricePerHour"
                        name="price_per_hour"
                        x-model.number="form.price_per_hour"
                        required
                        min="0"
                        step="100"
                        placeholder="1000">
                    <span class="input-group-text">/ hora</span>
                </div>
            </div>

            <!-- Capacidad Máxima -->
            <div class="mb-3">
                <label for="cafeCapacityMax" class="form-label">
                    Capacidad Máxima <span class="text-danger">*</span>
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="cafeCapacityMax"
                    name="capacity_max"
                    x-model.number="form.capacity_max"
                    required
                    min="1"
                    max="200"
                    placeholder="20">
                <small class="form-text text-muted">
                    Número máximo de visitantes simultáneos
                </small>
            </div>

            <!-- Imagen URL -->
            <div class="mb-3">
                <label for="cafeImageUrl" class="form-label">
                    URL de Imagen
                </label>
                <input
                    type="url"
                    class="form-control"
                    id="cafeImageUrl"
                    name="image_url"
                    x-model="form.image_url"
                    placeholder="https://ejemplo.com/imagen.jpg">
                <small class="form-text text-muted">
                    URL completa de la imagen principal del café
                </small>

                <!-- Preview de imagen -->
                <div x-show="form.image_url" class="mt-2">
                    <img :src="form.image_url"
                        class="img-thumbnail modal-img-preview"
                        alt="Preview"
                        @error="form.image_url = ''">
                </div>
            </div>
        </div>

        <!-- Tab 3: Horarios -->
        <div class="tab-pane fade" id="schedule">
            <div class="row">
                <!-- Hora de Apertura -->
                <div class="col-md-6 mb-3">
                    <label for="cafeOpeningTime" class="form-label">
                        Hora de Apertura <span class="text-danger">*</span>
                    </label>
                    <input
                        type="time"
                        class="form-control"
                        id="cafeOpeningTime"
                        name="opening_time"
                        x-model="form.opening_time"
                        required>
                </div>

                <!-- Hora de Cierre -->
                <div class="col-md-6 mb-3">
                    <label for="cafeClosingTime" class="form-label">
                        Hora de Cierre <span class="text-danger">*</span>
                    </label>
                    <input
                        type="time"
                        class="form-control"
                        id="cafeClosingTime"
                        name="closing_time"
                        x-model="form.closing_time"
                        required>
                </div>
            </div>

            <!-- Switches de Estado -->
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="cafeIsActive"
                        x-model="form.is_active">
                    <label class="form-check-label" for="cafeIsActive">
                        Café activo y visible
                    </label>
                </div>
                <small class="form-text text-muted d-block">
                    Los cafés inactivos no aparecen en el catálogo público
                </small>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="cafeHasReservations"
                        x-model="form.has_reservations">
                    <label class="form-check-label" for="cafeHasReservations">
                        Permite reservas online
                    </label>
                </div>
                <small class="form-text text-muted d-block">
                    Si está desactivado, el café solo acepta walk-ins
                </small>
            </div>
        </div>
    </div>

    <!-- Botones del formulario -->
    <div class="modal-footer px-0 pb-0 mt-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" :disabled="isSubmitting">
            Cancelar
        </button>
        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
            <span x-show="!isSubmitting">
                <i class="bi" :class="isEditMode ? 'bi-save' : 'bi-plus-lg'"></i>
                <span x-text="isEditMode ? 'Actualizar Café' : 'Crear Café'"></span>
            </span>
            <span x-show="isSubmitting">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Guardando...
            </span>
        </button>
    </div>
</form>
