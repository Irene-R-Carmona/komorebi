<?php

declare(strict_types=1);

/**
 * Vista: Gestión de Reservas del Manager
 * Ruta: GET /manager/reservations
 *
 * @var string $titulo
 * @var array  $reservations Lista de reservas (id, reservation_date, reservation_time, guest_count, status, user_id, ...)
 * @var array  $filters      ['status' => string|null, 'date' => string|null]
 * @var string $csrf_token   Token CSRF (disponible para acciones futuras)
 */

$statusLabels = [
    'pending'   => ['bg' => '#fef3c7', 'text' => '#92400e', 'label' => 'Pendiente'],
    'confirmed' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'label' => 'Confirmada'],
    'active'    => ['bg' => '#d1fae5', 'text' => '#065f46', 'label' => 'Activa'],
    'completed' => ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => 'Completada'],
    'cancelled' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'label' => 'Cancelada'],
    'no_show'   => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'label' => 'No Show'],
];
?>

<div class="container-fluid">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-header__title"><?= e($titulo) ?></h1>
            <p class="dashboard-header__subtitle">Listado de reservas de tu café</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="glass-card" style="margin-top:1.5rem;padding:1.25rem;">
        <form method="GET" action="/manager/reservations"
            style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
            <div>
                <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;color:var(--text-primary,#1f2937);">
                    Fecha
                </label>
                <input type="date" name="date"
                    value="<?= e($filters['date'] ?? '') ?>"
                    style="padding:0.5rem 0.75rem;border:1px solid var(--border-color,#e5e7eb);border-radius:0.375rem;font-size:0.9rem;">
            </div>
            <div>
                <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;color:var(--text-primary,#1f2937);">
                    Estado
                </label>
                <select name="status"
                    style="padding:0.5rem 0.75rem;border:1px solid var(--border-color,#e5e7eb);border-radius:0.375rem;font-size:0.9rem;min-width:160px;">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $value => $meta): ?>
                        <option value="<?= e($value) ?>"
                            <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= e($meta['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <button type="submit"
                    style="padding:0.5rem 1.25rem;background:var(--primary-600,#2563eb);color:#fff;border:none;border-radius:0.375rem;cursor:pointer;font-weight:600;font-size:0.9rem;">
                    Filtrar
                </button>
                <?php if ($filters['status'] !== null || $filters['date'] !== null): ?>
                    <a href="/manager/reservations"
                        style="padding:0.5rem 1rem;background:var(--border-color,#e5e7eb);color:var(--text-primary,#1f2937);border-radius:0.375rem;text-decoration:none;font-size:0.9rem;display:inline-flex;align-items:center;">
                        Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabla de reservas -->
    <div class="glass-card" style="margin-top:1rem;">
        <?php if (empty($reservations)): ?>
            <div style="padding:3rem;text-align:center;">
                <div style="font-size:3rem;margin-bottom:1rem;">📅</div>
                <p style="color:var(--text-secondary,#6b7280);">
                    No hay reservas<?= ($filters['status'] !== null || $filters['date'] !== null) ? ' con los filtros seleccionados' : '' ?>.
                </p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border-color,#e5e7eb);">
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">ID</th>
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">Fecha</th>
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">Hora</th>
                            <th style="padding:0.75rem 1rem;text-align:center;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">Personas</th>
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">Estado</th>
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.85rem;font-weight:600;color:var(--text-secondary,#6b7280);">Creación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <?php
                            $rstatus = $r['status'] ?? 'pending';
                            $badge   = $statusLabels[$rstatus] ?? ['bg' => '#f3f4f6', 'text' => '#374151', 'label' => e($rstatus)];
                            ?>
                            <tr style="border-bottom:1px solid var(--border-color,#e5e7eb);">
                                <td style="padding:0.75rem 1rem;font-size:0.9rem;color:var(--text-secondary,#6b7280);">
                                    #<?= (int) $r['id'] ?>
                                </td>
                                <td style="padding:0.75rem 1rem;font-weight:600;white-space:nowrap;">
                                    <?= e($r['reservation_date'] ?? '') ?>
                                </td>
                                <td style="padding:0.75rem 1rem;white-space:nowrap;">
                                    <?= e(substr((string) ($r['reservation_time'] ?? ''), 0, 5)) ?>
                                </td>
                                <td style="padding:0.75rem 1rem;text-align:center;">
                                    <?= (int) ($r['guest_count'] ?? 0) ?>
                                </td>
                                <td style="padding:0.75rem 1rem;">
                                    <span style="display:inline-block;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.8rem;font-weight:600;background:<?= $badge['bg'] ?>;color:<?= $badge['text'] ?>;">
                                        <?= $badge['label'] ?>
                                    </span>
                                </td>
                                <td style="padding:0.75rem 1rem;font-size:0.85rem;color:var(--text-secondary,#6b7280);white-space:nowrap;">
                                    <?= e(substr((string) ($r['created_at'] ?? ''), 0, 10)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:0.75rem 1rem;border-top:1px solid var(--border-color,#e5e7eb);font-size:0.85rem;color:var(--text-secondary,#6b7280);">
                <?= count($reservations) ?> reserva<?= count($reservations) !== 1 ? 's' : '' ?> mostrada<?= count($reservations) !== 1 ? 's' : '' ?>
            </div>
        <?php endif; ?>
    </div>
</div>


.btn--primary:hover {
background: var(--primary-700, #1d4ed8);
}
</style>
