<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Confirmación de cancelación de reserva
 * Ruta: GET /reservas/mis-reservas/{id}/cancelar
 *
 * @var string $titulo
 * @var array{id: int, cafe_name: string, reservation_date: string, reservation_time: string, guest_count: int, status: string} $reservation
 */

$reservation ??= [];

$formattedDate = isset($reservation['reservation_date'])
    ? \date('d \d\e F \d\e Y', \strtotime((string) $reservation['reservation_date']))
    : '—';

$formattedTime = isset($reservation['reservation_time'])
    ? \substr((string) $reservation['reservation_time'], 0, 5)
    : '—';

?>
<section class="seccion seccion--activa">
    <div class="seccion__container rsv2-cancel">

        <!-- Cabecera -->
        <div class="rsv2-cancel__header">
            <i class="bi bi-x-circle-fill rsv2-cancel__icon" aria-hidden="true"></i>
            <h1 class="rsv2-cancel__title">¿Cancelar esta reserva?</h1>
            <p class="rsv2-cancel__subtitle">Esta acción es irreversible. Tu plaza quedará liberada.</p>
        </div>

        <!-- Detalle de la reserva -->
        <div class="rsv2-cancel__detail-card">
            <h2 class="rsv2-cancel__heading">Reserva #<?= (int) ($reservation['id'] ?? 0) ?></h2>
            <dl class="rsv2-cancel__dl">
                <dt class="rsv2-cancel__dt">Café</dt>
                <dd class="rsv2-cancel__dd"><?= e($reservation['cafe_name'] ?? '—') ?></dd>

                <dt class="rsv2-cancel__dt">Fecha</dt>
                <dd class="rsv2-cancel__dd"><?= e($formattedDate) ?></dd>

                <dt class="rsv2-cancel__dt">Hora</dt>
                <dd class="rsv2-cancel__dd"><?= e($formattedTime) ?></dd>

                <dt class="rsv2-cancel__dt">Personas</dt>
                <dd class="rsv2-cancel__dd"><?= (int) ($reservation['guest_count'] ?? 0) ?></dd>
            </dl>
        </div>

        <!-- Aviso -->
        <div class="rsv2-cancel__alert" role="alert">
            <i class="bi bi-info-circle rsv2-cancel__alert-icon" aria-hidden="true"></i>
            <p class="rsv2-cancel__alert-text">
                Si cancelas y el horario se completa, no podrás recuperar la plaza.
                Si tienes puntos de fidelización asociados a esta reserva, no se revertirán.
            </p>
        </div>

        <!-- Acciones -->
        <div class="rsv2-cancel__actions">
            <form method="POST" action="/reservas/mis-reservas/<?= (int) ($reservation['id'] ?? 0) ?>/cancel">
                <?= Csrf::field() ?>
                <button type="submit" class="btn-komorebi btn-komorebi-danger btn-komorebi--full">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Sí, cancelar reserva
                </button>
            </form>
            <a href="/reservas/mis-reservas" class="btn-komorebi btn-komorebi-ghost btn-komorebi--full">
                No, volver a mis reservas
            </a>
        </div>

    </div>
</section>
