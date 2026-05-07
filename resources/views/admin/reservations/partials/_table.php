<?php

/**
 * Partial: Tabla de reservas (PHP foreach — HDA)
 *
 * @var array $reservations  - Reservas paginadas (raw rows con JOINs)
 * @var array $meta          - Metadatos de paginación
 * @var array $currentParams - Parámetros activos
 */

use App\Support\ViewHelpers;

$reservations ??= [];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$statusLabels = [
    'confirmed' => 'Confirmada',
    'pending' => 'Pendiente',
    'cancelled' => 'Cancelada',
    'completed' => 'Completada',
];
?>

<div class="card-admin">
    <div class="table-responsive">
        <table class="table table-admin table-hover align-middle mb-0 reservation-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Café</th>
                    <th>Fecha</th>
                    <th>Personas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations === []): ?>
                <tr>
                    <td colspan="7">
                        <?= \App\Core\View::componentToString('components/admin/empty-state', [
                            'icon' => 'calendar-x',
                            'title' => 'No hay reservas aquí',
                            'message' => 'Ajusta los filtros o espera nuevas reservas',
                            'compact' => true,
                        ]) ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($reservations as $reservation): ?>
                <?php
                    $resId = (int) $reservation['id'];
                    $status = (string) ($reservation['status'] ?? '');
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $customerName = htmlspecialchars((string) ($reservation['customer_name'] ?? 'Invitado'), ENT_QUOTES, 'UTF-8');
                    $cafeName = htmlspecialchars((string) ($reservation['cafe_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $guestCount = (int) ($reservation['guest_count'] ?? 1);
                    $resDate = (string) ($reservation['reservation_date'] ?? '');
                    $resTime = (string) ($reservation['reservation_time'] ?? '');
                    $canConfirm = $status === 'pending';
                    $canCancel = in_array($status, ['confirmed', 'pending'], true);
                    $initial = mb_strtoupper(mb_substr((string) ($reservation['customer_name'] ?? 'U'), 0, 1));

                    $modalData = htmlspecialchars(json_encode([
                        'id' => $resId,
                        'status' => $status,
                        'date' => $resDate,
                        'time' => $resTime,
                        'customer_name' => (string) ($reservation['customer_name'] ?? ''),
                        'customer_email' => (string) ($reservation['customer_email'] ?? ''),
                        'cafe_name' => (string) ($reservation['cafe_name'] ?? ''),
                        'guest_count' => $guestCount,
                        'notes' => (string) ($reservation['notes'] ?? ''),
                        'created_at' => (string) ($reservation['created_at'] ?? ''),
                    ], \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR), \ENT_QUOTES, 'UTF-8');
                    ?>
                <tr>
                    <td>
                        <span class="font-monospace text-muted">#<?= $resId ?></span>
                    </td>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                                <span><?= $initial ?></span>
                            </div>
                            <span><?= $customerName ?></span>
                        </div>
                    </td>

                    <td>
                        <div class="reservation-table__cafe">
                            <?php if (!empty($reservation['cafe_image'])): ?>
                            <img
                                src="<?= htmlspecialchars((string) $reservation['cafe_image'], ENT_QUOTES, 'UTF-8') ?>"
                                class="reservation-table__cafe-icon"
                                alt="">
                            <?php endif; ?>
                            <span><?= $cafeName ?></span>
                        </div>
                    </td>

                    <td>
                        <div class="reservation-table__date"><?= htmlspecialchars($resDate, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="reservation-table__time"><?= htmlspecialchars($resTime, ENT_QUOTES, 'UTF-8') ?></div>
                    </td>

                    <td>
                        <span class="reservation-table__guests">
                            <i class="bi bi-people text-muted"></i>
                            <?= $guestCount ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge-reservation badge-reservation--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>

                    <td class="text-end">
                        <div class="table-actions">
                            <?php if ($canConfirm): ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-success"
                                @click="confirmReservation(<?= $resId ?>)"
                                title="Confirmar reserva"
                                aria-label="Confirmar reserva #<?= $resId ?>">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                @click="openModal(<?= $modalData ?>)"
                                title="Ver detalles"
                                aria-label="Ver detalles reserva #<?= $resId ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($canCancel): ?>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="cancelReservation(<?= $resId ?>)"
                                title="Cancelar"
                                aria-label="Cancelar reserva #<?= $resId ?>">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($reservations !== []): ?>
    <div class="d-flex justify-content-between align-items-center p-3 border-top mt-2">
        <div class="text-muted small">Página <?= (int) $meta['page'] ?></div>
        <?= ViewHelpers::paginationLinks($meta, $currentParams) ?>
    </div>
    <?php endif; ?>
</div>
