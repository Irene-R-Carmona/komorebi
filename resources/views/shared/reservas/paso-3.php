<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Support\CurrencyFormatting;
use App\Support\DateFormatting;

/**
 * Vista: Reserva Paso 3 — Confirmar (sin JavaScript)
 *
 * @var array  $wizard      - Datos del wizard (cafe_name, pass_name, fecha, hora, guests, etc.)
 * @var array  $cartDetails - Extras del carrito
 * @var float  $passTotal   - Total del pase
 * @var float  $grandTotal  - Total con extras
 */
$wizard ??= [];
$cartDetails ??= [];
$passTotal ??= 0.0;
$grandTotal ??= 0.0;

$fechaFmt = !empty($wizard['fecha'])
    ? DateFormatting::toSpanishDate($wizard['fecha'])
    : '—';
?>

<section class="seccion seccion--activa">
    <div class="seccion__container rsv2">

        <header class="rsv2__header">
            <div>
                <h2 class="rsv2__title">予約 · Paso 3 de 3</h2>
                <p class="rsv2__subtitle">Confirma tu reserva.</p>
            </div>
        </header>

        <div class="rsv2__layout rsv2__layout--single">
            <section class="rsv2-card rsv2-card--form">

                <h3 class="rsv2-card__title">Resumen de tu reserva</h3>

                <dl class="booking-summary-dl">
                    <div class="booking-summary-dl__row">
                        <dt>Café</dt>
                        <dd><?= htmlspecialchars((string) ($wizard['cafe_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="booking-summary-dl__row">
                        <dt>Pase</dt>
                        <dd><?= htmlspecialchars((string) ($wizard['pass_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="booking-summary-dl__row">
                        <dt>Fecha</dt>
                        <dd><?= htmlspecialchars($fechaFmt, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="booking-summary-dl__row">
                        <dt>Hora</dt>
                        <dd><?= htmlspecialchars((string) ($wizard['hora'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="booking-summary-dl__row">
                        <dt>Personas</dt>
                        <dd><?= (int) ($wizard['guests'] ?? 1) ?></dd>
                    </div>
                    <?php if (!empty($wizard['comments'])): ?>
                        <div class="booking-summary-dl__row">
                            <dt>Notas</dt>
                            <dd><?= htmlspecialchars((string) $wizard['comments'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="booking-summary">
                    <div class="booking-summary__line">
                        <span>Pase</span>
                        <span><?= CurrencyFormatting::yen($passTotal) ?></span>
                    </div>

                    <?php if (!empty($cartDetails)): ?>
                        <div class="booking-summary__extras">
                            <div class="booking-summary__extras-title">Extras del carrito</div>
                            <?php foreach ($cartDetails as $item): ?>
                                <div class="booking-summary__line">
                                    <span><?= (int) ($item['qty'] ?? 1) ?>× <?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><?= CurrencyFormatting::yen((float) ($item['subtotal'] ?? 0)) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="booking-summary__line booking-summary__line--total">
                        <strong>Total estimado</strong>
                        <strong><?= CurrencyFormatting::yen($grandTotal) ?></strong>
                    </div>

                    <p class="booking-note">Pago en el local · Experiencia obligatoria.</p>
                </div>

                <form method="POST" action="/reservar">
                    <?= Csrf::field() ?>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn--primario">Confirmar reserva</button>
                        <a href="/reservar/paso-2" class="btn btn--secundario">Volver</a>
                    </div>
                </form>

            </section>
        </div>

    </div>
</section>

<style>
    .booking-summary-dl {
        margin: 20px 0;
        border: 1px solid var(--color-border, #dee2e6);
        border-radius: 8px;
        overflow: hidden;
    }

    .booking-summary-dl__row {
        display: flex;
        justify-content: space-between;
        padding: 10px 16px;
        border-bottom: 1px solid var(--color-border, #dee2e6);
    }

    .booking-summary-dl__row:last-child {
        border-bottom: none;
    }

    .booking-summary-dl__row dt {
        color: var(--color-text-muted, #6c757d);
        font-size: .9em;
    }

    .booking-summary-dl__row dd {
        font-weight: 500;
    }

    .booking-summary__line--total {
        padding-top: 12px;
        border-top: 2px solid var(--color-border, #dee2e6);
        margin-top: 8px;
        font-size: 1.1em;
    }

    .rsv2__layout--single {
        max-width: 600px;
    }
</style>
