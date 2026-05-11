<?php

declare(strict_types=1);

/**
 * Vista: Reservas — layout accordion + sidebar resumen
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
    <script src="/js/sections/reservas.js?v=16"></script>
    <script src="/js/dietary-preferences.js"></script>

    <div class="seccion__container rsv2"
        x-data="reservaForm(<?= $cartTotal ?>, <?= e((string) json_encode($festivos ?? [])) ?>)"
        x-cloak>

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

        <!-- Layout: sidebar + accordion steps -->
        <div class="rsv3-layout">

            <!-- ── SIDEBAR RESUMEN ── -->
            <aside class="rsv3-sidebar">

                <div class="rsv3-sidebar__cafe">
                    <div class="rsv3-sidebar__cafe-img-wrap">
                        <img x-show="cafeActivo && cafeActivo.image_url"
                            :src="cafeActivo ? (cafeActivo.image_url || '') : ''"
                            :alt="cafeActivo ? cafeActivo.name : ''"
                            class="rsv3-sidebar__cafe-img" loading="lazy">
                        <svg x-show="!cafeActivo || !cafeActivo.image_url"
                            class="rsv3-sidebar__cafe-placeholder-svg"
                            viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true">
                            <rect width="80" height="80" rx="8" fill="var(--color-superficie-elevada,#f5f0e8)" />
                            <text x="40" y="52" text-anchor="middle" font-size="36">🍵</text>
                        </svg>
                    </div>
                    <p class="rsv3-sidebar__cafe-name"
                        x-text="cafeActivo ? cafeActivo.name : 'Elige un café'"></p>
                    <p class="rsv3-sidebar__cafe-city"
                        x-text="cafeActivo ? (cafeActivo.city ?? '') : ''"></p>
                </div>

                <!-- Widget de clima en sidebar -->
                <div class="rsv3-sidebar__weather" x-show="sidebarWeather || sidebarWeatherLoading">
                    <template x-if="sidebarWeatherLoading">
                        <div class="rsv3-sidebar__weather-content">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                            <span>Cargando clima…</span>
                        </div>
                    </template>
                    <template x-if="!sidebarWeatherLoading && sidebarWeather">
                        <div class="rsv3-sidebar__weather-content">
                            <i class="bi bi-cloud-sun" aria-hidden="true"></i>
                            <span x-text="sidebarWeather.description"></span>
                            <strong x-text="sidebarWeather.temp + '°C'"></strong>
                        </div>
                    </template>
                </div>

                <dl class="rsv3-sidebar__summary">
                    <dt class="rsv3-sidebar__dt">Pase</dt>
                    <dd class="rsv3-sidebar__dd" x-text="passActivo ? passActivo.name : '—'"></dd>

                    <dt class="rsv3-sidebar__dt">Personas</dt>
                    <dd class="rsv3-sidebar__dd" x-text="personas ? personas + ' pers.' : '—'"></dd>

                    <dt class="rsv3-sidebar__dt">Fecha</dt>
                    <dd class="rsv3-sidebar__dd" x-text="fecha ? formatFecha(fecha) : '—'"></dd>

                    <dt class="rsv3-sidebar__dt">Hora</dt>
                    <dd class="rsv3-sidebar__dd" x-text="hora || '—'"></dd>
                </dl>

                <div class="rsv3-sidebar__total">
                    <span>Total</span>
                    <span x-text="grandTotal > 0 ? formatEuro(grandTotal) : '—'"></span>
                </div>

                <template x-if="hasUnusedInclusions">
                    <p class="rsv3-sidebar-inclusion-hint">
                        <i class="bi bi-gift" aria-hidden="true"></i>
                        Tienes inclusiones del pase sin seleccionar
                    </p>
                </template>

                <button class="rsv3-sidebar__btn"
                    :disabled="!canSubmit || submitting"
                    @click="submitReservation()">
                    <span x-show="!submitting">Confirmar reserva</span>
                    <span x-show="submitting" aria-live="polite">Procesando…</span>
                </button>

                <p class="rsv3-sidebar__error" x-show="submitError" x-text="submitError"
                    role="alert" aria-live="assertive"></p>

                <p style="font-size:.75rem;color:var(--color-texto-suave);text-align:center;margin:0">
                    Pago en el local · Experiencia obligatoria
                </p>

            </aside>

            <!-- ── ACCORDION STEPS ── -->
            <div class="rsv3-steps">

                <!-- Paso 1: Café -->
                <div class="rsv3-step"
                    :class="{ 'rsv3-step--active': openStep === 1, 'rsv3-step--done': selectedCafeId && openStep !== 1 }">

                    <div class="rsv3-step__header" @click="toggleStep(1)" role="button" tabindex="0"
                        @keydown.enter="toggleStep(1)" @keydown.space.prevent="toggleStep(1)"
                        :aria-expanded="openStep === 1">
                        <div class="rsv3-step__num">
                            <template x-if="selectedCafeId && openStep !== 1">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </template>
                            <template x-if="!(selectedCafeId && openStep !== 1)">
                                <span>1</span>
                            </template>
                        </div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Elige un café</p>
                            <p class="rsv3-step__preview" x-text="cafeActivo ? cafeActivo.name : 'Ninguno seleccionado'"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">
                        <select class="form-select" x-model="selectedCafeId" required
                            aria-label="Selecciona un café">
                            <option value="" disabled>Selecciona un café...</option>
                            <template x-for="c in cafes" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                        <div style="margin-top:.75rem;display:flex;justify-content:flex-end">
                            <button type="button" class="btn btn--primario btn--sm"
                                :disabled="!selectedCafeId"
                                @click="if(selectedCafeId) openStep = 2">
                                Siguiente <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paso 2: Personas -->
                <div class="rsv3-step"
                    :class="{
                        'rsv3-step--active': openStep === 2,
                        'rsv3-step--done': openStep > 2,
                        'rsv3-step--locked': !selectedCafeId
                    }">

                    <div class="rsv3-step__header"
                        @click="if(selectedCafeId) toggleStep(2)"
                        role="button" tabindex="0"
                        @keydown.enter="if(selectedCafeId) toggleStep(2)"
                        @keydown.space.prevent="if(selectedCafeId) toggleStep(2)"
                        :aria-expanded="openStep === 2">
                        <div class="rsv3-step__num">
                            <template x-if="openStep > 2">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </template>
                            <template x-if="openStep <= 2">
                                <span>2</span>
                            </template>
                        </div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Número de invitados</p>
                            <p class="rsv3-step__preview" x-text="personas + ' persona' + (personas !== 1 ? 's' : '')"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">
                        <p style="font-size:.85rem;color:var(--color-texto-suave);margin-bottom:.75rem">
                            El número de invitados determina qué pases están disponibles.
                        </p>
                        <div class="rsv3-stepper" aria-label="Número de personas">
                            <button type="button" class="rsv3-stepper__btn"
                                @click="decrementar" :disabled="personas <= 1"
                                aria-label="Reducir personas">−</button>
                            <span class="rsv3-stepper__val" x-text="personas"
                                aria-live="polite" aria-atomic="true"></span>
                            <button type="button" class="rsv3-stepper__btn"
                                @click="incrementar" :disabled="personas >= 10"
                                aria-label="Añadir persona">+</button>
                        </div>
                        <div style="margin-top:.75rem;display:flex;justify-content:flex-end">
                            <button type="button" class="btn btn--primario btn--sm"
                                @click="openStep = 3">
                                Siguiente <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Pase -->
                <div class="rsv3-step"
                    :class="{
                        'rsv3-step--active': openStep === 3,
                        'rsv3-step--done': selectedPassId && openStep !== 3,
                        'rsv3-step--locked': !selectedCafeId
                    }">

                    <div class="rsv3-step__header"
                        @click="if(selectedCafeId) toggleStep(3)"
                        role="button" tabindex="0"
                        @keydown.enter="if(selectedCafeId) toggleStep(3)"
                        @keydown.space.prevent="if(selectedCafeId) toggleStep(3)"
                        :aria-expanded="openStep === 3">
                        <div class="rsv3-step__num">
                            <template x-if="selectedPassId && openStep !== 3">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </template>
                            <template x-if="!(selectedPassId && openStep !== 3)">
                                <span>3</span>
                            </template>
                        </div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Elige tu pase</p>
                            <p class="rsv3-step__preview"
                                x-text="passActivo ? passActivo.name + ' · ' + priceLabel(passActivo) : 'Ninguno seleccionado'"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">

                        <p class="booking-hint text-error" x-show="pasesDisponibles.length === 0" style="margin-top:.5rem">
                            No hay pases disponibles para este café y número de personas.
                        </p>

                        <div class="rsv3-pass-grid">
                            <template x-for="p in pasesDisponibles" :key="p.id">
                                <div class="rsv3-pass"
                                    :class="{ 'rsv3-pass--selected': String(selectedPassId) === String(p.id) }"
                                    @click="selectedPassId = String(p.id)"
                                    role="radio"
                                    :aria-checked="String(selectedPassId) === String(p.id) ? 'true' : 'false'"
                                    tabindex="0"
                                    @keydown.enter="selectedPassId = String(p.id)"
                                    @keydown.space.prevent="selectedPassId = String(p.id)">

                                    <p class="rsv3-pass__name" x-text="p.name"></p>

                                    <p class="rsv3-pass__price" x-text="priceLabel(p)"></p>

                                    <p class="rsv3-pass__desc"
                                        x-show="p.description"
                                        x-text="p.description || ''"></p>

                                    <div class="rsv3-pass__meta">
                                        <span class="badge-mini">
                                            <i class="bi bi-stopwatch" aria-hidden="true"></i>
                                            <span x-text="(p.duration_minutes || 0) + ' min'"></span>
                                        </span>
                                        <span class="badge-mini">
                                            <i class="bi bi-people" aria-hidden="true"></i>
                                            <span x-text="'Pax ' + (p.min_pax || 1) + (p.max_pax ? ('-' + p.max_pax) : '+')"></span>
                                        </span>
                                    </div>

                                    <!-- Badges de atributos del pase -->
                                    <div class="rsv3-pass__badges" x-show="passBadges(p).length > 0">
                                        <template x-for="badge in passBadges(p)" :key="badge.label">
                                            <span class="badge-inclusion">
                                                <i :class="'bi ' + badge.icon" aria-hidden="true"></i>
                                                <span x-text="badge.label"></span>
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Inclusiones del pase -->
                                    <ul class="rsv3-pass__inclusions"
                                        x-show="p.inclusions && p.inclusions.length > 0">
                                        <template x-if="p.inclusions && p.inclusions.length > 0">
                                            <template x-for="inc in p.inclusions" :key="inc.category_id">
                                                <li class="rsv3-pass__inclusion-item">
                                                    <span class="badge-inclusion">INCLUIDO</span>
                                                    <span class="rsv3-pass__inclusion-cat"
                                                        x-text="inc.category_name"></span>
                                                    <template x-if="inc.max_unit_price">
                                                        <small class="rsv3-pass__inclusion-limit"
                                                            x-text="'hasta ' + formatEuro(inc.max_unit_price)"></small>
                                                    </template>
                                                </li>
                                            </template>
                                        </template>
                                    </ul>

                                </div>
                            </template>
                        </div>

                        <div style="margin-top:.75rem;display:flex;justify-content:flex-end">
                            <button type="button" class="btn btn--primario btn--sm"
                                :disabled="!selectedPassId"
                                @click="if(selectedPassId) openStep = 4">
                                Siguiente <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php include __DIR__ . '/partials/inclusion-selector.php'; ?>
                    </div><!-- /.rsv3-step__body paso 3 -->
                </div><!-- /.rsv3-step paso 3 -->

                <!-- Paso 4: Fecha & Hora -->
                <div class="rsv3-step"
                    :class="{
                        'rsv3-step--active': openStep === 4,
                        'rsv3-step--done': fecha && hora && openStep !== 4,
                        'rsv3-step--locked': !selectedPassId
                    }">

                    <div class="rsv3-step__header"
                        @click="if(selectedPassId) toggleStep(4)"
                        role="button" tabindex="0"
                        @keydown.enter="if(selectedPassId) toggleStep(4)"
                        @keydown.space.prevent="if(selectedPassId) toggleStep(4)"
                        :aria-expanded="openStep === 4">
                        <div class="rsv3-step__num">
                            <template x-if="fecha && hora && openStep !== 4">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </template>
                            <template x-if="!(fecha && hora && openStep !== 4)">
                                <span>4</span>
                            </template>
                        </div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Fecha & Hora</p>
                            <p class="rsv3-step__preview"
                                x-text="fecha && hora ? formatFecha(fecha) + ' · ' + hora : 'Sin seleccionar'"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">
                        <!-- Fecha -->
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-input" x-model="fecha"
                            :min="minDate" required aria-required="true"
                            aria-label="Fecha de reserva">

                        <!-- Clima y festivos -->
                        <div x-show="fecha && (weatherData || holidayData || loadingWeather || loadingHoliday)"
                            x-transition style="margin-top:.75rem">
                            <div class="booking-info-box">
                                <div class="booking-info-item" x-show="loadingWeather" x-transition>
                                    <div class="booking-info-item__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                                    <div class="booking-info-item__content">
                                        <div class="booking-info-item__title">Consultando clima...</div>
                                    </div>
                                </div>
                                <div class="booking-info-item" x-show="!loadingWeather && weatherData && !forecastUnavailable" x-transition>
                                    <div class="booking-info-item__icon"><i class="bi bi-cloud-sun" aria-hidden="true"></i></div>
                                    <div class="booking-info-item__content">
                                        <div class="booking-info-item__title">
                                            <span x-show="!weatherData?.is_forecast">Tiempo actual</span>
                                            <span x-show="weatherData?.is_forecast">
                                                Previsión para
                                                <span x-text="hora || fecha"></span>
                                            </span>
                                        </div>
                                        <div class="booking-weather">
                                            <span x-text="(weatherData && weatherData.description) || ''"></span>
                                            <span class="booking-weather__temp"
                                                x-show="weatherData && weatherData.temp"
                                                x-text="((weatherData && weatherData.temp) || 0) + '°C'"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="booking-info-item" x-show="!loadingWeather && forecastUnavailable" x-transition>
                                    <div class="booking-info-item__icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></div>
                                    <div class="booking-info-item__content">
                                        <div class="booking-info-item__title">Previsión no disponible</div>
                                        <div class="booking-info-item__text">
                                            La previsión meteorológica solo está disponible para fechas dentro de los próximos ~16 días.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hora / turnos -->
                        <div style="margin-top:1rem" x-show="fecha">
                            <label class="form-label">Turno</label>
                            <div class="rsv3-slot-grid" role="group" aria-label="Selecciona un turno"
                                x-show="!loadingSlots">
                                <template x-for="s in allSlots" :key="s.time">
                                    <div class="rsv3-slot-wrapper">
                                        <button type="button" class="rsv3-slot"
                                            :class="{ 'rsv3-slot--selected': hora === s.time, 'rsv3-slot--full': !s.available }"
                                            :disabled="!s.available"
                                            @click="if(s.available) hora = s.time"
                                            :aria-pressed="hora === s.time ? 'true' : 'false'"
                                            :aria-label="s.time + (s.available ? (s.occupied_guests > 0 ? ' · ' + s.occupied_guests + '/' + s.total_capacity + ' personas' : '') : ' (completo)')">
                                            <span x-text="s.time"></span>
                                            <span class="rsv3-slot__occ" x-show="s.occupied_guests > 0" x-text="s.occupied_guests + '/' + s.total_capacity"></span>
                                            <span class="rsv3-slot__full-label" x-show="!s.available">Completo</span>
                                        </button>
                                        <button type="button" class="rsv3-slot__waitlist-btn"
                                            x-show="!s.available"
                                            @click="waitlistTargetTime = s.time; openWaitlistModal()">
                                            Lista de espera
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div x-show="loadingSlots" style="padding:.5rem 0">
                                <span class="booking-hint">
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                    Cargando turnos…
                                </span>
                            </div>
                            <p class="booking-hint text-error" x-show="!loadingSlots && slotsError" x-text="slotsError"></p>
                            <p class="booking-hint" x-show="!loadingSlots && !slotsError && allSlots.length === 0 && fecha">
                                No hay turnos disponibles para este pase en esta fecha.
                            </p>
                        </div>

                        <div style="margin-top:.75rem;display:flex;justify-content:flex-end">
                            <button type="button" class="btn btn--primario btn--sm"
                                :disabled="!fecha || !hora"
                                @click="if(fecha && hora) { openStep = 5; loadProductos(); loadAlergenos(); }">
                                Siguiente <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div><!-- /.rsv3-step paso 4 -->

                <!-- Paso 5: Comanda opcional -->
                <div class="rsv3-step"
                    :class="{
                        'rsv3-step--active': openStep === 5,
                        'rsv3-step--locked': !fecha || !hora
                    }"
                    x-data>

                    <div class="rsv3-step__header"
                        @click="if(fecha && hora) { toggleStep(5); if(openStep===5){ loadProductos(); loadAlergenos(); } }"
                        role="button" tabindex="0"
                        @keydown.enter="if(fecha && hora) toggleStep(5)"
                        @keydown.space.prevent="if(fecha && hora) toggleStep(5)"
                        :aria-expanded="openStep === 5">
                        <div class="rsv3-step__num">5</div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Comanda opcional</p>
                            <p class="rsv3-step__preview" x-text="comandaHasItems ? comandaBreakdown.length + ' producto(s)' : 'Puedes añadir productos de la carta'"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">

                        <!-- Spinner carga inicial -->
                        <div x-show="loadingProductos || loadingAlergenos" class="rsv3-comanda-loading">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                            Cargando carta&hellip;
                        </div>

                        <template x-if="!loadingProductos && !loadingAlergenos">
                            <div>

                                <!-- ── Filtro alérgenos ─────────────────────────────── -->
                                <template x-if="alergenos.length > 0">
                                    <div class="rsv3-comanda-alergenos mb-3">
                                        <p class="rsv3-comanda-alergenos__label small text-muted mb-1">
                                            <i class="bi bi-funnel" aria-hidden="true"></i>
                                            Excluir alérgenos:
                                        </p>
                                        <div class="d-flex flex-wrap gap-1">
                                            <template x-for="al in alergenos" :key="al.id">
                                                <button type="button"
                                                    class="rsv3-chip"
                                                    :class="alergenosExcluidos.includes(al.id) ? 'rsv3-chip--active' : ''"
                                                    @click="toggleAlergeno(al.id)"
                                                    :aria-pressed="alergenosExcluidos.includes(al.id)"
                                                    x-text="al.name">
                                                </button>
                                            </template>
                                            <button type="button"
                                                class="rsv3-chip rsv3-chip--clear"
                                                x-show="alergenosExcluidos.length > 0"
                                                @click="sinAlergenos()">
                                                <i class="bi bi-x-circle" aria-hidden="true"></i>
                                                Quitar filtros
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- ── Panel de quotas del pase ─────────────────────── -->
                                <template x-if="passActivo?.inclusions?.length">
                                    <div class="rsv3-comanda-quotas mb-3">
                                        <p class="rsv3-comanda-quotas__label small fw-semibold mb-2">
                                            <i class="bi bi-gift" aria-hidden="true"></i>
                                            Incluido en tu pase:
                                        </p>
                                        <template x-for="q in quotasPorCategoria" :key="q.category_id">
                                            <div class="rsv3-comanda-quota mb-2">
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span x-text="q.category_name"></span>
                                                    <span>
                                                        <span x-text="q.used"></span>/<span x-text="q.max_units"></span>
                                                        <template x-if="q.max_unit_price !== null">
                                                            <span class="text-muted ms-1">
                                                                (hasta <span x-text="formatEuro(q.max_unit_price)"></span>)
                                                            </span>
                                                        </template>
                                                    </span>
                                                </div>
                                                <div class="progress" style="height:6px">
                                                    <div class="progress-bar bg-success"
                                                        :style="`width:${q.max_units > 0 ? Math.round(q.used/q.max_units*100) : 0}%`"
                                                        role="progressbar"
                                                        :aria-valuenow="q.used"
                                                        :aria-valuemax="q.max_units">
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- ── Tabs de categorías ────────────────────────────── -->
                                <div class="rsv3-comanda-cats" x-show="categoriasDisponibles.length > 0">
                                    <button type="button" class="rsv3-comanda-cat"
                                        :class="{'rsv3-comanda-cat--active': comandaCatActiva === 0}"
                                        @click="comandaCatActiva = 0">Todos</button>
                                    <template x-for="cat in categoriasDisponibles" :key="cat.id">
                                        <button type="button" class="rsv3-comanda-cat"
                                            :class="{'rsv3-comanda-cat--active': comandaCatActiva === cat.id}"
                                            @click="comandaCatActiva = cat.id"
                                            x-text="cat.name">
                                        </button>
                                    </template>
                                </div>

                                <!-- ── Tip primera vez (inclusiones disponibles, carrito vacío) ── -->
                                <template x-if="quotasPorCategoria.length > 0 && !comandaHasItems">
                                    <div class="rsv3-comanda-tip">
                                        <i class="bi bi-stars" aria-hidden="true"></i>
                                        <div>
                                            <strong>¡Tu pase incluye artículos gratuitos!</strong>
                                            <p>Añade productos de las categorías incluidas y se descontarán automáticamente del total.</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- ── Grid de productos ─────────────────────────────── -->
                                <div class="rsv3-comanda-grid" x-show="productosPorCategoria.length > 0">
                                    <template x-for="p in productosPorCategoria" :key="p.id">
                                        <div class="rsv3-comanda-item"
                                            :class="{
                                                'rsv3-comanda-item--excluido': productoContieneAlergenoExcluido(p),
                                                'rsv3-comanda-item--agotado': p.stock_quantity !== null && p.stock_quantity !== undefined && Number(p.stock_quantity) === 0
                                            }">
                                            <!-- Imagen -->
                                            <template x-if="p.image_url">
                                                <img :src="p.image_url" :alt="p.name" class="rsv3-comanda-item__img" loading="lazy">
                                            </template>

                                            <div class="rsv3-comanda-item__body">
                                                <div class="rsv3-comanda-item__info">
                                                    <span class="rsv3-comanda-item__name" x-text="p.name"></span>
                                                    <template x-if="p.japanese_name">
                                                        <span class="rsv3-comanda-item__jp small text-muted" x-text="p.japanese_name"></span>
                                                    </template>
                                                    <span class="rsv3-comanda-item__price" x-text="formatEuro(p.price)"></span>
                                                </div>

                                                <!-- Badge alérgeno excluido -->
                                                <template x-if="productoContieneAlergenoExcluido(p)">
                                                    <p class="rsv3-comanda-item__warning small text-warning mb-1">
                                                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                                        Contiene alérgenos excluidos
                                                    </p>
                                                </template>

                                                <!-- Badge agotado -->
                                                <template x-if="p.stock_quantity !== null && p.stock_quantity !== undefined && Number(p.stock_quantity) === 0">
                                                    <span class="badge bg-secondary">Agotado</span>
                                                </template>

                                                <!-- Controles qty -->
                                                <div class="rsv3-comanda-item__controls"
                                                    x-show="!(p.stock_quantity !== null && p.stock_quantity !== undefined && Number(p.stock_quantity) === 0)">
                                                    <button type="button" class="rsv3-qty-btn"
                                                        x-show="cantidadEnComanda(p.id) > 0"
                                                        @click="removeFromComanda(p.id)"
                                                        aria-label="Quitar uno">&minus;</button>
                                                    <span class="rsv3-qty-val"
                                                        x-show="cantidadEnComanda(p.id) > 0"
                                                        x-text="cantidadEnComanda(p.id)"></span>
                                                    <button type="button" class="rsv3-qty-btn rsv3-qty-btn--add"
                                                        @click="addToComanda(p)"
                                                        :disabled="productoContieneAlergenoExcluido(p)"
                                                        aria-label="Añadir uno">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- ── Desglose comanda ──────────────────────────────── -->
                                <template x-if="comandaHasItems">
                                    <div class="rsv3-comanda-breakdown">
                                        <p class="rsv3-comanda-breakdown__title">Tu pre-comanda</p>
                                        <template x-for="item in comandaBreakdown" :key="item.id">
                                            <div class="rsv3-comanda-breakdown__row">
                                                <span x-text="item.qty + '× ' + item.name"></span>
                                                <span>
                                                    <template x-if="item.slots_incluidos > 0 && item.qty_cobrada === 0">
                                                        <span class="rsv3-comanda-inc-label">Incluido en pase</span>
                                                    </template>
                                                    <template x-if="item.slots_incluidos > 0 && item.qty_cobrada > 0">
                                                        <span>
                                                            <span class="rsv3-comanda-inc-label" x-text="item.slots_incluidos + ' incluido(s)'"></span>
                                                            &nbsp;+&nbsp;
                                                            <span x-text="formatEuro(item.total_cents)"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="item.slots_incluidos === 0">
                                                        <span x-text="formatEuro(item.total_cents)"></span>
                                                    </template>
                                                </span>
                                            </div>
                                        </template>
                                        <div class="rsv3-comanda-breakdown__total">
                                            <span>Total adicional</span>
                                            <span x-text="formatEuro(comandaTotal)"></span>
                                        </div>
                                        <p class="small text-muted mt-1">IVA incluido · Pago en el local</p>
                                    </div>
                                </template>

                                <!-- ── Warning inclusiones sin usar ─────────────────── -->
                                <template x-if="hasUnusedInclusions">
                                    <div class="rsv3-inclusion-warning" role="alert">
                                        <i class="bi bi-gift" aria-hidden="true"></i>
                                        <div>
                                            <strong>¡Tienes artículos incluidos sin usar!</strong>
                                            <p>Tu pase incluye productos gratuitos. Puedes añadirlos ahora o continuar sin ellos.</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- ── Acciones ──────────────────────────────────────── -->
                                <div class="rsv3-comanda-actions">
                                    <button type="button" class="btn btn--secundario btn--sm"
                                        @click="openStep = 6">
                                        Omitir este paso
                                    </button>
                                    <button type="button" class="btn btn--primario btn--sm"
                                        @click="openStep = 6">
                                        Añadir a mi reserva
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    </button>
                                </div>

                            </div>
                        </template>
                    </div>
                </div><!-- /.rsv3-step paso 5 comanda -->

                <!-- Paso 6: Notas -->
                <div class="rsv3-step"
                    :class="{
                        'rsv3-step--active': openStep === 6,
                        'rsv3-step--locked': !selectedPassId
                    }">

                    <div class="rsv3-step__header"
                        @click="if(selectedPassId) toggleStep(6)"
                        role="button" tabindex="0"
                        @keydown.enter="if(selectedPassId) toggleStep(6)"
                        @keydown.space.prevent="if(selectedPassId) toggleStep(6)"
                        :aria-expanded="openStep === 6">
                        <div class="rsv3-step__num">6</div>
                        <div class="rsv3-step__meta">
                            <p class="rsv3-step__title">Notas adicionales</p>
                            <p class="rsv3-step__preview" x-text="comentarios ? comentarios.substring(0,40) + '…' : 'Opcional'"></p>
                        </div>
                        <i class="bi bi-chevron-down rsv3-step__chevron" aria-hidden="true"></i>
                    </div>

                    <div class="rsv3-step__body">
                        <label class="form-label" for="rsv3-comentarios">
                            Alergias, celebraciones, peticiones especiales…
                        </label>
                        <textarea id="rsv3-comentarios" class="form-textarea" rows="3"
                            x-model="comentarios"
                            placeholder="Ej: Alergia a los frutos secos, es mi cumpleaños…"></textarea>
                    </div>
                </div>

                <!-- ── HISTORIAL ── -->
                <div class="rsv3-historial">
                    <h3 class="rsv3-historial__title">
                        Historial de reservas
                        <span style="font-weight:400;font-size:.85rem;color:var(--color-texto-suave)"
                            x-text="'(' + historial.length + ')'"></span>
                    </h3>

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

            </div><!-- /.rsv3-steps -->

        </div><!-- /.rsv3-layout -->
    </div>
</section>
