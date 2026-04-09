<?php

/**
 * Vista: Estado de Lista de Espera
 *
 * Variables esperadas:
 * - $waitlist: array con datos del waitlist
 */
?>

<style>
    .waitlist-container {
        max-width: 600px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.9rem;
    }

    .status-waiting {
        background: #fef3c7;
        color: #92400e;
    }

    .status-notified {
        background: #d1fae5;
        color: #065f46;
    }

    .status-confirmed {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-expired {
        background: #fee2e2;
        color: #991b1b;
    }

    .position-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 2rem auto;
        font-size: 2.5rem;
        font-weight: bold;
    }

    .position-label {
        font-size: 0.8rem;
        font-weight: normal;
        margin-top: 0.5rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-label {
        font-weight: 600;
        color: #6b7280;
    }

    .info-value {
        color: #111827;
    }

    .alert-box {
        padding: 1rem;
        border-radius: 6px;
        margin: 1.5rem 0;
    }

    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border-left: 4px solid #10b981;
    }

    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border-left: 4px solid #f59e0b;
    }

    .btn-primary {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        text-align: center;
        transition: background 0.2s;
    }

    .btn-primary:hover {
        background: #5a67d8;
    }
</style>

<div class="waitlist-container">
    <h1 style="text-align: center; color: #111827; margin-bottom: 2rem;">
        🐾 Estado de Lista de Espera
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

    <div style="text-align: center; margin-bottom: 2rem;">
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
            <strong>⏳ Tiempo estimado:</strong> ~<?= (int) $waitlist['estimated_wait_minutes'] ?> minutos
            <br>
            <small>Te notificaremos por email cuando tengamos una plaza disponible</small>
        </div>

    <?php elseif ($waitlist['status'] === 'notified'): ?>
        <div class="alert-box alert-success">
            <strong>🎉 ¡Buenas noticias!</strong><br>
            Hay una plaza disponible para ti. Por favor confirma tu reserva antes de que expire.
            <br><br>
            <strong>⏰ Expira:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($waitlist['expires_at'])), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="/waitlist/confirm/<?= htmlspecialchars($waitlist['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="btn-primary">
                Confirmar Reserva Ahora
            </a>
        </div>

    <?php elseif ($waitlist['status'] === 'confirmed'): ?>
        <div class="alert-box alert-success">
            <strong>✅ Reserva Confirmada</strong><br>
            Tu reserva ha sido procesada exitosamente.
        </div>

    <?php elseif ($waitlist['status'] === 'expired'): ?>
        <div class="alert-box alert-warning">
            <strong>⚠️ Promoción Expirada</strong><br>
            Lo sentimos, la plaza disponible expiró. Puedes volver a unirte a la lista de espera.
        </div>
    <?php endif; ?>

    <div style="margin-top: 2rem;">
        <h3 style="color: #6b7280; font-size: 1rem; margin-bottom: 1rem;">Detalles de tu Solicitud</h3>

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

    <div style="text-align: center; margin-top: 2rem; color: #6b7280; font-size: 0.9rem;">
        <p>Guarda este enlace para consultar tu posición en cualquier momento</p>
    </div>
</div>
<div id="waitlist-meta" data-status="<?= htmlspecialchars($waitlist['status'], ENT_QUOTES, 'UTF-8') ?>"></div>
<script src="/js/pages/waitlistStatus.js" nonce="<?= $cspNonce ?? '' ?>"></script>
