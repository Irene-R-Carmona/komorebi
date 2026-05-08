<?php

declare(strict_types=1);

use App\Support\CurrencyFormatting;

/**
 * Vista: Reservas (pase obligatorio)
 *
 * Variables esperadas:
 * - array $reservas
 * - array $cart
 * - array $cartDetails
 * - array|null $flash
 */
$cartTotal = (float) ($cart['totalPrice'] ?? 0);
?>
<section class="seccion seccion--activa">
    <script src="/js/sections/reservas.js?v=11"></script>
    <script src="/js/dietary-preferences.js"></script>

    <div class="seccion__container rsv2"
        x-data="reservaForm(<?= $cartTotal ?>, <?= e((string) json_encode($festivos ?? [])) ?>)">

        <?php if (!empty($flash)): ?>
            <div class="toast <?= ($flash['type'] ?? '') === 'success' ? 'toast--exito' : 'toast--error' ?> mb-lg">
                <span class="toast__icono"><?= ($flash['type'] ?? '') === 'success' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' : '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' ?></span>
                <span class="toast__mensaje"><?= e((string) ($flash['message'] ?? '')) ?></span>
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

            <!-- FORMULARIO TICKET -->
            <section class="rsv2-card rsv2-card--form" x-cloak>
                <form class="booking-ticket" @submit.prevent="submitReservation()" autocomplete="off">

                    <header class="booking-ticket__header">
                        <div class="booking-ticket__kanji">予約</div>

                        <div class="booking-ticket__titles">
                            <h3 class="booking-ticket__title">Tu pase Komorebi</h3>
                            <p class="booking-ticket__subtitle">Selecciona una experiencia obligatoria.</p>
                        </div>

                        <div class="booking-ticket__step">
                            Paso <span x-text="step"></span>/3
                        </div>
                    </header>

                    <!-- Indicador de pasos -->
                    <div class="booking-steps" role="progressbar" aria-valuenow="<?= 1 ?>" aria-valuemin="1" aria-valuemax="3" aria-label="Progreso de reserva">
                        <div class="booking-steps__item" :class="{'is-active': step >= 1, 'is-done': step > 1}">
                            <div class="booking-steps__circle" aria-hidden="true">
                                <i class="bi bi-check-lg" x-show="step > 1"></i>
                                <span x-show="step <= 1">1</span>
                            </div>
                            <span class="booking-steps__label">Café & Pase</span>
                        </div>
                        <div class="booking-steps__line" :class="{'is-done': step > 1}" aria-hidden="true"></div>
                        <div class="booking-steps__item" :class="{'is-active': step >= 2, 'is-done': step > 2}">
                            <div class="booking-steps__circle" aria-hidden="true">
                                <i class="bi bi-check-lg" x-show="step > 2"></i>
                                <span x-show="step <= 2">2</span>
                            </div>
                            <span class="booking-steps__label">Fecha & Hora</span>
                        </div>
                        <div class="booking-steps__line" :class="{'is-done': step > 2}" aria-hidden="true"></div>
                        <div class="booking-steps__item" :class="{'is-active': step >= 3}">
                            <div class="booking-steps__circle" aria-hidden="true">3</div>
                            <span class="booking-steps__label">Confirmar</span>
                        </div>
                    </div>
                    <div class="booking-progress" aria-hidden="true">
                        <div class="booking-progress__bar">
                            <div class="booking-progress__fill" :style="`width:${progressPercent}%`"></div>
                        </div>
                    </div>

                    <!-- Paso 1: Café & Experiencia -->
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

                    <!-- Paso 2: Personas + Pase -->
                    <div class="booking-section" x-show="selectedCafeId" x-transition>
                        <div class="booking-section__title">
                            <span class="booking-dot">2</span> Personas & Pase
                        </div>

                        <!-- Personas -->
                        <div class="booking-row" style="margin-bottom:.75rem;">
                            <div class="booking-row__label">Personas</div>
                            <div class="stepper-fancy">
                                <button type="button" class="stepper-btn" @click="decrementar" :disabled="personas<=1">
                                    -
                                </button>
                                <div class="stepper-val" x-text="personas"></div>
                                <button type="button" class="stepper-btn" @click="incrementar" :disabled="personas>=6">
                                    +
                                </button>
                            </div>
                            <input type="hidden" name="personas" :value="personas">
                        </div>

                        <!-- Pases -->
                        <div class="booking-pass-grid">
                            <template x-for="p in pasesDisponibles" :key="p.id">
                                <label class="booking-pass"
                                    :class="{ 'booking-pass--selected': String(selectedPassId)===String(p.id) }">

                                    <input type="radio" name="pass_product_id" :value="p.id" x-model="selectedPassId"
                                        required>

                                    <img class="booking-pass__img"
                                        :src="p.image_url || '/images/ui/placeholder.jpg'"
                                        @error="$event.target.src='/images/ui/placeholder.jpg'"
                                        alt="">

                                    <div class="booking-pass__body">
                                        <div class="booking-pass__top">
                                            <div class="booking-pass__name" x-text="p.name"></div>
                                            <div class="booking-pass__price" x-text="priceLabel(p)"></div>
                                        </div>

                                        <div class="booking-pass__jp"
                                            x-text="p.japanese_name ? `（${p.japanese_name}）` : ''"></div>

                                        <div class="booking-pass__description" x-show="p.description"
                                            x-text="p.description || ''"></div>

                                        <div class="booking-pass__meta">
                                            <span class="badge-mini"><i class="bi bi-stopwatch" aria-hidden="true"></i> <span x-text="(p.duration_minutes || 0) + ' min'"></span></span>

                                            <span class="badge-mini">
                                                <i class="bi bi-people" aria-hidden="true"></i> <span x-text="'Pax ' + (p.min_pax || 1) + (p.max_pax ? ('-' + p.max_pax) : '+')"></span>
                                            </span>

                                            <template x-if="passAnimalLabel(p) !== ''">
                                                <span class="badge-mini"><i class="bi bi-house-heart" aria-hidden="true"></i> <span
                                                        x-text="passAnimalLabel(p)"></span></span>
                                            </template>
                                        </div>

                                        <div class="booking-pass__badges">
                                            <template x-for="b in passBadges(p)" :key="b.label">
                                                <span class="booking-mini-badge">
                                                    <i :class="'bi ' + b.icon" aria-hidden="true"></i>
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

                    <!-- Paso 3: Fecha & Hora -->
                    <div class="booking-section" x-show="selectedPassId" x-transition>
                        <div class="booking-section__title">
                            <span class="booking-dot">3</span> Fecha & Hora
                        </div>

                        <!-- Fecha -->
                        <div class="booking-row" style="margin-bottom: 1rem;">
                            <div class="booking-row__label">Fecha</div>
                            <input type="date" name="fecha" class="form-input" x-model="fecha" required
                                :min="minDate"
                                aria-required="true">
                        </div>

                        <!-- Información de clima y festividades -->
                        <div class="booking-info-box" x-show="fecha && (weatherData || holidayData || loadingWeather || loadingHoliday)" x-cloak x-transition>
                            <!-- Clima -->
                            <div class="booking-info-item" x-show="loadingWeather" x-transition>
                                <div class="booking-info-item__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title">Consultando clima...</div>
                                </div>
                            </div>

                            <div class="booking-info-item" x-show="!loadingWeather && weatherData" x-transition>
                                <div class="booking-info-item__icon"><i class="bi bi-cloud-sun" aria-hidden="true"></i></div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title">Clima previsto</div>
                                    <div class="booking-weather">
                                        <span x-text="(weatherData && weatherData.description) || ''  "></span>
                                        <span class="booking-weather__temp" x-show="weatherData && weatherData.temp" x-text="((weatherData && weatherData.temp) || 0) + '°C'"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Festividad -->
                            <div class="booking-info-item" x-show="loadingHoliday" x-transition>
                                <div class="booking-info-item__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title">Verificando festividades...</div>
                                </div>
                            </div>

                            <div class="booking-info-item" x-show="!loadingHoliday && holidayData" x-transition>
                                <div class="booking-info-item__icon"><i class="bi bi-flag" aria-hidden="true"></i></div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title" x-text="(holidayData && holidayData.name) || 'Festividad'"></div>
                                    <div class="booking-info-item__text" x-text="(holidayData && holidayData.description) || ''"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Hora -->
                        <div class="booking-row" style="margin-bottom: 1rem;">
                            <div class="booking-row__label">Turno</div>
                            <div class="booking-slot-grid" role="group" aria-label="Selecciona un turno">
                                <template x-for="h in horariosDisponibles" :key="h">
                                    <button type="button" class="booking-slot"
                                        :class="{ 'booking-slot--selected': hora === h }"
                                        @click="hora = h"
                                        :aria-pressed="hora === h ? 'true' : 'false'"
                                        x-text="h">
                                    </button>
                                </template>
                            </div>
                            <input type="hidden" name="hora" :value="hora">
                            <p class="booking-hint" x-show="horariosDisponibles.length === 0">
                                No hay turnos disponibles con este pase.
                            </p>
                        </div>

                        <!-- Notas -->
                        <div class="booking-row">
                            <div class="booking-row__label">Notas (opcional)</div>
                            <textarea name="comentarios"
                                class="form-textarea"
                                rows="2"
                                x-model="comentarios"
                                placeholder="Alergias, cumpleaños..."></textarea>
                        </div>
                    </div>

                    <!-- Paso 4: Resumen Financiero & Submit -->
                    <div class="booking-summary" x-show="selectedPassId && fecha && hora">
                        <?php if (!empty($cartDetails)): ?>
                            <div class="booking-summary__extras">
                                <div class="booking-summary__extras-title">Extras incluidos</div>
                                <?php foreach ($cartDetails as $item): ?>
                                    <div class="booking-summary__line">
                                        <span><?= (int) $item['qty'] ?>x <?= $item['name'] ?></span>
                                        <span><?= e(CurrencyFormatting::yen((float) $item['subtotal'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="booking-summary__line">
                            <span>Total estimado</span>
                            <strong>¥<span x-text="grandTotal.toLocaleString()"></span></strong>
                        </div>

                        <button type="submit" class="btn btn--primario booking-btn-confirm" :disabled="!canSubmit || submitting">
                            <span x-show="!submitting">Confirmar pase</span>
                            <span x-show="submitting" aria-live="polite">Procesando…</span>
                        </button>

                        <p x-show="submitError" x-text="submitError" class="form-error" role="alert" aria-live="assertive"></p>

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
