<?php

declare(strict_types=1);

/**
 * Vista: Confirmación exitosa de lista de espera
 *
 * Variables disponibles:
 * @var string $message        Mensaje de confirmación
 * @var int    $reservationId  ID de la reserva creada
 */

?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">

            <div class="mb-4" aria-hidden="true" style="font-size:4.5rem;color:var(--color-success,#16a34a)">
                <i class="bi bi-check-circle-fill"></i>
            </div>

            <h1 class="h2 mb-3">¡Plaza confirmada!</h1>

            <p class="lead mb-4">
                <?= \htmlspecialchars($message ?? 'Tu reserva ha sido confirmada exitosamente.', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <?php if (!empty($reservationId)): ?>
                <p class="text-muted mb-4">
                    Número de reserva: <strong>#<?= (int) $reservationId ?></strong>
                </p>
            <?php endif; ?>

            <a href="/reservas/mis-reservas" class="btn-komorebi btn-komorebi-primary">
                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                Ver mis reservas
            </a>

        </div>
    </div>
</div>
