<?php

/**
 * Badge Component
 *
 * Indicador visual compacto para estados, categorías o contadores.
 *
 * @param string $label Texto del badge - REQUIRED
 * @param string $variant Estilo visual (success|warning|danger|info|neutral) - default: neutral
 * @param string $size Tamaño (sm|md|lg) - default: md
 * @param string $icon Icono Material Symbol (opcional)
 * @param bool $dot Mostrar dot indicator en lugar de texto - default: false
 * @param string $class Clases CSS adicionales - default: ''
 * @param array $attributes Atributos HTML adicionales - default: []
 *
 * Usage Examples:
 *
 * Basic badge:
 * <?php include __DIR__ . '/badge.php'; ?>
 * <?= renderBadge(['label' => 'New']) ?>
 *
 * Status badge:
 * <?= renderBadge([
 *     'label' => 'Active',
 *     'variant' => 'success'
 * ]) ?>
 *
 * Badge with icon:
 * <?= renderBadge([
 *     'label' => 'Alert',
 *     'variant' => 'warning',
 *     'icon' => 'warning'
 * ]) ?>
 *
 * Dot indicator:
 * <?= renderBadge([
 *     'label' => 'Online',
 *     'variant' => 'success',
 *     'dot' => true
 * ]) ?>
 *
 * Counter badge:
 * <?= renderBadge([
 *     'label' => '5',
 *     'variant' => 'danger',
 *     'size' => 'sm'
 * ]) ?>
 */

function renderBadge(array $props): string
{
    // Extract props with defaults
    $label = $props['label'] ?? 'Badge';
    $variant = $props['variant'] ?? 'neutral';
    $size = $props['size'] ?? 'md';
    $icon = $props['icon'] ?? null;
    $dot = $props['dot'] ?? false;
    $class = $props['class'] ?? '';
    $attributes = $props['attributes'] ?? [];

    // Build classes
    $classes = [
        'badge',
        "badge--{$variant}",
        "badge--{$size}",
    ];

    if ($dot) {
        $classes[] = 'badge--dot';
    }

    if ($class) {
        $classes[] = $class;
    }

    $classString = implode(' ', $classes);

    // Build attributes
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
    }

    // Render badge
    ob_start();
    ?>
    <span class="<?= htmlspecialchars($classString, ENT_QUOTES, 'UTF-8') ?>" <?= $attrString ?>>
        <?php if ($dot): ?>
            <span class="badge__dot" aria-hidden="true"></span>
        <?php endif; ?>

        <?php if ($icon && !$dot): ?>
            <span class="badge__icon material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>

        <span class="badge__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    </span>
<?php
        return ob_get_clean();
}

// Auto-render if label provided directly in scope
if (isset($label) && !function_exists('___badge_already_rendered')) {
    function ___badge_already_rendered()
    {
        return true;
    }
    echo renderBadge(get_defined_vars());
}
?>
