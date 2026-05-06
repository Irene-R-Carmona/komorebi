<?php

declare(strict_types=1);

use App\Support\DateFormatting;
use App\Support\StatusLabeling;

/**
 * Vista: Mis Reservas (usuario autenticado)
 * Ruta: GET /reservas/mis-reservas
 *
 * Variables esperadas:
 * @var string $titulo
 * @var array  $reservations  Array de reservas del usuario (puede estar vacío)
 */

$reservations ??= [];
$flash ??= null;

$statusBadge = [
    'confirmed' => 'rsv2-pill--confirmed',
    'pending' => 'rsv2-pill--pending',
    'cancelled' => 'rsv2-pill--cancelled',
];
?>

<section class="seccion seccion--activa">
    <div class="seccion__container rsv2-lista">

        <?php if (!empty($flash)): ?>
            <div class="toast <?= ($flash['type'] ?? '') === 'success' ? 'toast--exito' : 'toast--error' ?> mb-lg" role="alert">
                <span class="toast__icono"><?= ($flash['type'] ?? '') === 'success' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' : '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' ?></span>
                <span class="toast__mensaje"><?= e((string) ($flash['message'] ?? '')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Cabecera -->
        <div class="rsv2-lista__header">
            <div>
                <h1 class="rsv2-lista__title">Mis Reservas</h1>
                <p class="rsv2-lista__subtitle">Historial y estado de tus visitas a Komorebi Café</p>
            </div>
            <div class="rsv2-lista__header-actions">
                <button type="button" class="btn-komorebi btn-komorebi-ghost rsv2-lista__print-btn" @click="window.print()">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    Imprimir
                </button>
                <a href="/reservas" class="btn-komorebi btn-komorebi-primary">
                    Nueva reserva
                </a>
            </div>
        </div>

        <?php if (empty($reservations)): ?>
            <!-- Estado vacío -->
            <div class="rsv2-empty">
                <i class="bi bi-calendar-x rsv2-empty__icon" aria-hidden="true"></i>
                <h2 class="rsv2-empty__title">No tienes reservas aún</h2>
                <p class="rsv2-empty__text">¿Listo para vivir la experiencia Komorebi?</p>
                <a href="/reservas" class="btn-komorebi btn-komorebi-primary">Hacer una reserva</a>
            </div>
        <?php else: ?>
            <!-- Lista de reservas -->
            <div class="rsv2-lista__list" aria-live="polite" aria-relevant="additions removals" aria-atomic="false">
                <?php foreach ($reservations as $rsv): ?>
                    <?php
                    $status = $rsv['status'] ?? '';
                    $label = StatusLabeling::reservationLabel($status);
                    $badge = $statusBadge[$status] ?? 'rsv2-pill--cancelled';
                    $canCancel = in_array($status, ['pending', 'confirmed'], true);
                    $formattedDate = isset($rsv['reservation_date'])
                        ? DateFormatting::toSpanishDate($rsv['reservation_date'])
                        : '—';
                    $formattedTime = isset($rsv['reservation_time'])
                        ? substr($rsv['reservation_time'], 0, 5)
                        : '—';
                    ?>
                    <div class="rsv2-card">
                        <div class="rsv2-card__body">
                            <div class="rsv2-card__row">
                                <div>
                                    <p class="rsv2-card__ref">Reserva #<?= (int) ($rsv['id'] ?? 0) ?></p>
                                    <h2 class="rsv2-card__name"><?= e($rsv['cafe_name'] ?? '—') ?></h2>
                                    <p class="rsv2-card__pass">
                                        <?= e($rsv['pass_name'] ?? '—') ?>
                                        <?php if (!empty($rsv['pass_duration_minutes'])): ?>
                                            · <?= (int) $rsv['pass_duration_minutes'] ?> min
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="rsv2-pill <?= $badge ?>"><?= e($label) ?></span>
                            </div>

                            <div class="rsv2-card__meta">
                                <span>
                                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    <?= e($formattedDate) ?>
                                </span>
                                <span>
                                    <i class="bi bi-clock" aria-hidden="true"></i>
                                    <?= e($formattedTime) ?>
                                </span>
                                <span>
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    <?= (int) ($rsv['guest_count'] ?? 0) ?> persona<?= (int) ($rsv['guest_count'] ?? 0) !== 1 ? 's' : '' ?>
                                </span>
                            </div>

                            <?php if ($canCancel): ?>
                                <div class="rsv2-card__actions">
                                    <a href="/reservas/mis-reservas/<?= (int) ($rsv['id'] ?? 0) ?>/cancelar"
                                        class="btn-komorebi btn-komorebi-secondary rsv2-card__cancel">
                                        Cancelar reserva
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>
