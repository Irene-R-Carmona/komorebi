<?php

declare(strict_types=1);

use App\Core\Csrf;

?>
<div class="confirm-container">
    <div class="success-icon"><i class="bi bi-stars" aria-hidden="true"></i></div>

    <h1 class="confirm-title">
        ¡Tu Plaza Está Disponible!
    </h1>

    <p class="confirm-subtitle">
        Confirma tu reserva antes de que expire el tiempo
    </p>

    <div class="countdown" id="countdown">
        Cargando...
    </div>

    <div class="info-card">
        <h3 class="info-card__title">Detalles de tu Reserva</h3>

        <div class="info-row">
            <span class="info-label">Fecha:</span>
            <strong><?= htmlspecialchars(date('d/m/Y', strtotime($waitlist['time_slot']['date'])), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <div class="info-row">
            <span class="info-label">Hora:</span>
            <strong><?= htmlspecialchars(date('H:i', strtotime($waitlist['time_slot']['time'])), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <div class="info-row">
            <span class="info-label">Personas:</span>
            <strong><?= (int) $waitlist['guest_count'] ?></strong>
        </div>

        <?php if (!empty($waitlist['special_requests'])): ?>
            <div class="info-row">
                <span class="info-label">Notas:</span>
                <strong><?= htmlspecialchars($waitlist['special_requests'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" id="confirmForm" class="confirm-form">
        <?= Csrf::field() ?>
        <button type="submit" class="btn-confirm" id="confirmBtn">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Confirmar Reserva
        </button>
    </form>

    <div id="errorMessage" class="alert-danger d-none"></div>

    <p class="confirm-footer">
        Al confirmar, aceptas los términos y condiciones de reserva de Komorebi Café
    </p>
</div>
<div id="waitlist-meta" data-expires-at="<?= htmlspecialchars($waitlist['expires_at'], ENT_QUOTES, 'UTF-8') ?>"></div>
<script src="/js/pages/waitlistConfirm.js" nonce="<?= $cspNonce ?? '' ?>"></script>
