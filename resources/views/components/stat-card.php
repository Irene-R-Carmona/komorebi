<?php

$icon ??= 'circle';
$color ??= 'primary';
$label ??= '';
$value ??= '0';
$small ??= false;

// Mapeo a CSS tokens de admin-common.css — sin clases Bootstrap dinámicas
$bgStyle = match ($color) {
    'success' => 'background: var(--admin-success-light, rgba(94,111,100,0.1));',
    'warning' => 'background: var(--admin-warning-light, rgba(230,149,0,0.1));',
    'danger' => 'background: var(--admin-error-light, rgba(155,35,53,0.1));',
    'info' => 'background: var(--admin-info-light, rgba(59,130,246,0.1));',
    default => 'background: var(--admin-primary-light, rgba(92,61,46,0.1));',
};
$textStyle = match ($color) {
    'success' => 'color: var(--admin-success, #5E6F64);',
    'warning' => 'color: var(--admin-warning, #E69500);',
    'danger' => 'color: var(--admin-error, #9B2335);',
    'info' => 'color: var(--admin-info, #3B82F6);',
    default => 'color: var(--admin-primary, #5C3D2E);',
};
?>

<div class="col-md-3">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 rounded p-3" style="<?= $bgStyle ?>">
                    <i class="bi bi-<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> fs-4" style="<?= $textStyle ?>"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="text-muted mb-0 small"><?= htmlspecialchars($label) ?></p>
                    <<?= $small ? 'h5' : 'h3' ?> class="mb-0" x-text="<?= $value ?>">
                    </<?= $small ? 'h5' : 'h3' ?>>
                </div>
            </div>
        </div>
    </div>
