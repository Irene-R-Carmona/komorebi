<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Reserva Paso 2 — Fecha y Hora
 *
 * @var array  $wizard   - Datos del wizard (cafe_id, cafe_name, pass_name, guests, etc.)
 * @var array  $festivos - Festivos del año (PHP-injected)
 */
$wizard ??= [];
$festivos ??= [];

$festivos_json = json_encode($festivos, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
$wizard_json = json_encode([
    'cafeId' => (int) ($wizard['cafe_id'] ?? 0),
    'cafeName' => (string) ($wizard['cafe_name'] ?? ''),
    'passName' => (string) ($wizard['pass_name'] ?? ''),
    'guests' => (int) ($wizard['guests'] ?? 1),
    'festivos' => $festivos,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<section class="seccion seccion--activa">
    <div class="seccion__container rsv2">

        <header class="rsv2__header">
            <div>
                <h2 class="rsv2__title">予約 · Paso 2 de 3</h2>
                <p class="rsv2__subtitle">Elige tu fecha y turno.</p>
            </div>
        </header>

        <div class="rsv2__layout rsv2__layout--single">

            <section class="rsv2-card rsv2-card--form" x-data='reservaPaso2(<?= $wizard_json ?>)' x-cloak>

                <!-- Resumen del paso anterior -->
                <div class="booking-summary-mini">
                    <span class="badge-mini"><i class="bi bi-cup-hot" aria-hidden="true"></i> <?= htmlspecialchars((string) ($wizard['cafe_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge-mini"><i class="bi bi-ticket" aria-hidden="true"></i> <?= htmlspecialchars((string) ($wizard['pass_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge-mini"><i class="bi bi-people" aria-hidden="true"></i> <?= (int) ($wizard['guests'] ?? 1) ?> pers.</span>
                    <a href="/reservar" class="booking-edit-link">Cambiar</a>
                </div>

                <form method="POST" action="/reservar/paso-2">
                    <?= Csrf::field() ?>

                    <!-- Fecha -->
                    <div class="booking-section">
                        <div class="booking-section__title">
                            <span class="booking-dot">1</span> Fecha
                        </div>
                        <input type="date" id="rsv2-fecha" name="fecha" class="form-input"
                            x-model="fecha"
                            :min="minDate"
                            required
                            aria-required="true"
                            aria-label="Fecha de reserva">
                        <p class="booking-hint text-error" x-show="festivoBlocked" x-text="festivoMsg" role="alert"></p>
                    </div>

                    <!-- Clima y festivo (reactivo) -->
                    <div class="booking-info-box" x-show="fecha && (weatherData || holidayData || loadingWeather || loadingHoliday)" x-transition>
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
                                    <span x-text="(weatherData && weatherData.description) || ''"></span>
                                    <span class="booking-weather__temp" x-show="weatherData && weatherData.temp"
                                        x-text="((weatherData && weatherData.temp) || 0) + '°C'"></span>
                                </div>
                            </div>
                        </div>
                        <div class="booking-info-item" x-show="!loadingHoliday && holidayData" x-transition>
                            <div class="booking-info-item__icon"><i class="bi bi-flag" aria-hidden="true"></i></div>
                            <div class="booking-info-item__content">
                                <div class="booking-info-item__title" x-text="(holidayData && holidayData.name) || 'Festividad'"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Hora (slots vía AJAX reactivo a fecha) -->
                    <div class="booking-section" x-show="fecha && !festivoBlocked" x-transition>
                        <div class="booking-section__title">
                            <span class="booking-dot">2</span> Turno
                        </div>

                        <div class="booking-info-item" x-show="loadingSlots" x-transition>
                            <div class="booking-info-item__content">
                                <div class="booking-info-item__title">Cargando turnos disponibles...</div>
                            </div>
                        </div>

                        <div class="booking-slot-grid" x-show="!loadingSlots" role="group" aria-label="Selecciona un turno">
                            <template x-for="slot in slots" :key="slot.time">
                                <button type="button" class="booking-slot"
                                    :class="{ 'booking-slot--selected': hora === slot.time, 'booking-slot--full': slot.available <= 0 }"
                                    @click="slot.available > 0 && (hora = slot.time)"
                                    :aria-pressed="hora === slot.time ? 'true' : 'false'"
                                    :disabled="slot.available <= 0"
                                    x-text="slot.time">
                                </button>
                            </template>
                        </div>

                        <p class="booking-hint" x-show="!loadingSlots && slots.length === 0">
                            No hay turnos disponibles para esta fecha.
                        </p>

                        <input type="hidden" name="hora" :value="hora">
                    </div>

                    <!-- Notas -->
                    <div class="booking-section" x-show="hora" x-transition>
                        <div class="booking-section__title">Notas (opcional)</div>
                        <label for="rsv2-comments" class="visually-hidden">Notas adicionales</label>
                        <textarea id="rsv2-comments" name="comments" class="form-textarea" rows="2"
                            placeholder="Alergias, cumpleaños..."></textarea>
                    </div>

                    <div class="modal-actions" x-show="hora" x-transition>
                        <button type="submit" class="btn btn--primario">Continuar →</button>
                        <a href="/reservar" class="btn btn--secundario">Volver</a>
                    </div>

                </form>

            </section>

        </div>
    </div>
</section>

<script nonce="<?= $cspNonce ?? '' ?>">
    (function() {
        const reservaPaso2 = (config = {}) => ({
            fecha: '',
            hora: '',
            minDate: new Date().toISOString().split('T')[0],
            cafeId: config.cafeId || 0,
            festivos: Array.isArray(config.festivos) ? config.festivos : [],
            slots: [],
            loadingSlots: false,
            loadingWeather: false,
            loadingHoliday: false,
            weatherData: null,
            holidayData: null,
            festivoBlocked: false,
            festivoMsg: '',

            async init() {
                this.$watch('fecha', () => this.onFechaChange());
            },

            onFechaChange() {
                this.hora = '';
                this.slots = [];
                this.festivoBlocked = false;
                this.festivoMsg = '';

                if (!this.fecha) return;

                const festivo = this.festivos.find(f => f.fecha === this.fecha);
                if (festivo && !festivo.permite_reservas) {
                    this.festivoBlocked = true;
                    this.festivoMsg = `${festivo.nombre_es} — No se aceptan reservas este día`;
                    this.fecha = '';
                    return;
                }

                this.loadSlots();
                this.loadWeatherAndHoliday();
            },

            async loadSlots() {
                if (!this.fecha || !this.cafeId) return;
                this.loadingSlots = true;
                try {
                    const res = await fetch(`/api/v1/time-slots/available?cafe_id=${this.cafeId}&start_date=${this.fecha}&end_date=${this.fecha}`);
                    if (res.ok) {
                        const json = await res.json();
                        this.slots = (json.data?.slots ?? []).filter(s => s.date === this.fecha);
                    }
                } catch {
                    /* silent — user sees empty slot grid */
                } finally {
                    this.loadingSlots = false;
                }
            },

            async loadWeatherAndHoliday() {
                if (!this.fecha) return;
                this.loadingWeather = true;
                this.loadingHoliday = true;
                try {
                    const [weatherRes, holidayRes] = await Promise.all([
                        fetch(`/api/v1/weather?timezone=${window.CONFIG?.timezone ?? 'Asia/Tokyo'}`),
                        fetch(`/api/v1/holidays/${this.fecha}`),
                    ]);
                    if (weatherRes.ok) {
                        const d = await weatherRes.json();
                        const cur = d.data?.current;
                        this.weatherData = cur ? {
                            temp: Math.round(cur.temp),
                            description: cur.description ?? ''
                        } : null;
                    }
                    if (holidayRes.ok) {
                        const d = await holidayRes.json();
                        this.holidayData = d.data?.is_holiday ? {
                            name: d.data.holiday_name
                        } : null;
                    }
                } catch {
                    /* silent */
                } finally {
                    this.loadingWeather = false;
                    this.loadingHoliday = false;
                }
            },
        });
        globalThis.reservaPaso2 = reservaPaso2;
        document.addEventListener('alpine:init', () => {
            Alpine.data('reservaPaso2', reservaPaso2);
        });
    })();
</script>
