<?php
/**
 * Partial: Detalles adicionales
 * Usado en create.php y edit.php
 *
 * Requiere que exista en el scope:
 * - $product (array, opcional en create)
 */

$product ??= [];
?>

<div class="form-section">
    <div class="form-section__header">
        <h2 class="form-section__title">
            <i class="bi bi-sliders"></i>
            Detalles Adicionales
        </h2>
    </div>
    <div class="form-section__body">
        <!-- Calorías -->
        <div class="mb-3">
            <label class="form-label" for="calories">Calorías</label>
            <div class="input-group">
                <input
                        type="number"
                        class="form-control"
                        id="calories"
                        name="calories"
                        x-model="form.calories"
                        min="0"
                        placeholder="0"
                >
                <span class="input-group-text">kcal</span>
            </div>
        </div>

        <!-- Tiempo de preparación -->
        <div class="mb-3">
            <label class="form-label" for="prep_time">Tiempo de preparación</label>
            <div class="input-group">
                <input
                        type="number"
                        class="form-control"
                        id="prep_time"
                        name="prep_time"
                        x-model="form.prep_time"
                        min="0"
                        placeholder="0"
                >
                <span class="input-group-text">min</span>
            </div>
        </div>

        <!-- Disponibilidad -->
        <div class="form-check form-switch">
            <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="is_active"
                    name="is_active"
                    x-model="form.is_active"
            >
            <label class="form-check-label" for="is_active">
                <strong>Disponible</strong>
                <span class="d-block text-muted small">
                    El producto aparecerá en el menú público
                </span>
            </label>
        </div>
    </div>
</div>