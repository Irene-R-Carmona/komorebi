<?php

/**
 * Partial: Modal de café (crear/editar)
 */
?>

<div
    class="modal fade"
    id="cafeModal"
    tabindex="-1"
    aria-labelledby="cafeModalLabel"
    aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form @submit.prevent="submitCafe">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="cafeModalLabel">
                        <i class="bi me-2" :class="isEditMode ? 'bi-pencil' : 'bi-shop'"></i>
                        <span x-text="isEditMode ? 'Editar Café' : 'Nuevo Café'"></span>
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <!-- Errores -->
                    <template x-if="formErrors.length > 0">
                        <div class="alert alert-danger py-2 mb-3">
                            <ul class="mb-0 ps-3">
                                <template x-for="error in formErrors" :key="error">
                                    <li x-text="error" class="small"></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <div class="row">
                        <!-- Columna izquierda -->
                        <div class="col-md-8">
                            <!-- Información básica -->
                            <div class="cafe-modal__section">
                                <h6 class="cafe-modal__section-title">
                                    <i class="bi bi-info-circle"></i>
                                    Información básica
                                </h6>

                                <div class="row">
                                    <!-- Nombre -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form-label-required" for="cafeName">
                                            Nombre
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="cafeName"
                                            x-model="form.name"
                                            @input="generateSlug"
                                            required
                                            maxlength="100"
                                            placeholder="Ej: Neko Paradise">
                                    </div>

                                    <!-- Nombre japonés -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="cafeNameJp">
                                            Nombre en Japonés
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="cafeNameJp"
                                            x-model="form.japanese_name"
                                            maxlength="100"
                                            placeholder="猫カフェ">
                                    </div>
                                </div>

                                <!-- Slug -->
                                <div class="mb-3">
                                    <label class="form-label" for="cafeSlug">
                                        Slug (URL)
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="cafeSlug"
                                        x-model="form.slug"
                                        pattern="[a-z0-9-]+"
                                        placeholder="neko-paradise">
                                    <p class="form-hint">Se genera automáticamente del nombre.</p>
                                </div>

                                <!-- Ubicación -->
                                <div class="mb-3">
                                    <label class="form-label form-label-required" for="cafeLocation">
                                        Ubicación
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="cafeLocation"
                                        x-model="form.location"
                                        required
                                        maxlength="200"
                                        placeholder="Ej: Shibuya, Tokyo">
                                </div>

                                <!-- Descripción -->
                                <div class="mb-3">
                                    <label class="form-label form-label-required" for="cafeDescription">
                                        Descripción
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="cafeDescription"
                                        x-model="form.description"
                                        required
                                        rows="3"
                                        maxlength="500"
                                        placeholder="Describe el ambiente y experiencia del café..."></textarea>
                                </div>
                            </div>

                            <!-- Categoría y Animal -->
                            <div class="cafe-modal__section">
                                <h6 class="cafe-modal__section-title">
                                    <i class="bi bi-tags"></i>
                                    Clasificación
                                </h6>

                                <div class="row">
                                    <!-- Categoría -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form-label-required" for="cafeCategory">
                                            Categoría
                                        </label>
                                        <select
                                            class="form-select"
                                            id="cafeCategory"
                                            x-model="form.category"
                                            required>
                                            <option value="">Seleccionar...</option>
                                            <option value="lounge">🛋️ Lounge</option>
                                            <option value="playroom">🎮 Playroom</option>
                                            <option value="farm">🌾 Farm</option>
                                            <option value="zen">🧘 Zen</option>
                                        </select>
                                    </div>

                                    <!-- Tipo de animal -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form-label-required" for="cafeAnimalType">
                                            Tipo de Animal
                                        </label>
                                        <select
                                            class="form-select"
                                            id="cafeAnimalType"
                                            x-model="form.animal_type"
                                            required>
                                            <option value="">Seleccionar...</option>
                                            <option value="cat">🐱 Gatos</option>
                                            <option value="dog">🐶 Perros</option>
                                            <option value="rabbit">🐰 Conejos</option>
                                            <option value="bird">🦜 Aves</option>
                                            <option value="hedgehog">🦔 Erizos</option>
                                            <option value="capybara">🦫 Capibaras</option>
                                            <option value="mixed">🐾 Mixto</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Operación -->
                            <div class="cafe-modal__section">
                                <h6 class="cafe-modal__section-title">
                                    <i class="bi bi-gear"></i>
                                    Operación
                                </h6>

                                <div class="row">
                                    <!-- Precio por hora -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="cafePrice">
                                            Precio por hora
                                        </label>
                                        <div class="price-input">
                                            <span class="price-input__symbol">¥</span>
                                            <input
                                                type="number"
                                                class="form-control"
                                                id="cafePrice"
                                                x-model.number="form.price_per_hour"
                                                min="0"
                                                step="100">
                                        </div>
                                    </div>

                                    <!-- Capacidad -->
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="cafeCapacity">
                                            Capacidad máxima
                                        </label>
                                        <div class="capacity-input">
                                            <input
                                                type="range"
                                                class="form-range capacity-input__slider"
                                                id="cafeCapacity"
                                                x-model.number="form.capacity_max"
                                                min="1"
                                                max="100">
                                            <span class="capacity-input__value" x-text="form.capacity_max"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Horario -->
                                <div class="mb-3">
                                    <label class="form-label">Horario de operación</label>
                                    <div class="hours-input-group">
                                        <input
                                            type="time"
                                            class="form-control"
                                            x-model="form.opening_time"
                                            style="max-width: 140px;">
                                        <span class="hours-input-group__separator">a</span>
                                        <input
                                            type="time"
                                            class="form-control"
                                            x-model="form.closing_time"
                                            style="max-width: 140px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha -->
                        <div class="col-md-4">
                            <!-- Imagen -->
                            <div class="cafe-modal__section">
                                <h6 class="cafe-modal__section-title">
                                    <i class="bi bi-image"></i>
                                    Imagen
                                </h6>

                                <!-- Preview -->
                                <div
                                    class="cafe-modal__image-preview"
                                    :class="{ 'cafe-modal__image-preview--has-image': form.image_url && !imageError }">
                                    <template x-if="form.image_url && !imageError">
                                        <img :src="form.image_url" alt="Preview">
                                    </template>
                                    <template x-if="!form.image_url || imageError">
                                        <div class="cafe-modal__image-placeholder">
                                            <i class="bi bi-image"></i>
                                            <span class="small" x-text="imageError ? 'Error al cargar' : 'Sin imagen'"></span>
                                        </div>
                                    </template>
                                </div>

                                <!-- URL -->
                                <div class="mb-3">
                                    <label class="form-label" for="cafeImageUrl">
                                        URL de imagen
                                    </label>
                                    <input
                                        type="url"
                                        class="form-control"
                                        id="cafeImageUrl"
                                        x-model="form.image_url"
                                        @input.debounce.500ms="validateImageUrl"
                                        placeholder="https://...">
                                </div>
                            </div>

                            <!-- Estado -->
                            <div class="cafe-modal__section">
                                <h6 class="cafe-modal__section-title">
                                    <i class="bi bi-toggle-on"></i>
                                    Estado
                                </h6>

                                <!-- Activo -->
                                <div class="form-check form-switch mb-3">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="cafeActive"
                                        x-model="form.is_active">
                                    <label class="form-check-label" for="cafeActive">
                                        <strong>Café activo</strong>
                                        <span class="d-block text-muted small">
                                            Visible en el sitio público
                                        </span>
                                    </label>
                                </div>

                                <!-- Reservas -->
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="cafeReservations"
                                        x-model="form.has_reservations">
                                    <label class="form-check-label" for="cafeReservations">
                                        <strong>Acepta reservas</strong>
                                        <span class="d-block text-muted small">
                                            Habilitar sistema de reservas online
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="isSubmitting">
                        <template x-if="!isSubmitting">
                            <span>
                                <i class="bi bi-check-lg me-1"></i>
                                <span x-text="isEditMode ? 'Guardar Cambios' : 'Crear Café'"></span>
                            </span>
                        </template>
                        <template x-if="isSubmitting">
                            <span>
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Guardando...
                            </span>
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
