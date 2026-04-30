<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Staff (Manager — HDA)
 *
 * @var array  $staff       - Listado de staff members
 * @var array  $shifts      - Turnos de la semana
 * @var int    $cafe_id     - ID del café
 * @var string $csrf_token  - Token CSRF
 */

$staff ??= [];
$shifts ??= [];
$cafe_id ??= 0;
$csrf_token ??= '';

$alpineConfig = json_encode([
    'csrfToken' => $csrf_token,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container" x-data='managerStaff(<?= $alpineConfig ?>)'>

    <header class="mb-4">
        <h1 class="h3">Gestión de Staff</h1>
        <p class="text-muted">Gestiona el personal de tu café</p>
    </header>

    <!-- Notificación -->
    <output x-show="showMessage" x-transition
        :class="'alert alert-' + (messageType === 'success' ? 'success' : 'danger') + ' mb-3 d-block'"
        x-text="message"></output>

    <!-- Tabs -->
    <div class="tabs mb-3">
        <button type="button" @click="activeTab = 'staff'"     :class="{'active': activeTab === 'staff'}">
            Staff Activo (<?= count($staff) ?>)
        </button>
        <button type="button" @click="activeTab = 'turnos'"    :class="{'active': activeTab === 'turnos'}">
            Turnos de la Semana (<?= count($shifts) ?>)
        </button>
        <button type="button" @click="activeTab = 'calendario'" :class="{'active': activeTab === 'calendario'}">
            Calendario
        </button>
    </div>

    <!-- Tab: Staff Activo -->
    <div x-show="activeTab === 'staff'" class="tab-content">
        <div class="mb-3">
            <button type="button" @click="openShiftModal()" class="btn btn-primary">
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
                <?php if ($staff === []): ?>
                <tr>
                    <td colspan="6" class="text-center">No hay staff asignado a este café</td>
                </tr>
                <?php else: ?>
                <?php foreach ($staff as $member): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($member['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($member['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($member['roles'] ?? 'Sin rol'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= !empty($member['is_active']) ? 'badge-success' : 'badge-danger' ?>">
                            <?= !empty($member['is_active']) ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) ($member['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="/manager/staff/<?= (int) ($member['id'] ?? 0) ?>" class="btn btn-sm btn-secondary">Ver Detalle</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tab: Turnos de la Semana -->
    <div x-show="activeTab === 'turnos'" class="tab-content">
        <div class="mb-3">
            <button type="button" @click="openShiftModal()" class="btn btn-primary">
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
                <?php if ($shifts === []): ?>
                <tr>
                    <td colspan="6" class="text-center">No hay turnos asignados esta semana</td>
                </tr>
                <?php else: ?>
                <?php foreach ($shifts as $shift): ?>
                <?php
                    $start = new DateTime((string) ($shift['shift_start'] ?? 'now'));
                    $end = new DateTime((string) ($shift['shift_end'] ?? 'now'));
                    $duration = $start->diff($end);
                    ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($shift['staff_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime((string) ($shift['shift_date'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(substr((string) ($shift['shift_start'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(substr((string) ($shift['shift_end'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $duration->h ?>h <?= $duration->i ?>m</td>
                    <td><?= htmlspecialchars((string) ($shift['notes'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tab: Calendario (próximos 7 días) -->
    <div x-show="activeTab === 'calendario'" class="tab-content">
        <div class="calendar-grid">
            <?php
            $today = new DateTime();
for ($i = 0; $i < 7; $i++):
    $day = clone $today;
    $day->modify("+{$i} days");
    $dateStr = $day->format('Y-m-d');
    $dayShifts = array_filter($shifts, static fn ($s) => ($s['shift_date'] ?? '') === $dateStr);
    ?>
            <div class="calendar-day">
                <div class="day-header">
                    <strong><?= $day->format('D d/m') ?></strong>
                </div>
                <div class="day-shifts">
                    <?php if (empty($dayShifts)): ?>
                    <p class="no-shifts">Sin turnos</p>
                    <?php else: ?>
                    <?php foreach ($dayShifts as $shift): ?>
                    <div class="shift-item">
                        <strong><?= htmlspecialchars((string) ($shift['staff_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <?= htmlspecialchars(substr((string) ($shift['shift_start'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?> -
                        <?= htmlspecialchars(substr((string) ($shift['shift_end'] ?? ''), 0, 5), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Modal: Asignar Turno -->
    <div x-show="showShiftModal" x-transition class="modal-overlay" @click.self="closeShiftModal()">
        <div class="modal-content">
            <h2>Asignar Turno</h2>
            <form @submit.prevent="assignShift()">

                <div class="form-group">
                    <label for="shift-user" class="form-label">Staff Member:</label>
                    <select id="shift-user" name="user_id" class="form-select" x-model="shiftForm.user_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($staff as $member): ?>
                        <?php if (!empty($member['is_active'])): ?>
                        <option value="<?= (int) ($member['id'] ?? 0) ?>">
                            <?= htmlspecialchars((string) ($member['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="shift-date" class="form-label">Fecha:</label>
                    <input type="date" id="shift-date" name="shift_date" class="form-control"
                        x-model="shiftForm.shift_date" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="shift-start" class="form-label">Hora de inicio:</label>
                    <input type="time" id="shift-start" name="shift_start" class="form-control"
                        x-model="shiftForm.shift_start" required>
                </div>

                <div class="form-group">
                    <label for="shift-end" class="form-label">Hora de fin:</label>
                    <input type="time" id="shift-end" name="shift_end" class="form-control"
                        x-model="shiftForm.shift_end" required>
                </div>

                <div class="form-group">
                    <label for="shift-notes" class="form-label">Notas (opcional):</label>
                    <textarea id="shift-notes" name="notes" class="form-control"
                        x-model="shiftForm.notes" rows="3"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        Asignar Turno
                    </button>
                    <button type="button" @click="closeShiftModal()" class="btn btn-secondary">Cancelar</button>
                </div>

            </form>
        </div>
    </div>

    <div class="mt-4">
        <a href="/manager/dashboard" class="btn btn-secondary">Volver al panel</a>
    </div>

</div>

<style>
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 20px; }
.calendar-day { border: 1px solid var(--admin-border, #dee2e6); border-radius: 4px; padding: 10px; min-height: 120px; }
.day-header { background: var(--admin-bg-alt, #f8f9fa); padding: 5px; border-radius: 3px; margin-bottom: 8px; text-align: center; }
.shift-item { background: var(--admin-info-light, #cfe2ff); padding: 6px; margin: 4px 0; border-radius: 3px; font-size: .9em; }
.no-shifts { color: var(--admin-text-muted, #6c757d); font-size: .85em; text-align: center; padding: 10px 0; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
</style>
