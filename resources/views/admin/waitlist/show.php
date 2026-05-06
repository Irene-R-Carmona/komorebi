<?php

declare(strict_types=1);

use App\Support\DateFormatting;

/**
 * Detalle de entrada en lista de espera
 *
 * @var array $waitlist
 */

$waitlist ??= [];
$statusLabels = [
    'waiting' => 'En espera',
    'notified' => 'Notificado',
    'confirmed' => 'Confirmado',
    'cancelled' => 'Cancelado',
    'expired' => 'Expirado',
];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2">
                <a href="/admin/waitlists" class="btn btn-komorebi btn-komorebi-ghost btn-komorebi--sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
                <h1 class="h3 mb-0">Lista de espera #<?= (int) ($waitlist['id'] ?? 0) ?></h1>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Detalle</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            <?= e($statusLabels[$waitlist['status'] ?? ''] ?? ucfirst($waitlist['status'] ?? '')) ?>
                        </dd>

                        <dt class="col-sm-4">Posición</dt>
                        <dd class="col-sm-8"><?= (int) ($waitlist['position'] ?? 0) ?></dd>

                        <dt class="col-sm-4">Café</dt>
                        <dd class="col-sm-8"><?= e($waitlist['cafe_name'] ?? '—') ?></dd>

                        <dt class="col-sm-4">Fecha/Hora slot</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($waitlist['slot_date'])): ?>
                                <?= e(DateFormatting::toSpanishDate($waitlist['slot_date'])) ?>
                                <?= !empty($waitlist['slot_time']) ? e(substr($waitlist['slot_time'], 0, 5)) : '' ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Personas</dt>
                        <dd class="col-sm-8"><?= (int) ($waitlist['guest_count'] ?? 0) ?></dd>

                        <dt class="col-sm-4">Usuario</dt>
                        <dd class="col-sm-8">
                            <?= e($waitlist['user_name'] ?? '—') ?>
                            <?php if (!empty($waitlist['user_email'])): ?>
                                <small class="text-muted">(<?= e($waitlist['user_email']) ?>)</small>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Email contacto</dt>
                        <dd class="col-sm-8"><?= e($waitlist['contact_email'] ?? '—') ?></dd>

                        <?php if (!empty($waitlist['special_requests'])): ?>
                            <dt class="col-sm-4">Solicitudes</dt>
                            <dd class="col-sm-8"><?= e($waitlist['special_requests']) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Creado</dt>
                        <dd class="col-sm-8">
                            <?= !empty($waitlist['created_at']) ? e(DateFormatting::toSpanishDateTime($waitlist['created_at'])) : '—' ?>
                        </dd>

                        <?php if (!empty($waitlist['notified_at'])): ?>
                            <dt class="col-sm-4">Notificado</dt>
                            <dd class="col-sm-8"><?= e(DateFormatting::toSpanishDateTime($waitlist['notified_at'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($waitlist['expires_at'])): ?>
                            <dt class="col-sm-4">Expira</dt>
                            <dd class="col-sm-8"><?= e(DateFormatting::toSpanishDateTime($waitlist['expires_at'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <?php if (($waitlist['status'] ?? '') !== 'cancelled'): ?>
            <div class="col-lg-6 mt-4 mt-lg-0">
                <div class="card shadow-sm border-danger">
                    <div class="card-header py-3 bg-danger text-white">
                        <h6 class="m-0 fw-bold">Cancelar entrada</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/waitlists/<?= (int) ($waitlist['id'] ?? 0) ?>/cancel"
                            x-data
                            x-on:submit.prevent="if(confirm('¿Cancelar esta entrada de lista de espera?')) $el.submit()">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle"></i> Cancelar entrada
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
