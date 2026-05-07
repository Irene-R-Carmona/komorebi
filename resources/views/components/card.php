<?php

/**
 * Card Component
 *
 * Contenedor flexible para contenido con múltiples variantes visuales.
 *
 * @param string $variant Estilo visual (default|glass|outlined|elevated) - default: default
 * @param string $padding Espaciado interno (none|sm|md|lg) - default: md
 * @param bool $interactive Hover effect interactivo - default: false
 * @param string $class Clases CSS adicionales - default: ''
 * @param array $attributes Atributos HTML adicionales - default: []
 * @param string $header Contenido del header (opcional)
 * @param string $body Contenido del body - REQUIRED
 * @param string $footer Contenido del footer (opcional)
 *
 * Usage Examples:
 *
 * Basic card:
 * <?php include __DIR__ . '/card.php'; ?>
 * <?= renderCard([
 *     'body' => '<p>Contenido de la tarjeta</p>'
 * ]) ?>
 *
 * Card with header and footer:
 * <?= renderCard([
 *     'header' => '<h3>Título</h3>',
 *     'body' => '<p>Contenido principal</p>',
 *     'footer' => '<button>Acción</button>'
 * ]) ?>
 *
 * Glass morphism card:
 * <?= renderCard([
 *     'variant' => 'glass',
 *     'body' => '<p>Contenido con efecto glass</p>'
 * ]) ?>
 *
 * Interactive card (clickable):
 * <?= renderCard([
 *     'variant' => 'elevated',
 *     'interactive' => true,
 *     'body' => '<p>Click me!</p>',
 *     'attributes' => ['onclick' => "location.href='/details'"]
 * ]) ?>
 */

function renderCard(array $props): string
{
    // Extract props with defaults
    $variant = $props['variant'] ?? 'default';
    $padding = $props['padding'] ?? 'md';
    $interactive = $props['interactive'] ?? false;
    $class = $props['class'] ?? '';
    $attributes = $props['attributes'] ?? [];

    $header = $props['header'] ?? null;
    $body = $props['body'] ?? '';
    $footer = $props['footer'] ?? null;

    // Build classes
    $classes = [
        'card',
        "card--{$variant}",
        "card--padding-{$padding}",
    ];

    if ($interactive) {
        $classes[] = 'card--interactive';
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

    // Render card
    ob_start();
    ?>
    <div class="<?= htmlspecialchars($classString, ENT_QUOTES, 'UTF-8') ?>" <?= $attrString ?>>
        <?php if ($header): ?>
            <div class="card__header">
                <?= $header ?>
            </div>
        <?php endif; ?>

        <div class="card__body">
            <?= $body ?>
        </div>

        <?php if ($footer): ?>
            <div class="card__footer">
                <?= $footer ?>
            </div>
        <?php endif; ?>
    </div>
<?php
        return ob_get_clean();
}

// Auto-render if body provided directly in scope
if (isset($body) && !function_exists('___card_already_rendered')) {
    function ___card_already_rendered()
    {
        return true;
    }
    echo renderCard(get_defined_vars());
}
?>
