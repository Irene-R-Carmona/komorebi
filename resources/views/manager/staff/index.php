<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Staff (Manager — HDA)
 *
 * @var array  $staff       - Listado de staff members
 * @var array  $shifts      - Turnos de la semana seleccionada
 * @var int    $cafe_id     - ID del café
 * @var string $csrf_token  - Token CSRF
 * @var int    $weekOffset  - Offset de semana (0 = actual, -1 = anterior, +1 = siguiente)
 * @var string $weekFrom    - Fecha lunes YYYY-MM-DD
 * @var string $weekTo      - Fecha domingo YYYY-MM-DD
 * @var string $weekLabel   - Etiqueta legible ej. "2 – 8 jun. 2025"
 */

use App\Support\DateFormatting;
use App\Support\TimeHelper;

$staff ??= [];
$shifts ??= [];
$cafe_id ??= 0;
$csrf_token ??= '';
$weekOffset ??= 0;
$weekFrom ??= date('Y-m-d');
$weekTo ??= date('Y-m-d');
$weekLabel ??= '';

$prevOffset = $weekOffset - 1;
$nextOffset = $weekOffset + 1;

$alpineConfig = json_encode([
    'csrfToken' => $csrf_token,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container" x-data='managerStaff(<?= $alpineConfig ?>)' x-cloak>

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
        <button type="button" @click="activeTab = 'staff'" :class="{'active': activeTab === 'staff'}">
            Staff Activo (<?= count($staff) ?>)
        </button>
        <button type="button" @click="activeTab = 'turnos'" :class="{'active': activeTab === 'turnos'}">
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
                            <td><?= e(DateFormatting::toSpanishDate((string) ($member['created_at'] ?? 'now'))) ?></td>
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
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($shifts === []): ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay turnos asignados esta semana</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shifts as $shift): ?>
                        <?php
                        $start = new DateTime((string) ($shift['shift_start'] ?? 'now'));
                        $end = new DateTime((string) ($shift['shift_end'] ?? 'now'));
                        $duration = $start->diff($end);
                        $shiftJson = htmlspecialchars(
                            json_encode([
                                'id' => (int) ($shift['id'] ?? 0),
                                'shift_date' => (string) ($shift['shift_date'] ?? ''),
                                'shift_start' => substr((string) ($shift['shift_start'] ?? ''), 0, 5),
                                'shift_end' => substr((string) ($shift['shift_end'] ?? ''), 0, 5),
                                'notes' => (string) ($shift['notes'] ?? ''),
                            ], JSON_THROW_ON_ERROR),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                        <tr>
                            <td><?= e((string) ($shift['staff_name'] ?? '')) ?></td>
                            <td><?= e(DateFormatting::toSpanishDate((string) ($shift['shift_date'] ?? 'now'))) ?></td>
                            <td><?= e(TimeHelper::display((string) ($shift['shift_start'] ?? ''))) ?></td>
                            <td><?= e(TimeHelper::display((string) ($shift['shift_end'] ?? ''))) ?></td>
                            <td><?= $duration->h ?>h <?= $duration->i ?>m</td>
                            <td><?= e((string) ($shift['notes'] ?? '—')) ?></td>
                            <td>
                                <button type="button"
                                    @click="openEditModal(<?= $shiftJson ?>)"
                                    class="btn btn-sm btn-secondary">
                                    Editar
                                </button>
                                <button type="button"
                                    @click="deleteShift(<?= (int) ($shift['id'] ?? 0) ?>)"
                                    class="btn btn-sm btn-danger"
                                    :disabled="saving">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tab: Calendario (semana seleccionada Lun-Dom) -->
    <div x-show="activeTab === 'calendario'" class="tab-content">

        <!-- Navegación de semana -->
        <nav class="week-nav" aria-label="Navegación de semana">
            <a href="/manager/staff?week=<?= $prevOffset ?>" class="btn btn-sm btn-secondary week-nav__prev">
                ← Semana anterior
            </a>
            <span class="week-nav__label" aria-live="polite">
                <?= e($weekLabel) ?>
            </span>
            <a href="/manager/staff?week=<?= $nextOffset ?>" class="btn btn-sm btn-secondary week-nav__next">
                Semana siguiente →
            </a>
            <?php if ($weekOffset !== 0): ?>
                <a href="/manager/staff" class="btn btn-sm btn-link week-nav__today">
                    Hoy
                </a>
            <?php endif; ?>
        </nav>

        <div class="calendar-grid">
            <?php
            $mondayTs = strtotime($weekFrom);
for ($i = 0; $i < 7; $i++):
    $dayTs = $mondayTs + $i * 86400;
    $dateStr = date('Y-m-d', $dayTs);
    $dayShifts = array_filter($shifts, static fn ($s) => ($s['shift_date'] ?? '') === $dateStr);
    $isToday = $dateStr === date('Y-m-d');
    ?>
                <div class="calendar-day <?= $isToday ? 'calendar-day--today' : '' ?>">
                    <div class="day-header">
                        <strong><?= date('D d/m', $dayTs) ?></strong>
                        <?php if ($isToday): ?>
                            <span class="today-badge" aria-label="Hoy">•</span>
                        <?php endif; ?>
                    </div>
                    <div class="day-shifts">
                        <?php if (empty($dayShifts)): ?>
                            <p class="no-shifts">Sin turnos</p>
                        <?php else: ?>
                            <?php foreach ($dayShifts as $shift): ?>
                                <div class="shift-item">
                                    <strong><?= e((string) ($shift['staff_name'] ?? '')) ?></strong><br>
                                    <?= e(substr((string) ($shift['shift_start'] ?? ''), 0, 5)) ?> -
                                    <?= e(substr((string) ($shift['shift_end'] ?? ''), 0, 5)) ?>
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

    <!-- Modal: Editar Turno -->
    <div x-show="showEditModal" x-transition class="modal-overlay" @click.self="closeEditModal()">
        <dialog class="modal-content" aria-labelledby="edit-shift-title">
            <h2 id="edit-shift-title">Editar Turno</h2>
            <form @submit.prevent="updateShift()">

                <div class="form-group">
                    <label for="edit-shift-date" class="form-label">Fecha:</label>
                    <input type="date" id="edit-shift-date" name="shift_date" class="form-control"
                        x-model="editForm.shift_date" required>
                </div>

                <div class="form-group">
                    <label for="edit-shift-start" class="form-label">Hora de inicio:</label>
                    <input type="time" id="edit-shift-start" name="shift_start" class="form-control"
                        x-model="editForm.shift_start" required>
                </div>

                <div class="form-group">
                    <label for="edit-shift-end" class="form-label">Hora de fin:</label>
                    <input type="time" id="edit-shift-end" name="shift_end" class="form-control"
                        x-model="editForm.shift_end" required>
                </div>

                <div class="form-group">
                    <label for="edit-shift-notes" class="form-label">Notas (opcional):</label>
                    <textarea id="edit-shift-notes" name="notes" class="form-control"
                        x-model="editForm.notes" rows="3"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        Guardar cambios
                    </button>
                    <button type="button" @click="closeEditModal()" class="btn btn-secondary">Cancelar</button>
                </div>

            </form>
        </dialog>
    </div>

    <div class="mt-4">
        <a href="/manager/dashboard" class="btn btn-secondary">Volver al panel</a>
    </div>

</div>
