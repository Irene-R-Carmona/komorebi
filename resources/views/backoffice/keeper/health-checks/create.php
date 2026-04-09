<?php

/**
 * Formulario de Chequeo de Salud Animal
 *
 * Checklist interactivo para registrar el estado de salud diario de un animal.
 */
?>

<div class="container py-4">
    <!-- Header con información del animal -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-1">
                                <i class="bi bi-clipboard2-pulse text-primary"></i>
                                Chequeo de Salud
                            </h1>
                            <h5 class="text-muted mb-0">
                                <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="badge bg-info ms-2"><?= htmlspecialchars($animal['species_type'], ENT_QUOTES, 'UTF-8') ?></span>
                            </h5>
                            <p class="text-muted small mb-0 mt-1">
                                Edad: <?= $animal['age'] ?? 'N/D' ?> años •
                                Estado: <span class="badge bg-secondary"><?= htmlspecialchars($animal['current_status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="/keeper/health-checks" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver al Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario de Chequeo -->
    <form method="POST" action="/keeper/health-checks" id="healthCheckForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="animal_id" value="<?= $animal['id'] ?>">

        <div class="row">
            <!-- Columna Izquierda: Métricas Físicas -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-thermometer-half"></i> Métricas Físicas</h5>
                    </div>
                    <div class="card-body">
                        <!-- Peso -->
                        <div class="mb-3">
                            <label for="weight_kg" class="form-label">Peso (kg)</label>
                            <input type="number" step="0.01" min="0.1" max="50" class="form-control" id="weight_kg" name="weight_kg" placeholder="Ej: 4.50">
                            <small class="form-text text-muted">Deja en blanco si no mediste el peso</small>
                        </div>

                        <!-- Temperatura -->
                        <div class="mb-3">
                            <label for="temperature_c" class="form-label">Temperatura Corporal (°C)</label>
                            <input type="number" step="0.1" min="30" max="45" class="form-control" id="temperature_c" name="temperature_c" placeholder="Ej: 38.5">
                            <small class="form-text text-muted">Normal: 37.5 - 39.2°C (varía según especie)</small>
                        </div>

                        <!-- Condición del Pelaje -->
                        <div class="mb-3">
                            <label for="coat_condition" class="form-label">Condición del Pelaje</label>
                            <select class="form-select" id="coat_condition" name="coat_condition">
                                <option value="excellent">Excelente - Brillante y suave</option>
                                <option value="good" selected>Buena - Normal</option>
                                <option value="fair">Regular - Con opacidad</option>
                                <option value="poor">Pobre - Áspero o con pérdida</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Observaciones Generales -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> Estado General</h5>
                    </div>
                    <div class="card-body">
                        <!-- Apetito -->
                        <div class="mb-3">
                            <label for="appetite" class="form-label">Apetito</label>
                            <select class="form-select" id="appetite" name="appetite">
                                <option value="normal" selected>Normal - Come adecuadamente</option>
                                <option value="reduced">Reducido - Come menos de lo habitual</option>
                                <option value="none">Ninguno - No ha comido</option>
                            </select>
                        </div>

                        <!-- Nivel de Energía -->
                        <div class="mb-3">
                            <label for="energy_level" class="form-label">Nivel de Energía</label>
                            <select class="form-select" id="energy_level" name="energy_level">
                                <option value="high">Alto - Muy activo</option>
                                <option value="normal" selected>Normal - Activo habitual</option>
                                <option value="low">Bajo - Letárgico</option>
                            </select>
                        </div>

                        <!-- Comprobaciones Booleanas (Switches) -->
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="eyes_clear" name="eyes_clear" value="1" checked>
                            <label class="form-check-label" for="eyes_clear">
                                <i class="bi bi-eye"></i> Ojos claros sin secreciones
                            </label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="breathing_normal" name="breathing_normal" value="1" checked>
                            <label class="form-check-label" for="breathing_normal">
                                <i class="bi bi-lungs"></i> Respiración normal
                            </label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="mobility_normal" name="mobility_normal" value="1" checked>
                            <label class="form-check-label" for="mobility_normal">
                                <i class="bi bi-activity"></i> Movilidad normal sin cojera
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notas Adicionales -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-journal-text"></i> Notas Adicionales</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Observaciones del Keeper</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Escribe aquí cualquier observación relevante sobre el comportamiento, interacciones, alimentación, etc."></textarea>
                            <small class="form-text text-muted">Opcional - Detalles que puedan ser importantes para el seguimiento</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    Se detectarán automáticamente alertas basadas en umbrales de salud.
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/keeper/health-checks" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save"></i> Guardar Chequeo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Script para validación y UX mejorada -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('healthCheckForm');
        const temperatureInput = document.getElementById('temperature_c');
        const weightInput = document.getElementById('weight_kg');

        // Validación visual de temperatura
        if (temperatureInput) {
            temperatureInput.addEventListener('input', function() {
                const temp = parseFloat(this.value);
                if (temp > 39.5) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else if (temp >= 30 && temp <= 39.5) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });
        }

        // Validación visual de peso
        if (weightInput) {
            weightInput.addEventListener('input', function() {
                const weight = parseFloat(this.value);
                if (weight < 0.1 || weight > 50) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else if (weight >= 0.1) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });
        }

        // Confirmación antes de enviar
        form.addEventListener('submit', function(e) {
            const appetite = document.getElementById('appetite').value;
            const energyLevel = document.getElementById('energy_level').value;
            const breathing = document.getElementById('breathing_normal').checked;

            // Alerta si hay signos preocupantes
            if (appetite === 'none' || energyLevel === 'low' || !breathing) {
                const confirmed = confirm('Has marcado signos de salud preocupantes. ¿Confirmas que deseas guardar este chequeo?');
                if (!confirmed) {
                    e.preventDefault();
                }
            }
        });
    });
</script>
