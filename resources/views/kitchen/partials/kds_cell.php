<?php

/**
 * Partial: KDS Item Cell
 * Muestra una orden individual en el Kitchen Display System
 * Variables disponibles: $item (array con product_name, quantity, ui_time, ui_class, json_sop, tracker_code, guests)
 */
?>
<div class="kds-card <?= htmlspecialchars($item['ui_class'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-item-id="<?= (int) ($item['id'] ?? 0) ?>"
    data-created-ts="<?= (int) ($item['created_ts'] ?? strtotime($item['created_at'] ?? '')) ?>"
    data-prep-time="<?= (int) ($item['prep_time'] ?? 5) ?>"
    x-data="{ expanded: false }">

    <!-- HEADER -->
    <div class="kds-card__header">
        <div>
            <div class="kds-card__tracker">
                <?php if (!empty($item['tracker_code'])): ?>
                    <span class="material-symbols-outlined">confirmation_number</span>
                    <strong><?= htmlspecialchars($item['tracker_code'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php else: ?>
                    <span class="material-symbols-outlined">pending</span>
                    <strong>N/A</strong>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['reservation_id'])): ?>
                <div class="kds-card__reservation">RES #<?= (int) $item['reservation_id'] ?></div>
            <?php endif; ?>
        </div>
        <div class="kds-card__time"><?= htmlspecialchars($item['ui_time'] ?? '00:00', ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <!-- BODY -->
    <div class="kds-card__body">
        <div class="kds-card__product">
            <?= htmlspecialchars($item['product_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="kds-card__meta">
            <span class="kds-card__qty">
                <span class="material-symbols-outlined">restaurant</span>
                × <?= (int) ($item['quantity'] ?? 1) ?>
            </span>
            <?php if (!empty($item['guests'])): ?>
                <span class="kds-card__guests">
                    <span class="material-symbols-outlined">group</span>
                    <?= (int) $item['guests'] ?> PAX
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACCIONES -->
    <div class="kds-card__actions">
        <?php if (($item['status'] ?? '') === 'pending'): ?>
            <button class="kds-btn kds-btn--start"
                @click="$dispatch('start-prep', { id: <?= (int) ($item['id'] ?? 0) ?> })"
                type="button">
                <span class="material-symbols-outlined">play_arrow</span>
                INICIAR
            </button>
        <?php endif; ?>

        <button class="kds-btn kds-btn--primary"
            @click="$dispatch('mark-ready', { id: <?= (int) ($item['id'] ?? 0) ?> })"
            type="button">
            <span class="material-symbols-outlined">check_circle</span>
            READY
        </button>

        <?php if (!empty($item['json_sop'])): ?>
            <button class="kds-btn kds-btn--secondary"
                @click="$dispatch('show-sop', <?= $item['json_sop'] ?>)"
                type="button">
                <span class="material-symbols-outlined">menu_book</span>
                SOP
            </button>
        <?php endif; ?>
    </div>

    <!-- PREP TIME (si existe) -->
    <?php if (!empty($item['prep_time']) && (int) $item['prep_time'] > 0): ?>
        <div class="kds-card__footer">
            <small>
                <span class="material-symbols-outlined">schedule</span>
                Prep: ~<?= (int) $item['prep_time'] ?>min
            </small>
        </div>
    <?php endif; ?>
</div>
