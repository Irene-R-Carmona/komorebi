<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Reservas (pase obligatorio)
 *
 * Variables esperadas:
 * - array $cafes
 * - array $passes
 * - array $cart
 * - array $cartDetails
 * - array|null $flash
 */
$cafes   ??= [];
$passes  ??= [];

$alpineConfig = json_encode([
    'cafes'   => $cafes,
    'passes'  => $passes,
    'festivos' => $festivos ?? [],
    'cartTotal' => 0,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<section class="seccion seccion--activa">
    <script src="/js/sections/reservas.js?v=12"></script>
    <script src="/js/dietary-preferences.js"></script>

    <div class="seccion__container rsv2"
        x-data='reservaForm(<?= $alpineConfig ?>)'>

        <?php if (!empty($flash)): ?>
            <div class="toast <?= ($flash['type'] ?? '') === 'success' ? 'toast--exito' : 'toast--error' ?> mb-lg">
                <span class="toast__icono"><?= ($flash['type'] ?? '') === 'success' ? '✅' : '⚠️' ?></span>
                <span class="toast__mensaje"><?= $flash['message'] ?? '' ?></span>
            </div>
        <?php endif; ?>

        <header class="rsv2__header">
            <div>
                <h2 class="rsv2__title">予約 · Reservas</h2>
                <p class="rsv2__subtitle">Elige tu café, tu pase y tu turno.</p>
            </div>
        </header>

        <!-- Información contextual: Clima y Festivos -->
        <?php if (isset($clima) && isset($festivos)): ?>
            <?php include __DIR__ . '/../../components/reserva-contexto.php'; ?>
        <?php endif; ?>

        <div class="rsv2__layout">

            <!-- FORMULARIO TICKET — Paso 1: Café & Pase -->
            <section class="rsv2-card rsv2-card--form" x-cloak>
                <form class="booking-ticket" method="POST" action="/reservar/paso-1" autocomplete="off">
                    <?= Csrf::field() ?>

                    <header class="booking-ticket__header">
                        <div class="booking-ticket__kanji">予約</div>
                        <div class="booking-ticket__titles">
                            <h3 class="booking-ticket__title">Tu pase Komorebi</h3>
                            <p class="booking-ticket__subtitle">Selecciona una experiencia obligatoria.</p>
                        </div>
                        <div class="booking-ticket__step">Paso 1/3</div>
                    </header>

                    <!-- Indicador de pasos (estático — paso 1) -->
                    <div class="booking-steps" aria-label="Progreso de reserva">
                        <div class="booking-steps__item is-active">
                            <div class="booking-steps__circle" aria-hidden="true">1</div>
                            <span class="booking-steps__label">Café &amp; Pase</span>
                        </div>
                        <div class="booking-steps__line" aria-hidden="true"></div>
                        <div class="booking-steps__item">
                            <div class="booking-steps__circle" aria-hidden="true">2</div>
                            <span class="booking-steps__label">Fecha &amp; Hora</span>
                        </div>
                        <div class="booking-steps__line" aria-hidden="true"></div>
                        <div class="booking-steps__item">
                            <div class="booking-steps__circle" aria-hidden="true">3</div>
                            <span class="booking-steps__label">Confirmar</span>
                        </div>
                    </div>
                    <div class="booking-progress" aria-hidden="true">
                        <div class="booking-progress__bar">
                            <div class="booking-progress__fill" style="width:33%"></div>
                        </div>
                    </div>

                    <!-- Café -->
                    <div class="booking-section">
                        <div class="booking-section__title">
                            <span class="booking-dot">1</span> Café
                        </div>
                        <select name="cafe_id" class="form-select" x-model="selectedCafeId" required>
                            <option value="" disabled>Selecciona un café...</option>
                            <template x-for="c in cafes" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Personas & Pase -->
                    <div class="booking-section" x-show="selectedCafeId" x-transition>
                        <div class="booking-section__title">
                            <span class="booking-dot">2</span> Personas &amp; Pase
                        </div>

                        <div class="booking-row" style="margin-bottom:.75rem;">
                            <div class="booking-row__label">Personas</div>
                            <div class="stepper-fancy">
                                <button type="button" class="stepper-btn" @click="decrementar" :disabled="personas<=1">-</button>
                                <div class="stepper-val" x-text="personas"></div>
                                <button type="button" class="stepper-btn" @click="incrementar" :disabled="personas>=6">+</button>
                            </div>
                            <input type="hidden" name="guests" :value="personas">
                        </div>

                        <div class="booking-pass-grid">
                            <template x-for="p in pasesDisponibles" :key="p.id">
                                <label class="booking-pass"
                                    :class="{ 'booking-pass--selected': String(selectedPassId)===String(p.id) }">
                                    <input type="radio" name="pass_product_id" :value="p.id" x-model="selectedPassId" required>
                                    <img class="booking-pass__img" :src="p.image_url || '/images/ui/placeholder.jpg'" alt="">
                                    <div class="booking-pass__body">
                                        <div class="booking-pass__top">
                                            <div class="booking-pass__name" x-text="p.name"></div>
                                            <div class="booking-pass__price" x-text="priceLabel(p)"></div>
                                        </div>
                                        <div class="booking-pass__jp" x-text="p.japanese_name ? `（${p.japanese_name}）` : ''"></div>
                                        <div class="booking-pass__description" x-show="p.description" x-text="p.description || ''"></div>
                                        <div class="booking-pass__meta">
                                            <span class="badge-mini">⏱️ <span x-text="(p.duration_minutes || 0) + ' min'"></span></span>
                                            <span class="badge-mini">👥 <span x-text="'Pax ' + (p.min_pax || 1) + (p.max_pax ? ('-' + p.max_pax) : '+')"></span></span>
                                            <template x-if="passAnimalLabel(p) !== ''">
                                                <span class="badge-mini">🐾 <span x-text="passAnimalLabel(p)"></span></span>
                                            </template>
                                        </div>
                                        <div class="booking-pass__badges">
                                            <template x-for="b in passBadges(p)" :key="b.label">
                                                <span class="booking-mini-badge">
                                                    <span x-text="b.icon"></span>
                                                    <span x-text="b.label"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </label>
                            </template>
                        </div>

                        <p class="booking-hint text-error" x-show="pasesDisponibles.length === 0">
                            No hay pases disponibles para este café y número de personas.
                        </p>
                    </div>

                    <!-- Continuar -->
                    <div class="booking-summary" x-show="selectedCafeId && selectedPassId" x-transition>
                        <button type="submit" class="btn btn--primario booking-btn-confirm">
                            Continuar →
                        </button>
                        <p class="booking-note">Pago en el local · Experiencia obligatoria.</p>
                    </div>

                </form>
            </section>

            <!-- HISTORIAL -->
            <section class="rsv2-card rsv2-card--history">
                <h3 class="rsv2-card__title">
                    Historial <span class="rsv2-count" x-text="'(' + historial.length + ')'"></span>
                </h3>

                <div class="rsv2-history">
                    <template x-if="historialLoading">
                        <p class="rsv2-empty">Cargando historial…</p>
                    </template>
                    <template x-if="!historialLoading && historial.length === 0">
                        <p class="rsv2-empty">Aún no tienes reservas.</p>
                    </template>
                    <template x-for="res in historial" :key="res.id">
                        <article class="rsv2-item" :class="{ 'rsv2-item--dim': isPast(res) }">
                            <div>
                                <p class="rsv2-item__title" x-text="res.cafe_name ?? 'Café'"></p>
                                <p class="rsv2-item__meta"
                                    x-text="formatFecha(res.reservation_date) + ' · ' + (res.reservation_time||'').substring(0,5) + ' · ' + (res.guest_count ?? 1) + ' pers.'">
                                </p>
                                <div class="rsv2-pill"
                                    :class="'rsv2-pill--' + (res.status||'')"
                                    x-text="statusLabel(res.status)"></div>
                            </div>
                            <div class="rsv2-item__actions">
                                <div class="rsv2-ref" x-text="'#' + res.id"></div>
                                <template x-if="isCancelable(res)">
                                    <button type="button" class="btn-danger-outline rsv2-btn-cancel"
                                        @click="if(confirm('¿Seguro que deseas cancelar?')) cancelReservation(res.id)">
                                        Cancelar
                                    </button>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>
            </section>

        </div>
    </div>
</section>
