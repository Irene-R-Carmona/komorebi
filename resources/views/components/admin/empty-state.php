<?php
/**
 * Componente: Empty State
 *
 * Estado vacío para cuando no hay datos que mostrar.
 * Usado en tablas, listas y grids.
 *
 * @var string $icon - Nombre del icono Bootstrap
 * @var string $title - Título principal
 * @var string|null $message - Mensaje descriptivo
 * @var string|null $actionLabel - Texto del botón de acción
 * @var string|null $actionUrl - URL del botón (para enlaces)
 * @var string|null $actionClick - Expresión Alpine @click (para botones)
 * @var string $actionVariant - Variante del botón
 * @var bool $compact - Versión compacta para tablas
 * @var string|null $alpineShow - Condición Alpine x-show
 *
 * Ejemplo:
 * <?= View::componentToString('admin/empty-state', [
 *     'icon' => 'inbox',
 *     'title' => 'No hay usuarios',
 *     'message' => 'Comienza creando tu primer usuario',
 *     'actionLabel' => 'Crear Usuario',
 *     'actionUrl' => '/admin/usuarios/crear'
 * ]) ?>
 */

// Valores por defecto
$icon ??= 'inbox';
$title ??= 'No hay datos';
$message ??= null;
$actionLabel ??= null;
$actionUrl ??= null;
$actionClick ??= null;
$actionVariant ??= 'primary';
$compact ??= false;
$alpineShow ??= null;

// Clase base
$baseClass = 'empty-state';
if ($compact) {
    $baseClass .= ' empty-state--compact';
}
?>

<div class="<?= $baseClass ?>"
    <?= $alpineShow ? 'x-show="' . e($alpineShow) . '"' : '' ?>>

    <!-- Icono -->
    <i class="bi bi-<?= e($icon) ?> empty-state__icon"></i>

    <!-- Título -->
    <h3 class="empty-state__title"><?= e($title) ?></h3>

    <!-- Mensaje -->
    <?php if ($message): ?>
        <p class="empty-state__message"><?= e($message) ?></p>
    <?php endif; ?>

    <!-- Acción -->
    <?php if ($actionLabel): ?>
        <div class="empty-state__action">
            <?php if ($actionUrl): ?>
                <a href="<?= e($actionUrl) ?>"
                   class="btn btn-<?= e($actionVariant) ?>">
                    <?= e($actionLabel) ?>
                </a>
            <?php elseif ($actionClick): ?>
                <button type="button"
                        class="btn btn-<?= e($actionVariant) ?>"
                        @click="<?= e($actionClick) ?>">
                    <?= e($actionLabel) ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>