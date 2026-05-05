<?php

declare(strict_types=1);

/**
 * Vista: Confirmación de Reserva
 * Ruta: GET /reservas/confirmacion
 *
 * Variables esperadas:
 * @var string $titulo
 * @var array  $reservation  {id, cafe_name, pass_name, pass_duration_minutes, reservation_date, reservation_time, guest_count, status, comments}
 */

$reservation ??= [];
$cart_items ??= [];
$cart_total ??= 0.0;

$formattedDate = isset($reservation['reservation_date'])
    ? date('d/m/Y', strtotime($reservation['reservation_date']))
    : '—';

$formattedTime = isset($reservation['reservation_time'])
    ? substr($reservation['reservation_time'], 0, 5)
    : '—';

$statusLabels = [
    'confirmed' => 'Confirmada',
    'pending' => 'Pendiente',
    'cancelled' => 'Cancelada',
];
$statusLabel = $statusLabels[$reservation['status'] ?? ''] ?? ucfirst($reservation['status'] ?? '—');
?>

<section class="seccion seccion--activa">
    <div class="seccion__container rsv2-confirmation">

        <!-- Cabecera de confirmación -->
        <div class="rsv2-confirmation__header">
            <i class="bi bi-patch-check-fill rsv2-confirmation__icon" aria-hidden="true"></i>
            <h1 class="rsv2-confirmation__title">¡Reserva confirmada!</h1>
            <p class="rsv2-confirmation__subtitle">Tu experiencia en Komorebi Café está lista.</p>
        </div>

        <!-- Código de reserva -->
        <div class="rsv2-confirmation__code-card">
            <p class="rsv2-confirmation__code-label">Número de reserva</p>
            <p class="rsv2-confirmation__code-number">#<?= e((string) ($reservation['id'] ?? '—')) ?></p>
            <p class="rsv2-confirmation__code-hint">Guarda este número para identificar tu reserva</p>
        </div>

        <!-- Detalle de la reserva -->
        <div class="rsv2-confirmation__detail-card">
            <h2 class="rsv2-confirmation__detail-heading">Detalle de la reserva</h2>
            <dl class="rsv2-confirmation__dl">
                <dt class="rsv2-confirmation__dt">Café</dt>
                <dd class="rsv2-confirmation__dd"><?= e($reservation['cafe_name'] ?? '—') ?></dd>

                <dt class="rsv2-confirmation__dt">Pase</dt>
                <dd class="rsv2-confirmation__dd">
                    <?= e($reservation['pass_name'] ?? '—') ?>
                    <?php if (!empty($reservation['pass_duration_minutes'])): ?>
                        <span class="rsv2-confirmation__dd-note">(<?= (int) $reservation['pass_duration_minutes'] ?> min)</span>
                    <?php endif; ?>
                </dd>

                <dt class="rsv2-confirmation__dt">Fecha</dt>
                <dd class="rsv2-confirmation__dd"><?= e($formattedDate) ?></dd>

                <dt class="rsv2-confirmation__dt">Hora</dt>
                <dd class="rsv2-confirmation__dd"><?= e($formattedTime) ?></dd>

                <dt class="rsv2-confirmation__dt">Personas</dt>
                <dd class="rsv2-confirmation__dd"><?= (int) ($reservation['guest_count'] ?? 0) ?></dd>

                <dt class="rsv2-confirmation__dt">Estado</dt>
                <dd class="rsv2-confirmation__dd">
                    <span class="rsv2-pill <?= ($reservation['status'] ?? '') === 'confirmed' ? 'rsv2-pill--confirmed' : 'rsv2-pill--pending' ?>">
                        <?= e($statusLabel) ?>
                    </span>
                </dd>

                <?php if (!empty($reservation['notes'])): ?>
                    <dt class="rsv2-confirmation__dt">Comentarios</dt>
                    <dd class="rsv2-confirmation__dd"><?= e($reservation['notes']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Extras del carrito -->
        <?php if (!empty($cart_items)): ?>
            <div class="rsv2-confirmation__extras-card">
                <h2 class="rsv2-confirmation__detail-heading">Extras pedidos</h2>
                <ul class="rsv2-confirmation__extras-list">
                    <?php foreach ($cart_items as $item): ?>
                        <li class="rsv2-confirmation__extras-line">
                            <span class="rsv2-confirmation__extras-name">
                                <?= (int) ($item['quantity'] ?? 1) ?>×
                                <?= e((string) ($item['product_name'] ?? '')) ?>
                            </span>
                            <span class="rsv2-confirmation__extras-price">
                                ¥<?= \number_format((float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0)) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="rsv2-confirmation__extras-total">
                    <span>Total extras</span>
                    <strong>¥<?= \number_format((float) $cart_total) ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="rsv2-confirmation__actions">
            <a href="/reservas/mis-reservas" class="btn-komorebi btn-komorebi-primary">
                Ver mis reservas
            </a>
            <button type="button" class="btn-komorebi btn-komorebi-accent rsv2-confirmation__print-btn" @click="window.print()">
                <i class="bi bi-printer" aria-hidden="true"></i>
                Guardar confirmación
            </button>
            <a href="/" class="btn-komorebi btn-komorebi-ghost">
                Volver al inicio
            </a>
        </div>

    </div>
</section>
