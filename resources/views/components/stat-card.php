<?php

$icon ??= 'circle';
$color ??= 'primary';
$label ??= '';
$value ??= '0';
$small ??= false;
?>

<div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 bg-<?= $color ?> bg-opacity-10 rounded p-3">
                    <i class="bi bi-<?= $icon ?> fs-4 text-<?= $color ?>"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="text-muted mb-0 small"><?= htmlspecialchars($label) ?></p>
                    <<?= $small ? 'h5' : 'h3' ?> class="mb-0" x-text="<?= $value ?>">
                </<?= $small ? 'h5' : 'h3' ?>>
            </div>
        </div>
    </div>
</div>