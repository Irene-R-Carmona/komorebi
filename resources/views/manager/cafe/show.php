<?php

declare(strict_types=1);

/**
 * Vista: Configuración del Café (Manager)
 *
 * @var array $cafe Datos del café asignado
 */

use App\Core\Session;

$pageTitle = 'Configuración del Café';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Komorebi Café</title>
    <link rel="stylesheet" href="/css/admin.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Gestiona la configuración de tu café</p>
        </header>

        <main x-data="{
            activeTab: 'info',
            showMessage: false,
            message: '',
            messageType: 'success'
        }">
            <!-- Tabs -->
            <div class="tabs">
                <button
                    @click="activeTab = 'info'"
                    :class="{'active': activeTab === 'info'}">
                    Información
                </button>
                <button
                    @click="activeTab = 'horarios'"
                    :class="{'active': activeTab === 'horarios'}">
                    Horarios
                </button>
                <button
                    @click="activeTab = 'capacidad'"
                    :class="{'active': activeTab === 'capacidad'}">
                    Capacidad
                </button>
                <button
                    @click="activeTab = 'config'"
                    :class="{'active': activeTab === 'config'}">
                    Configuración
                </button>
            </div>

            <!-- Message Area -->
            <div x-show="showMessage" x-transition
                :class="'message ' + messageType"
                x-text="message"></div>

            <!-- Tab: Información -->
            <div x-show="activeTab === 'info'" class="tab-content">
                <h2><?= htmlspecialchars($cafe['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><strong>Nombre japonés:</strong> <?= htmlspecialchars($cafe['japanese_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Ubicación:</strong> <?= htmlspecialchars($cafe['location'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Categoría:</strong> <?= htmlspecialchars($cafe['category'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Tipo de animal:</strong> <?= htmlspecialchars($cafe['animal_type'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Descripción:</strong></p>
                <p><?= nl2br(htmlspecialchars($cafe['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
            </div>

            <!-- Tab: Horarios -->
            <div x-show="activeTab === 'horarios'" class="tab-content">
                <form
                    @submit.prevent="async () => {
                        const formData = new FormData($event.target);
                        try {
                            const response = await fetch('/manager/cafe/schedule', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            messageType = data.success ? 'success' : 'error';
                            message = data.success ? 'Horarios actualizados correctamente' : data.error;
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                            if (data.success) {
                                setTimeout(() => { window.location.reload() }, 1500);
                            }
                        } catch (e) {
                            messageType = 'error';
                            message = 'Error al actualizar los horarios';
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                        }
                    }">
                    <input type="hidden" name="csrf_token" value="<?= Session::get('csrf_token') ?>">

                    <div class="form-group">
                        <label for="opening_time">Hora de apertura (HH:MM):</label>
                        <input
                            type="time"
                            id="opening_time"
                            name="opening_time"
                            value="<?= htmlspecialchars(substr($cafe['opening_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="closing_time">Hora de cierre (HH:MM):</label>
                        <input
                            type="time"
                            id="closing_time"
                            name="closing_time"
                            value="<?= htmlspecialchars(substr($cafe['closing_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"
                            required>
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar horarios</button>
                </form>
            </div>

            <!-- Tab: Capacidad -->
            <div x-show="activeTab === 'capacidad'" class="tab-content">
                <form
                    @submit.prevent="async () => {
                        const formData = new FormData($event.target);
                        try {
                            const response = await fetch('/manager/cafe/capacity', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            messageType = data.success ? 'success' : 'error';
                            message = data.success ? 'Capacidad actualizada correctamente' : data.error;
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                            if (data.success) {
                                setTimeout(() => { window.location.reload() }, 1500);
                            }
                        } catch (e) {
                            messageType = 'error';
                            message = 'Error al actualizar la capacidad';
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                        }
                    }">
                    <input type="hidden" name="csrf_token" value="<?= Session::get('csrf_token') ?>">

                    <div class="form-group">
                        <label for="capacity_max">Capacidad máxima:</label>
                        <input
                            type="number"
                            id="capacity_max"
                            name="capacity_max"
                            min="1"
                            max="500"
                            value="<?= htmlspecialchars((string)$cafe['capacity_max'], ENT_QUOTES, 'UTF-8') ?>"
                            required>
                        <small>Entre 1 y 500 personas</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar capacidad</button>
                </form>
            </div>

            <!-- Tab: Configuración -->
            <div x-show="activeTab === 'config'" class="tab-content">
                <form
                    @submit.prevent="async () => {
                        const formData = new FormData($event.target);
                        try {
                            const response = await fetch('/manager/cafe/settings', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            messageType = data.success ? 'success' : 'error';
                            message = data.success ? 'Configuración actualizada correctamente' : data.error;
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                            if (data.success) {
                                setTimeout(() => { window.location.reload() }, 1500);
                            }
                        } catch (e) {
                            messageType = 'error';
                            message = 'Error al actualizar la configuración';
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                        }
                    }">
                    <input type="hidden" name="csrf_token" value="<?= Session::get('csrf_token') ?>">

                    <div class="form-group">
                        <label for="description">Descripción:</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="5"
                            maxlength="2000"><?= htmlspecialchars($cafe['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small>Máximo 2000 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="price_per_hour">Precio por hora (€):</label>
                        <input
                            type="number"
                            id="price_per_hour"
                            name="price_per_hour"
                            min="0"
                            max="100"
                            step="0.01"
                            value="<?= htmlspecialchars((string)$cafe['price_per_hour'], ENT_QUOTES, 'UTF-8') ?>">
                        <small>Entre 0€ y 100€</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar configuración</button>
                </form>
            </div>

            <div class="actions">
                <a href="/manager/dashboard" class="btn btn-secondary">Volver al panel</a>
            </div>
        </main>
    </div>
</body>

</html>
