<?php

declare(strict_types=1);

/**
 * Vista: Detalle de Reserva — Manager
 * Ruta: GET /manager/reservations/{id}
 *
 * @var string   $titulo
 * @var array    $reservation       Datos de la reserva + café + usuario
 * @var string[] $valid_transitions Estados destino válidos desde el estado actual
 * @var string   $csrf_token
 */

use App\Support\DateFormatting;
use App\Support\StatusLabeling;

$id          = (int) ($reservation['id'] ?? 0);
$status      = (string) ($reservation['status'] ?? '');
$date        = (string) ($reservation['reservation_date'] ?? '');
$time        = (string) ($reservation['reservation_time'] ?? '');
$guests      = (int) ($reservation['guest_count'] ?? 0);
$passName    = (string) ($reservation['pass_name'] ?? '—');
$finalAmount = (int) ($reservation['final_amount'] ?? 0);
$payStatus   = (string) ($reservation['payment_status'] ?? '');
$userName    = (string) ($reservation['user_name'] ?? '—');
$userEmail   = (string) ($reservation['user_email'] ?? '');
$cafeName    = (string) ($reservation['cafe_name'] ?? '—');
$notes       = (string) ($reservation['notes'] ?? '');
$cancelReason = (string) ($reservation['cancellation_reason'] ?? '');
$managerNotes = (string) ($reservation['manager_notes'] ?? '');
$refundAmount = (int) ($reservation['refund_amount'] ?? 0);
$refundedAt  = (string) ($reservation['refunded_at'] ?? '');
$createdAt   = (string) ($reservation['created_at'] ?? '');

$statusLabels = [
    'confirmed' => 'Confirmar',
    'active'    => 'Activar',
    'cancelled' => 'Cancelar',
    'no_show'   => 'Marcar No-Show',
    'completed' => 'Completar',
];

$isCancelled = $status === 'cancelled';
$hasRefund   = $refundAmount > 0;
?>
<div class="container-fluid">

    <div class="dashboard-header">
        <div>
            <a href="/manager/reservations" class="btn btn-sm btn-ghost mb-2">&larr; Volver al listado</a>
            <h1 class="dashboard-header__title"><?= e($titulo) ?></h1>
            <p class="dashboard-header__subtitle"><?= e($cafeName) ?></p>
        </div>
    </div>

    <?php if (\function_exists('flash_messages')): ?>
        <?= flash_messages() ?>
    <?php endif; ?>

    <div class="row g-3 mt-1">

        <!-- ── Columna izquierda: información ── -->
        <div class="col-lg-7">
            <div class="glass-card p-4 mb-3">
                <h2 class="section-title mb-3">Información de la reserva</h2>

                <dl class="detail-grid">
                    <dt>Estado</dt>
                    <dd>
                        <span class="reservation-badge <?= e(StatusLabeling::reservationBadge($status)) ?>">
                            <?= e(StatusLabeling::reservationLabel($status)) ?>
                        </span>
                    </dd>

                    <dt>Fecha</dt>
                    <dd><?= e(DateFormatting::toSpanishDate($date)) ?></dd>

                    <dt>Hora</dt>
                    <dd><?= e(\substr($time, 0, 5)) ?></dd>

                    <dt>Personas</dt>
                    <dd><?= $guests ?></dd>

                    <dt>Pase</dt>
                    <dd><?= e($passName) ?></dd>

                    <dt>Importe</dt>
                    <dd><?= \number_format($finalAmount / 100, 2) ?> €</dd>

                    <dt>Pago</dt>
                    <dd><?= e($payStatus !== '' ? $payStatus : '—') ?></dd>

                    <?php if ($notes !== ''): ?>
                        <dt>Notas del cliente</dt>
                        <dd><?= e($notes) ?></dd>
                    <?php endif; ?>

                    <?php if ($cancelReason !== ''): ?>
                        <dt>Motivo de cambio</dt>
                        <dd><?= e($cancelReason) ?></dd>
                    <?php endif; ?>

                    <?php if ($managerNotes !== ''): ?>
                        <dt>Notas del manager</dt>
                        <dd><?= e($managerNotes) ?></dd>
                    <?php endif; ?>

                    <dt>Creada</dt>
                    <dd><?= e(DateFormatting::toSpanishDate(\substr($createdAt, 0, 10))) ?></dd>
                </dl>
            </div>

            <div class="glass-card p-4">
                <h2 class="section-title mb-3">Cliente</h2>
                <dl class="detail-grid">
                    <dt>Nombre</dt>
                    <dd><?= e($userName) ?></dd>
                    <?php if ($userEmail !== ''): ?>
                        <dt>Email</dt>
                        <dd><a href="mailto:<?= e($userEmail) ?>"><?= e($userEmail) ?></a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- ── Columna derecha: acciones ── -->
        <div class="col-lg-5">

            <?php if ($valid_transitions !== []): ?>
                <div class="glass-card p-4 mb-3">
                    <h2 class="section-title mb-3">Cambiar estado</h2>

                    <form method="POST" action="/manager/reservations/<?= $id ?>/status"
                        aria-label="Cambiar estado de la reserva">
                        <input type="hidden" name="_csrf" value="<?= e($csrf_token) ?>">

                        <div class="form-group mb-3">
                            <label class="form-label" for="new_status">Nuevo estado <span aria-hidden="true">*</span></label>
                            <select id="new_status" name="new_status" class="form-select" required
                                aria-required="true">
                                <option value="" disabled selected>Selecciona...</option>
                                <?php foreach ($valid_transitions as $transition): ?>
                                    <option value="<?= e($transition) ?>">
                                        <?= e($statusLabels[$transition] ?? \ucfirst($transition)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="reason">Justificación / motivo</label>
                            <textarea
                                id="reason"
                                name="reason"
                                class="form-control"
                                rows="3"
                                maxlength="1000"
                                placeholder="Describe el motivo del cambio de estado..."
                                aria-describedby="reason-hint"></textarea>
                            <small id="reason-hint" class="form-text">Opcional para activaciones; recomendado para cancelaciones.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Guardar estado</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($isCancelled && !$hasRefund): ?>
                <div class="glass-card p-4 mb-3">
                    <h2 class="section-title mb-3">Registrar devolución</h2>

                    <form method="POST" action="/manager/reservations/<?= $id ?>/refund"
                        aria-label="Registrar devolución">
                        <input type="hidden" name="_csrf" value="<?= e($csrf_token) ?>">

                        <div class="form-group mb-3">
                            <label class="form-label" for="amount_euros">Importe devuelto (€) <span aria-hidden="true">*</span></label>
                            <input
                                id="amount_euros"
                                name="amount_euros"
                                type="number"
                                class="form-control"
                                min="0"
                                step="0.01"
                                max="<?= \number_format($finalAmount / 100, 2, '.', '') ?>"
                                placeholder="0.00"
                                required
                                aria-required="true">
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label" for="refund_notes">Notas de la devolución</label>
                            <textarea
                                id="refund_notes"
                                name="notes"
                                class="form-control"
                                rows="2"
                                maxlength="1000"
                                placeholder="Método de devolución, referencia, etc."></textarea>
                        </div>

                        <button type="submit" class="btn btn-warning w-100">Registrar devolución</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($hasRefund): ?>
                <div class="glass-card p-4">
                    <h2 class="section-title mb-3">Devolución registrada</h2>
                    <dl class="detail-grid">
                        <dt>Importe</dt>
                        <dd><?= \number_format($refundAmount / 100, 2) ?> €</dd>
                        <?php if ($refundedAt !== ''): ?>
                            <dt>Fecha</dt>
                            <dd><?= e(DateFormatting::toSpanishDate(\substr($refundedAt, 0, 10))) ?></dd>
                        <?php endif; ?>
                        <?php if ($managerNotes !== ''): ?>
                            <dt>Notas</dt>
                            <dd><?= e($managerNotes) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
