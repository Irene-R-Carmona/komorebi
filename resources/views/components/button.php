<?php

/**
 * Button Component
 *
 * Componente de botón reutilizable con soporte para:
 * - Múltiples variantes (primary, secondary, danger, ghost)
 * - Múltiples tamaños (sm, md, lg)
 * - Estados (disabled, loading)
 * - Iconos (Material Symbols)
 * - WCAG 2.1 compliant (44px min touch target)
 *
 * @param string $type Button type (button|submit|reset) - default: button
 * @param string $variant Visual style (primary|secondary|danger|ghost) - default: primary
 * @param string $size Size variant (sm|md|lg) - default: md
 * @param string $label Button text - REQUIRED
 * @param string $icon Material Symbol icon name (optional)
 * @param bool $disabled Disabled state - default: false
 * @param bool $loading Loading state - default: false
 * @param string $class Additional CSS classes - default: ''
 * @param array $attributes Additional HTML attributes - default: []
 *
 * Usage Examples:
 *
 * Basic:
 * <?php include __DIR__ . '/button.php'; ?>
 * <?= renderButton(['label' => 'Click me']) ?>
 *
 * With icon:
 * <?= renderButton([
 *     'label' => 'Save',
 *     'icon' => 'save',
 *     'variant' => 'primary'
 * ]) ?>
 *
 * Loading state:
 * <?= renderButton([
 *     'label' => 'Saving...',
 *     'loading' => true,
 *     'variant' => 'primary'
 * ]) ?>
 *
 * Submit button:
 * <?= renderButton([
 *     'type' => 'submit',
 *     'label' => 'Submit Form',
 *     'variant' => 'primary',
 *     'class' => 'w-full'
 * ]) ?>
 */

function renderButton(array $props): string
{
    // Extract props with defaults
    $type = $props['type'] ?? 'button';
    $variant = $props['variant'] ?? 'primary';
    $size = $props['size'] ?? 'md';
    $label = $props['label'] ?? 'Button';
    $icon = $props['icon'] ?? null;
    $disabled = $props['disabled'] ?? false;
    $loading = $props['loading'] ?? false;
    $class = $props['class'] ?? '';
    $attributes = $props['attributes'] ?? [];

    // Build classes
    $classes = [
        'btn',
        "btn--{$variant}",
        "btn--{$size}",
    ];

    if ($disabled || $loading) {
        $classes[] = 'btn--disabled';
    }

    if ($loading) {
        $classes[] = 'btn--loading';
    }

    if ($class) {
        $classes[] = $class;
    }

    $classString = implode(' ', $classes);

    // Build attributes
    $attrString = '';
    if ($disabled || $loading) {
        $attrString .= ' disabled';
    }

    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
    }

    // Render button
    ob_start();
    ?>
    <button type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
        class="<?= htmlspecialchars($classString, ENT_QUOTES, 'UTF-8') ?>"
        <?= $attrString ?>>
        <?php if ($loading): ?>
            <span class="btn__spinner" aria-hidden="true"></span>
            <span class="sr-only">Cargando...</span>
        <?php endif; ?>

        <?php if ($icon && !$loading): ?>
            <span class="btn__icon material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>

        <span class="btn__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    </button>
<?php
        return ob_get_clean();
}

// Auto-render if props provided directly in scope
if (isset($label) && !function_exists('___button_already_rendered')) {
    function ___button_already_rendered()
    {
        return true;
    }
    echo renderButton(get_defined_vars());
}
?>
