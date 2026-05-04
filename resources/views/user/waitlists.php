<?php

declare(strict_types=1);

/**
 * Vista: Mis Listas de Espera
 *
 * Variables disponibles:
 * @var array $waitlists - Lista de registros de espera del usuario
 */

// Helper: formatear fecha en español
function formatDate(string $date): string
{
    $months = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    $day = (int) date('d', $timestamp);
    $month = (int) date('m', $timestamp);
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);

    return "$day de {$months[$month]} de $year a las $time";
}

// Helper: obtener badge de estado
function getStatusBadge(string $status): array
{
    $badges = [
        'waiting'   => ['text' => 'Esperando',  'class' => 'status-badge--waiting'],
        'notified'  => ['text' => 'Notificado',  'class' => 'status-badge--notified'],
        'confirmed' => ['text' => 'Confirmado',  'class' => 'status-badge--confirmed'],
        'expired'   => ['text' => 'Expirado',    'class' => 'status-badge--expired'],
    ];

    return $badges[$status] ?? ['text' => $status, 'class' => ''];
}
?>

<!-- Encabezado de sección -->
<div class="loyalty-header">
    <div class="loyalty-header__content">
        <h1 class="loyalty-header__title">Mis Listas de Espera</h1>
        <p class="loyalty-header__description">Consulta tu posición y confirma cuando haya plazas disponibles</p>
    </div>
</div>

<?php if (empty($waitlists)): ?>
    <!-- Empty State Enriquecido -->
    <section class="loyalty-section">
        <div class="empty-state">
            <div class="empty-state__icon"><i class="bi bi-clipboard2-list" aria-hidden="true"></i></div>
            <h2 class="empty-state__title">No tienes listas de espera activas</h2>
            <p class="empty-state__description">
                Únete a la lista de espera cuando una actividad esté completa y te avisaremos si se libera un lugar.
            </p>

            <a href="/reservas" class="btn-primary">Explorar Actividades</a>
        </div>

        <!-- Información sobre cómo funciona -->
        <div class="info-cards">
            <div class="info-card">
                <span class="info-card__icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
                <h3 class="info-card__title">Te avisamos</h3>
                <p class="info-card__description">
                    Recibirás una notificación por email cuando haya una plaza disponible.
                </p>
            </div>
            <div class="info-card">
                <span class="info-card__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                <h3 class="info-card__title">Tienes 24h</h3>
                <p class="info-card__description">
                    Una vez notificado, tienes 24 horas para confirmar tu plaza.
                </p>
            </div>
            <div class="info-card">
                <span class="info-card__icon"><i class="bi bi-bullseye" aria-hidden="true"></i></span>
                <h3 class="info-card__title">Prioridad</h3>
                <p class="info-card__description">
                    Tu posición en la lista determina quién recibe la oferta primero.
                </p>
            </div>
        </div>

        <!-- Cross-promotion a loyalty -->
        <div class="promo-card">
            <div class="promo-card__icon"><i class="bi bi-card-checklist" aria-hidden="true"></i></div>
            <div class="promo-card__content">
                <h3 class="promo-card__title">¿Ya tienes tu tarjeta de fidelización?</h3>
                <p class="promo-card__description">
                    Acumula sellos con cada visita y canjea recompensas exclusivas.
                </p>
                <a href="/loyalty/card" class="promo-card__cta">Ver mi tarjeta →</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <!-- Lista de Waitlists -->
    <section class="loyalty-section">
        <div class="waitlist-cards">
            <?php
            foreach ($waitlists as $item):
                $statusBadge = getStatusBadge((string) ($item['status'] ?? 'waiting'));
            ?>
                <div class="waitlist-card">
                    <!-- Badge de posición -->
                    <div class="waitlist-card__position">
                        <span class="waitlist-card__position-number">#<?= (int) ($item['position'] ?? 1) ?></span>
                    </div>

                    <!-- Contenido principal -->
                    <div class="waitlist-card__content">
                        <div class="waitlist-card__header">
                            <h3 class="waitlist-card__title">
                                <?= htmlspecialchars($item['cafe_name'] ?? 'Café', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <span class="status-badge <?= $statusBadge['class'] ?>">
                                <?= htmlspecialchars($statusBadge['text'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="waitlist-card__details">
                            <p class="waitlist-card__date">
                                <i class="bi bi-calendar3" aria-hidden="true"></i> <?= formatDate(\trim(($item['slot_date'] ?? '') . ' ' . ($item['slot_time'] ?? ''))) ?>
                            </p>
                            <?php if (!empty($item['guest_count'])): ?>
                                <p class="waitlist-card__party">
                                    <i class="bi bi-people" aria-hidden="true"></i> <?= (int) $item['guest_count'] ?> persona<?= (int) $item['guest_count'] > 1 ? 's' : '' ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($item['status'] === 'notified'): ?>
                            <div class="waitlist-card__actions">
                                <p class="waitlist-card__warning">
                                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Tienes hasta el <?= date('d/m/Y H:i', strtotime((string) ($item['notified_at'] ?? '') . ' +24 hours')) ?> para confirmar
                                </p>
                                <button class="btn-primary btn-sm">Confirmar Plaza</button>
                                <button class="btn-secondary btn-sm">Rechazar</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            endforeach;
            ?>
        </div>
    </section>

    <!-- Información adicional -->
    <section class="loyalty-section">
        <div class="info-box">
            <h3 class="info-box__title"><i class="bi bi-info-circle" aria-hidden="true"></i> ¿Cómo funciona la lista de espera?</h3>
            <ul class="info-box__list">
                <li>Las notificaciones se envían por orden de llegada (tu posición en la lista).</li>
                <li>Si confirmas, la plaza es tuya. Si no respondes en 24h, pasamos al siguiente.</li>
                <li>Puedes cancelar tu posición en cualquier momento desde esta página.</li>
            </ul>
        </div>
    </section>
<?php endif; ?>
