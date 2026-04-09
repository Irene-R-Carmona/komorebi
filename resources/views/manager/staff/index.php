<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Staff (Manager)
 *
 * @var array $staff Listado de staff members
 * @var array $shifts Turnos de la semana
 * @var int $cafe_id ID del café
 * @var string $csrf_token Token CSRF
 */

use App\Core\Session;

$pageTitle = 'Gestión de Staff';
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
            <p>Gestiona el personal de tu café</p>
        </header>

        <main x-data="{
            activeTab: 'staff',
            showMessage: false,
            message: '',
            messageType: 'success',
            showShiftModal: false,
            shiftForm: {
                user_id: '',
                shift_date: '',
                shift_start: '',
                shift_end: '',
                notes: ''
            }
        }">
            <!-- Tabs -->
            <div class="tabs">
                <button
                    @click="activeTab = 'staff'"
                    :class="{'active': activeTab === 'staff'}">
                    Staff Activo (<?= count($staff) ?>)
                </button>
                <button
                    @click="activeTab = 'turnos'"
                    :class="{'active': activeTab === 'turnos'}">
                    Turnos de la Semana (<?= count($shifts) ?>)
                </button>
                <button
                    @click="activeTab = 'calendario'"
                    :class="{'active': activeTab === 'calendario'}">
                    Calendario
                </button>
            </div>

            <!-- Message Area -->
            <div x-show="showMessage" x-transition
                :class="'message ' + messageType"
                x-text="message"></div>

            <!-- Tab: Staff Activo -->
            <div x-show="activeTab === 'staff'" class="tab-content">
                <div class="actions">
                    <button @click="showShiftModal = true" class="btn btn-primary">
                        + Asignar Turno
                    </button>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Estado</th>
                            <th>Fecha Alta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No hay staff asignado a este café</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($member['roles'] ?? 'Sin rol', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge <?= $member['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $member['is_active'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($member['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <a href="/manager/staff/<?= $member['id'] ?>" class="btn btn-sm btn-secondary">Ver Detalle</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab: Turnos de la Semana -->
            <div x-show="activeTab === 'turnos'" class="tab-content">
                <div class="actions">
                    <button @click="showShiftModal = true" class="btn btn-primary">
                        + Asignar Turno
                    </button>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Fecha</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Duración</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No hay turnos asignados esta semana</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $shift): ?>
                                <?php
                                $start = new DateTime($shift['shift_start']);
                                $end = new DateTime($shift['shift_end']);
                                $duration = $start->diff($end);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($shift['staff_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($shift['shift_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(substr($shift['shift_start'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(substr($shift['shift_end'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $duration->h ?>h <?= $duration->i ?>m</td>
                                    <td><?= htmlspecialchars($shift['notes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab: Calendario Simple -->
            <div x-show="activeTab === 'calendario'" class="tab-content">
                <div class="calendar-view">
                    <h3>Vista de Calendario (Próximos 7 días)</h3>
                    <div class="calendar-grid">
                        <?php
                        $today = new DateTime();
                        for ($i = 0; $i < 7; $i++):
                            $date = clone $today;
                            $date->modify("+{$i} days");
                            $dateStr = $date->format('Y-m-d');
                            $dayShifts = array_filter($shifts, fn($s) => $s['shift_date'] === $dateStr);
                        ?>
                            <div class="calendar-day">
                                <div class="day-header">
                                    <strong><?= $date->format('D d/m') ?></strong>
                                </div>
                                <div class="day-shifts">
                                    <?php if (empty($dayShifts)): ?>
                                        <p class="no-shifts">Sin turnos</p>
                                    <?php else: ?>
                                        <?php foreach ($dayShifts as $shift): ?>
                                            <div class="shift-item">
                                                <strong><?= htmlspecialchars($shift['staff_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <?= htmlspecialchars(substr($shift['shift_start'], 0, 5), ENT_QUOTES, 'UTF-8') ?> -
                                                <?= htmlspecialchars(substr($shift['shift_end'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Modal: Asignar Turno -->
            <div x-show="showShiftModal" x-transition class="modal-overlay" @click.self="showShiftModal = false">
                <div class="modal-content">
                    <h2>Asignar Turno</h2>
                    <form @submit.prevent="async () => {
                        const formData = new FormData($event.target);
                        try {
                            const response = await fetch('/manager/staff/assign-shift', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();
                            messageType = data.success ? 'success' : 'error';
                            message = data.success ? data.message : data.error;
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                            if (data.success) {
                                showShiftModal = false;
                                setTimeout(() => { window.location.reload() }, 1500);
                            }
                        } catch (e) {
                            messageType = 'error';
                            message = 'Error al asignar turno';
                            showMessage = true;
                            setTimeout(() => { showMessage = false }, 3000);
                        }
                    }">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="form-group">
                            <label for="user_id">Staff Member:</label>
                            <select id="user_id" name="user_id" x-model="shiftForm.user_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($staff as $member): ?>
                                    <?php if ($member['is_active']): ?>
                                        <option value="<?= $member['id'] ?>">
                                            <?= htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="shift_date">Fecha:</label>
                            <input
                                type="date"
                                id="shift_date"
                                name="shift_date"
                                x-model="shiftForm.shift_date"
                                min="<?= date('Y-m-d') ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="shift_start">Hora de inicio:</label>
                            <input
                                type="time"
                                id="shift_start"
                                name="shift_start"
                                x-model="shiftForm.shift_start"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="shift_end">Hora de fin:</label>
                            <input
                                type="time"
                                id="shift_end"
                                name="shift_end"
                                x-model="shiftForm.shift_end"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notas (opcional):</label>
                            <textarea
                                id="notes"
                                name="notes"
                                x-model="shiftForm.notes"
                                rows="3"></textarea>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">Asignar Turno</button>
                            <button type="button" @click="showShiftModal = false" class="btn btn-secondary">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="actions">
                <a href="/manager/dashboard" class="btn btn-secondary">Volver al panel</a>
            </div>
        </main>
    </div>

    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .calendar-day {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            min-height: 120px;
        }

        .day-header {
            background: #f5f5f5;
            padding: 5px;
            border-radius: 3px;
            margin-bottom: 8px;
            text-align: center;
        }

        .shift-item {
            background: #e3f2fd;
            padding: 6px;
            margin: 4px 0;
            border-radius: 3px;
            font-size: 0.9em;
        }

        .no-shifts {
            color: #999;
            font-size: 0.85em;
            text-align: center;
            padding: 10px 0;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</body>

</html>
