<?php

declare(strict_types=1);

/**
 * Vista parcial: Intersticial de redirección
 *
 * Variables esperadas:
 * - string      $destination  URL destino (validada como same-origin en controller)
 * - int         $countdown    Segundos antes de redirigir (1–30, clampado en controller)
 * - string|null $message      Mensaje opcional para el usuario
 * - string      $cancelUrl    URL de cancelación (por defecto '/')
 */

$message ??= null;
$cancelUrl ??= '/';

?>
<div class="error-card" data-kanji="道">
    <i class="bi bi-arrow-right-circle error-icon" aria-hidden="true"></i>
    <p class="error-code" id="countdown-display"><?= htmlspecialchars((string) $countdown, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="error-title">Redirigiendo…</h1>

    <p class="error-desc">
        <?= $message !== null && $message !== ''
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'Serás redirigido automáticamente en unos segundos.' ?>
    </p>

    <div class="redirect-progress"
        role="progressbar"
        aria-label="Progreso de redirección"
        aria-valuemin="0"
        aria-valuemax="<?= htmlspecialchars((string) $countdown, ENT_QUOTES, 'UTF-8') ?>"
        aria-valuenow="0"
        style="--countdown-duration:<?= htmlspecialchars((string) $countdown, ENT_QUOTES, 'UTF-8') ?>s">
    </div>

    <div class="error-actions">
        <a href="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>"
            class="error-btn"
            id="redirect-btn">
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
            Ir ahora
        </a>
        <a href="<?= htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') ?>"
            class="error-btn error-btn--alt">
            Cancelar
        </a>
    </div>
</div>

<script>
    (function() {
        'use strict';
        var count = <?= (int) $countdown ?>;
        var display = document.getElementById('countdown-display');
        var dest = <?= json_encode($destination, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        if (!display || count <= 0 || typeof dest !== 'string' || !dest.startsWith('/')) {
            return;
        }

        var timer = setInterval(function() {
            count -= 1;
            display.textContent = String(count);
            if (count <= 0) {
                clearInterval(timer);
                window.location.href = dest;
            }
        }, 1000);
    }());
</script>
