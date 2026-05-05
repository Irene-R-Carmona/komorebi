<?php

declare(strict_types=1);

/**
 * Vista: Configuración del Café (Manager)
 *
 * @var array  $cafe       Datos del café asignado
 * @var string $csrf_token Token CSRF
 * @var string $titulo
 */

$alpineConfig = json_encode([
    'csrfToken' => $csrf_token,
    'openingTime' => substr($cafe['opening_time'] ?? '', 0, 5),
    'closingTime' => substr($cafe['closing_time'] ?? '', 0, 5),
    'capacityMax' => (int) ($cafe['capacity_max'] ?? 1),
    'description' => $cafe['description'] ?? '',
    'pricePerHour' => (int) ($cafe['price_per_hour'] ?? 0),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='managerCafe(<?= $alpineConfig ?>)' x-cloak>

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="page-header__content">
            <h1 class="page-header__title"><?= e($titulo) ?></h1>
            <p class="page-header__subtitle">Gestiona la configuración de <?= e($cafe['name'] ?? 'tu café') ?></p>
        </div>
    </div>

    <!-- Feedback message -->
    <div x-show="showMessage" x-transition
        :class="'message ' + messageType"
        x-text="message"
        role="alert"
        aria-live="polite">
    </div>

    <!-- Tabs -->
    <div class="card-admin">
        <div class="tabs" role="tablist">
            <button type="button" role="tab" :aria-selected="activeTab === 'info'"
                @click="activeTab = 'info'"
                :class="{'active': activeTab === 'info'}">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i> Información
            </button>
            <button type="button" role="tab" :aria-selected="activeTab === 'horarios'"
                @click="activeTab = 'horarios'"
                :class="{'active': activeTab === 'horarios'}">
                <i class="bi bi-clock me-1" aria-hidden="true"></i> Horarios
            </button>
            <button type="button" role="tab" :aria-selected="activeTab === 'capacidad'"
                @click="activeTab = 'capacidad'"
                :class="{'active': activeTab === 'capacidad'}">
                <i class="bi bi-people me-1" aria-hidden="true"></i> Capacidad
            </button>
            <button type="button" role="tab" :aria-selected="activeTab === 'config'"
                @click="activeTab = 'config'"
                :class="{'active': activeTab === 'config'}">
                <i class="bi bi-gear me-1" aria-hidden="true"></i> Configuración
            </button>
        </div>

        <!-- Tab: Información -->
        <div x-show="activeTab === 'info'" class="tab-content" role="tabpanel">
            <dl class="row g-3 mb-0">
                <dt class="col-sm-3">Nombre</dt>
                <dd class="col-sm-9"><?= e($cafe['name'] ?? '') ?></dd>

                <dt class="col-sm-3">Nombre japonés</dt>
                <dd class="col-sm-9"><?= e($cafe['japanese_name'] ?? '—') ?></dd>

                <dt class="col-sm-3">Ubicación</dt>
                <dd class="col-sm-9"><?= e($cafe['location'] ?? '—') ?></dd>

                <dt class="col-sm-3">Categoría</dt>
                <dd class="col-sm-9"><?= e($cafe['category'] ?? '—') ?></dd>

                <dt class="col-sm-3">Tipo de animal</dt>
                <dd class="col-sm-9"><?= e($cafe['animal_type'] ?? '—') ?></dd>

                <dt class="col-sm-3">Descripción</dt>
                <dd class="col-sm-9"><?= nl2br(e($cafe['description'] ?? '')) ?></dd>
            </dl>
        </div>

        <!-- Tab: Horarios -->
        <div x-show="activeTab === 'horarios'" class="tab-content" role="tabpanel">
            <form @submit.prevent="updateSchedule()">
                <div class="form-group">
                    <label for="opening_time">Hora de apertura</label>
                    <input type="time" id="opening_time" name="opening_time"
                        x-model="scheduleForm.opening_time" required>
                </div>
                <div class="form-group">
                    <label for="closing_time">Hora de cierre</label>
                    <input type="time" id="closing_time" name="closing_time"
                        x-model="scheduleForm.closing_time" required>
                </div>
                <button type="submit" class="btn btn-komorebi-primary" :disabled="saving">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Actualizar horarios
                </button>
            </form>
        </div>

        <!-- Tab: Capacidad -->
        <div x-show="activeTab === 'capacidad'" class="tab-content" role="tabpanel">
            <form @submit.prevent="updateCapacity()">
                <div class="form-group">
                    <label for="capacity_max">Capacidad máxima</label>
                    <input type="number" id="capacity_max" name="capacity_max"
                        x-model.number="capacityForm.capacity_max"
                        min="1" max="500" required>
                    <small>Entre 1 y 500 personas</small>
                </div>
                <button type="submit" class="btn btn-komorebi-primary" :disabled="saving">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Actualizar capacidad
                </button>
            </form>
        </div>

        <!-- Tab: Configuración -->
        <div x-show="activeTab === 'config'" class="tab-content" role="tabpanel">
            <form @submit.prevent="updateSettings()">
                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description"
                        x-model="settingsForm.description"
                        rows="5" maxlength="2000"></textarea>
                    <small>Máximo 2000 caracteres</small>
                </div>
                <div class="form-group">
                    <label for="price_per_hour">Precio por hora (¥)</label>
                    <input type="number" id="price_per_hour" name="price_per_hour"
                        x-model.number="settingsForm.price_per_hour"
                        min="0" max="10000" step="1">
                    <small>Entre ¥0 y ¥10,000</small>
                </div>
                <button type="submit" class="btn btn-komorebi-primary" :disabled="saving">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Actualizar configuración
                </button>
            </form>
        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="/manager/dashboard" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver al panel
            </a>
        </div>
    </div>
</div>
