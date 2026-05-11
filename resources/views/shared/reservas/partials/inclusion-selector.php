<?php

declare(strict_types=1);
/**
 * Partial: Selector de ítems incluidos en el pase
 *
 * Renderizado dentro del paso 4 (Pase) cuando el pase seleccionado
 * tiene inclusiones definidas en pass_inclusions.
 *
 * Variables Alpine disponibles (padre: reservaForm):
 *   - passActivo       — objeto del pase seleccionado (incluye .inclusions[])
 *   - personas         — número de personas
 *   - formatEuro(c)    — formatea céntimos → "x,xx €"
 *
 * Cada inclusión tiene:
 *   - category_id, category_name, quantity_per_pax, max_unit_price
 *
 * Este partial NO gestiona estado de selección de ítems individuales del menú
 * (eso requeriría una API adicional). Muestra las inclusiones del pase como
 * información al usuario.
 */
?>
<div class="rsv3-pass__inclusions" x-show="passActivo && passActivo.inclusions && passActivo.inclusions.length > 0">

    <p style="font-size:.8rem;font-weight:600;color:var(--color-texto-suave);
              letter-spacing:.04em;text-transform:uppercase;margin:1rem 0 .5rem">
        Incluido en tu pase
    </p>

    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.375rem">
        <template x-for="inc in (passActivo.inclusions || [])" :key="inc.category_id">
            <li class="rsv3-pass__inclusion-item">
                <span class="badge-inclusion">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"
                        style="color:var(--color-success);font-size:.85rem"></i>
                    <span class="rsv3-pass__inclusion-cat" x-text="inc.category_name"></span>
                    <span class="rsv3-pass__inclusion-limit"
                        x-text="'×' + (inc.quantity_per_pax * personas)"
                        :title="'(' + inc.quantity_per_pax + ' por persona × ' + personas + ' pers.)'">
                    </span>
                    <template x-if="inc.max_unit_price">
                        <span style="font-size:.72rem;color:var(--color-texto-suave);margin-left:.25rem"
                            x-text="'(hasta ' + formatEuro(inc.max_unit_price) + '/ud.)'">
                        </span>
                    </template>
                </span>
            </li>
        </template>
    </ul>

</div>
