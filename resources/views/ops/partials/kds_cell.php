<?php

use App\Core\Csrf;

?>
<article class="kds-card <?= $item['ui_class'] ?>">
    <div class="kds-header-row">
        <span>#<?= $item['reservation_id'] ?></span>
        <span class="kds-timer"><?= $item['ui_time'] ?></span>
    </div>

    <div class="kds-body-row">
        <div class="kds-qty"><?= $item['quantity'] ?></div>
        <div class="kds-name"><?= e($item['product_name']) ?></div>
    </div>

    <div class="kds-actions-row">
        <?php if ($item['recipe_steps']): ?>
            <button type="button" class="btn-sop js-open-recipe"
                    data-recipe="<?= $item['json_sop'] ?>">
                <span class="material-symbols-outlined" style="font-size:1.2rem;">menu_book</span>
            </button>
        <?php endif; ?>

        <form action="/ops/kitchen/ready" method="POST" style="flex:1;">
            <?= Csrf::field() ?>
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
            <button type="submit" class="btn-done">LISTO</button>
        </form>
    </div>
</article>