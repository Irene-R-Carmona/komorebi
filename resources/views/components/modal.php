<?php

declare(strict_types=1);

/**
 * Modal Component
 *
 * Renders an accessible modal dialog with Alpine.js integration.
 *
 * Props:
 * - id: string (required) - Unique ID for the modal
 * - title: string (optional) - Modal title (displayed in header)
 * - size: string (optional) - Size variant: 'sm'|'md'|'lg'|'xl'|'full' (default: 'md')
 * - closeable: bool (optional) - Whether the modal can be closed (default: true)
 * - header: string (optional) - Custom header HTML content
 * - body: string (required) - Modal body content (HTML allowed)
 * - footer: string (optional) - Modal footer content (HTML allowed)
 * - class: string (optional) - Additional CSS classes
 * - attributes: array (optional) - Additional HTML attributes (e.g., x-data, x-show)
 *
 * Example usage:
 *
 * <?= renderModal([
 *     'id' => 'confirm-modal',
 *     'title' => 'Confirm Action',
 *     'size' => 'md',
 *     'closeable' => true,
 *     'body' => '<p>Are you sure you want to continue?</p>',
 *     'footer' => '<button type="button" @click="open = false">Cancel</button>
 *                  <button type="submit">Confirm</button>',
 *     'attributes' => [
 *         'x-data' => '{ open: false }',
 *         'x-show' => 'open',
 *         'x-transition'
 *     ]
 * ]) ?>
 *
 * Accessibility features:
 * - role="dialog" and aria-modal="true"
 * - aria-labelledby pointing to title
 * - ESC key support via Alpine @keydown.escape
 * - Focus trap (managed by Alpine x-trap)
 * - Screen reader announcements
 *
 * @param array{
 *   id: string,
 *   title?: string,
 *   size?: string,
 *   closeable?: bool,
 *   header?: string,
 *   body: string,
 *   footer?: string,
 *   class?: string,
 *   attributes?: array<string, string>
 * } $props
 *
 * @return string
 */
function renderModal(array $props): string
{
    // Extract props with defaults
    $id = $props['id'] ?? 'modal-' . uniqid();
    $title = $props['title'] ?? null;
    $size = $props['size'] ?? 'md';
    $closeable = $props['closeable'] ?? true;
    $header = $props['header'] ?? null;
    $body = $props['body'] ?? '<p>Modal content</p>';
    $footer = $props['footer'] ?? null;
    $class = $props['class'] ?? '';
    $attributes = $props['attributes'] ?? [];

    // Validate size
    $validSizes = ['sm', 'md', 'lg', 'xl', 'full'];
    if (!in_array($size, $validSizes, true)) {
        $size = 'md';
    }

    // Build CSS classes
    $classes = ['modal'];
    $classes[] = "modal--{$size}";
    if ($class) {
        $classes[] = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    }

    // Build attributes string
    $attrs = [];
    $attrs[] = 'id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
    $attrs[] = 'role="dialog"';
    $attrs[] = 'aria-modal="true"';

    if ($title) {
        $attrs[] = 'aria-labelledby="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '-title"';
    }

    foreach ($attributes as $key => $value) {
        $attrKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $attrValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $attrs[] = "{$attrKey}=\"{$attrValue}\"";
    }

    // Start output buffer
    ob_start();
    ?>
    <!-- Modal Backdrop -->
    <div class="modal-backdrop"
        @click="open = false"
        x-show="open"
        x-transition:enter="transition-opacity"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
    </div>

    <!-- Modal Container -->
    <div class="<?= implode(' ', $classes) ?>"
        <?= implode(' ', $attrs) ?>
        @keydown.escape.window="<?= $closeable ? 'open = false' : '' ?>"
        x-show="open"
        x-transition:enter="transition-all"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition-all"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-trap="open">

        <div class="modal__dialog">

            <?php if ($header || $title || $closeable): ?>
                <!-- Modal Header -->
                <div class="modal__header">
                    <?php if ($header): ?>
                        <?= $header ?>
                    <?php elseif ($title): ?>
                        <h2 class="modal__title" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>-title">
                            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                    <?php endif; ?>

                    <?php if ($closeable): ?>
                        <button type="button"
                            class="modal__close"
                            @click="open = false"
                            aria-label="Close modal">
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Modal Body -->
            <div class="modal__body">
                <?= $body ?>
            </div>

            <?php if ($footer): ?>
                <!-- Modal Footer -->
                <div class="modal__footer">
                    <?= $footer ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
<?php
        return ob_get_clean();
}
