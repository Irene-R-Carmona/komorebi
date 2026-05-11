<?php

declare(strict_types=1);

use App\Support\DateFormatting;
use App\Support\StatusLabeling;

/**
 * Vista: Mis Listas de Espera
 */
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
                $itemStatus = (string) ($item['status'] ?? 'waiting');

                // Unificar fecha y hora para el formateador
                $slotDatetime = trim(($item['slot_date'] ?? '') . ' ' . ($item['slot_time'] ?? ''));
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
                            <span class="status-badge <?= e(StatusLabeling::waitlistBadge($itemStatus)) ?>">
                                <?= e(StatusLabeling::waitlistLabel($itemStatus)) ?>
                            </span>
                        </div>

                        <div class="waitlist-card__details">
                            <p class="waitlist-card__date">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                <?= e(DateFormatting::toSpanishLongDate($slotDatetime)) ?>
                            </p>
                            <?php if (!empty($item['guest_count'])): ?>
                                <p class="waitlist-card__party">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    <?= (int) $item['guest_count'] ?> persona<?= (int) $item['guest_count'] > 1 ? 's' : '' ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($item['status'] === 'notified'): ?>
                            <div class="waitlist-card__actions">
                                <p class="waitlist-card__warning">
                                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                    <?php if (!empty($item['notified_at'])): ?>
                                        Tienes hasta el <?= date('d/m/Y H:i', strtotime((string) $item['notified_at'] . ' +24 hours')) ?> para confirmar
                                    <?php else: ?>
                                        Tienes 24 horas desde la notificación para confirmar
                                    <?php endif; ?>
                                </p>
                                <a
                                    href="/waitlist/confirm/<?= htmlspecialchars((string) ($item['token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    class="btn-komorebi btn-komorebi-primary btn-komorebi--sm"
                                    aria-label="Confirmar plaza para <?= htmlspecialchars($slotDatetime, ENT_QUOTES, 'UTF-8') ?>">
                                    Confirmar Plaza
                                </a>
                                <form method="POST" action="/user/waitlists/<?= (int) ($item['id'] ?? 0) ?>/cancel" style="display:inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button
                                        type="submit"
                                        class="btn-komorebi btn-komorebi-ghost btn-komorebi--sm"
                                        aria-label="Rechazar plaza para <?= htmlspecialchars($slotDatetime, ENT_QUOTES, 'UTF-8') ?>">
                                        Rechazar
                                    </button>
                                </form>
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
