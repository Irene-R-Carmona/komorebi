<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Vista: Reservas (pase obligatorio)
 *
 * Variables esperadas:
 * - array $reservas
 * - \App\Core\Raw $cafesJson
 * - \App\Core\Raw $passesJson
 * - array $cart
 * - array $cartDetails
 * - array|null $flash
 */
$cartTotal = (float) ($cart['totalPrice'] ?? 0);
?>
<section class="seccion seccion--activa">
    <script src="/js/sections/reservas.js?v=11"></script>
    <script src="/js/dietary-preferences.js"></script>

    <!-- Debug temporal (remover en producci\u00f3n) -->
    <?php if (isset($_GET['debug'])): ?>
        <div style="background: #f0f0f0; padding: 1rem; margin: 1rem; border: 2px solid #333; font-family: monospace; font-size: 12px;">
            <strong>DEBUG INFO:</strong><br>
            Caf\u00e9s JSON: <?= htmlspecialchars(substr((string) $cafesJson, 0, 200)) ?>...<br>
            Pases JSON: <?= htmlspecialchars(substr((string) $passesJson, 0, 200)) ?>...<br>
            Cart Total: <?= $cartTotal ?><br>
        </div>
    <?php endif; ?>

    <div class="seccion__container rsv2"
        x-data='reservaForm(<?= $cafesJson ?>, <?= $passesJson ?>, <?= $cartTotal ?>, <?= json_encode($festivos ?? []) ?>)'>

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

            <!-- FORMULARIO TICKET -->
            <section class="rsv2-card rsv2-card--form" x-cloak>
                <form class="booking-ticket" method="POST" action="/reservas/crear" autocomplete="off">
                    <?= Csrf::field() ?>

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

                    <!-- Progreso -->
                    <div class="booking-progress">
                        <div class="booking-progress__bar">
                            <div class="booking-progress__fill" :style="`width:${progressPercent}%`"></div>
                        </div>
                        <div class="booking-progress__labels">
                            <span :class="{ 'is-done': step > 1 }">Café & Pase</span>
                            <span :class="{ 'is-done': step > 2 }">Fecha & Hora</span>
                            <span :class="{ 'is-done': step >= 3 }">Confirmar</span>
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
                                            <span class="badge-mini">⏱️ <span x-text="(p.duration_minutes || 0) + ' min'"></span></span>

                                            <span class="badge-mini">
                                                👥 <span x-text="'Pax ' + (p.min_pax || 1) + (p.max_pax ? ('-' + p.max_pax) : '+')"></span>
                                            </span>

                                            <template x-if="passAnimalLabel(p) !== ''">
                                                <span class="badge-mini">🐾 <span
                                                        x-text="passAnimalLabel(p)"></span></span>
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
                                <div class="booking-info-item__icon">⏳</div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title">Consultando clima...</div>
                                </div>
                            </div>

                            <div class="booking-info-item" x-show="!loadingWeather && weatherData" x-transition>
                                <div class="booking-info-item__icon">🌤️</div>
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
                                <div class="booking-info-item__icon">⏳</div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title">Verificando festividades...</div>
                                </div>
                            </div>

                            <div class="booking-info-item" x-show="!loadingHoliday && holidayData" x-transition>
                                <div class="booking-info-item__icon">🎌</div>
                                <div class="booking-info-item__content">
                                    <div class="booking-info-item__title" x-text="(holidayData && holidayData.name) || 'Festividad'"></div>
                                    <div class="booking-info-item__text" x-text="(holidayData && holidayData.description) || ''"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Hora -->
                        <div class="booking-row" style="margin-bottom: 1rem;">
                            <div class="booking-row__label">Turno</div>
                            <select name="hora" class="form-select" x-model="hora" required
                                aria-required="true">
                                <option value="" disabled>Selecciona un turno...</option>
                                <template x-for="h in horariosDisponibles" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
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
                        <div class="booking-summary__line">
                            <span>Total estimado</span>
                            <strong>¥<span x-text="grandTotal.toLocaleString()"></span></strong>
                        </div>

                        <?php if (!empty($cartDetails)): ?>
                            <div class="booking-summary__extras">
                                <div class="booking-summary__extras-title">Extras incluidos</div>
                                <?php foreach ($cartDetails as $item): ?>
                                    <div class="booking-summary__line">
                                        <span><?= (int) $item['qty'] ?>x <?= $item['name'] ?></span>
                                        <span>¥<?= number_format((float) $item['subtotal']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn--primario booking-btn-confirm" :disabled="!canSubmit">
                            Confirmar pase
                        </button>

                        <p class="booking-note">Pago en el local · Experiencia obligatoria.</p>
                    </div>
                </form>
            </section>

            <!-- HISTORIAL -->
            <section class="rsv2-card rsv2-card--history">
                <h3 class="rsv2-card__title">
                    Historial <span class="rsv2-count">(<?= count($reservas ?? []) ?>)</span>
                </h3>

                <div class="rsv2-history">
                    <?php if (empty($reservas)): ?>
                        <p class="rsv2-empty">Aún no tienes reservas.</p>
                    <?php else: ?>
                        <?php foreach ($reservas as $res): ?>
                            <?php
                            $ts = strtotime($res['reservation_date'] . ' ' . $res['reservation_time']);
                            $past = $ts !== false && $ts < time();
                            $status = (string) ($res['status'] ?? '');
                            $cancelable = in_array($status, ['pending', 'confirmed'], true) && !$past;
                            $fechaFmt = date('d/m/Y', strtotime((string) $res['reservation_date']));
                            $horaFmt = substr((string) $res['reservation_time'], 0, 5);
                            ?>
                            <article class="rsv2-item <?= $past ? 'rsv2-item--dim' : '' ?>">
                                <div>
                                    <p class="rsv2-item__title"><?= $res['cafe_name'] ?? 'Café' ?></p>
                                    <p class="rsv2-item__meta">
                                        <?= $fechaFmt ?> · <?= $horaFmt ?> · <?= (int) ($res['guest_count'] ?? 1) ?> pers.
                                    </p>
                                    <p class="rsv2-item__meta">
                                        Pase: <strong><?= $res['pass_name'] ?? '—' ?></strong>
                                        (<?= (int) ($res['pass_duration_minutes'] ?? 0) ?>m)
                                    </p>
                                    <div class="rsv2-pill rsv2-pill--<?= $status ?>"><?= strtoupper($status) ?></div>
                                </div>

                                <div class="rsv2-item__actions">
                                    <div class="rsv2-ref">#<?= (int) $res['id'] ?></div>

                                    <?php if ($cancelable): ?>
                                        <form method="POST" action="/reservas/cancelar"
                                            data-action="confirm" data-confirm="¿Seguro que deseas cancelar?">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $res['id'] ?>">
                                            <button type="submit" class="btn-danger-outline rsv2-btn-cancel">Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </div>
</section>
