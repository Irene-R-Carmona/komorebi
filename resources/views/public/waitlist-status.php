<?php

declare(strict_types=1);

/**
 * Vista: Estado de Lista de Espera
 *
 * Variables esperadas:
 * - $waitlist: array con datos del waitlist
 */

?>

<div class="waitlist-container">
    <h1 class="waitlist-header">
        <i class="bi bi-paw" aria-hidden="true"></i> Estado de Lista de Espera
    </h1>

    <?php
    $statusLabels = [
        'waiting' => 'En Espera',
        'notified' => 'Plaza Disponible',
        'confirmed' => 'Confirmado',
        'expired' => 'Expirado',
        'cancelled' => 'Cancelado',
    ];
    $statusClass = 'status-' . $waitlist['status'];
    ?>

    <div class="text-center mb-4">
        <span class="status-badge <?= $statusClass ?>">
            <?= htmlspecialchars($statusLabels[$waitlist['status']] ?? $waitlist['status'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <?php if ($waitlist['status'] === 'waiting'): ?>
        <div class="position-circle">
            #<?= (int) $waitlist['position'] ?>
            <div class="position-label">Tu posición</div>
        </div>

        <div class="alert-box alert-warning">
            <strong><i class="bi bi-hourglass-split" aria-hidden="true"></i> Tiempo estimado:</strong> ~<?= (int) $waitlist['estimated_wait_minutes'] ?> minutos
            <br>
            <small>Te notificaremos por email cuando tengamos una plaza disponible</small>
        </div>

    <?php elseif ($waitlist['status'] === 'notified'): ?>
        <div class="alert-box alert-success">
            <strong><i class="bi bi-stars" aria-hidden="true"></i> ¡Buenas noticias!</strong><br>
            Hay una plaza disponible para ti. Por favor confirma tu reserva antes de que expire.
            <br><br>
            <strong><i class="bi bi-alarm" aria-hidden="true"></i> Expira:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($waitlist['expires_at'])), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="text-center mt-4">
            <a href="/waitlist/confirm/<?= htmlspecialchars($waitlist['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn-wl-primary">
                Confirmar Reserva Ahora
            </a>
        </div>

    <?php elseif ($waitlist['status'] === 'confirmed'): ?>
        <div class="alert-box alert-success">
            <strong><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Reserva Confirmada</strong><br>
            Tu reserva ha sido procesada exitosamente.
        </div>

    <?php elseif ($waitlist['status'] === 'expired'): ?>
        <div class="alert-box alert-warning">
            <strong><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Promoción Expirada</strong><br>
            Lo sentimos, la plaza disponible expiró. Puedes volver a unirte a la lista de espera.
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <h3 class="text-muted fs-6 mb-3">Detalles de tu Solicitud</h3>

        <div class="info-row">
            <span class="info-label">Fecha:</span>
            <span class="info-value">
                <?= htmlspecialchars(date('d/m/Y', strtotime($waitlist['time_slot']['date'])), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="info-row">
            <span class="info-label">Hora:</span>
            <span class="info-value">
                <?= htmlspecialchars(date('H:i', strtotime($waitlist['time_slot']['time'])), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="info-row">
            <span class="info-label">Personas:</span>
            <span class="info-value"><?= (int) $waitlist['guest_count'] ?></span>
        </div>

        <?php if (!empty($waitlist['special_requests'])): ?>
            <div class="info-row">
                <span class="info-label">Notas:</span>
                <span class="info-value"><?= htmlspecialchars($waitlist['special_requests'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if ($waitlist['status'] === 'waiting'): ?>
            <div class="info-row">
                <span class="info-label">Añadido:</span>
                <span class="info-value">
                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime($waitlist['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4 text-muted small">
        <p>Guarda este enlace para consultar tu posición en cualquier momento</p>
    </div>
</div>
<div id="waitlist-meta" data-status="<?= htmlspecialchars($waitlist['status'], ENT_QUOTES, 'UTF-8') ?>"></div>
<script src="/js/pages/waitlistStatus.js" nonce="<?= $cspNonce ?? '' ?>"></script>
