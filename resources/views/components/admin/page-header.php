<?php
/**
 * Componente: Page Header
 *
 * Cabecera de página para vistas de administración.
 * Incluye título, subtítulo y botones de acción opcionales.
 *
 * @var string $icon - Nombre del icono Bootstrap
 * @var string $title - Título de la página
 * @var string|null $subtitle - Subtítulo/descripción
 * @var string|null $actionLabel - Texto del botón de acción
 * @var string|null $actionUrl - URL del botón (para enlaces)
 * @var string|null $actionClick - Expresión Alpine @click (para botones)
 * @var string $actionVariant - Variante del botón: primary|success|warning|etc
 * @var string|null $actionIcon - Icono del botón
 * @var array $breadcrumbs - Array de migas de pan [['label' => '', 'url' => ''], ...]
 *
 * Ejemplo:
 * <?= View::componentToString('admin/page-header', [
 *     'icon' => 'people-fill',
 *     'title' => 'Gestión de Usuarios',
 *     'subtitle' => 'Administra usuarios y permisos',
 *     'actionLabel' => 'Nuevo Usuario',
 *     'actionClick' => 'openCreateModal()',
 *     'actionIcon' => 'plus-lg'
 * ]) ?>
 */

// Valores por defecto
$icon ??= null;
$title ??= 'Página';
$subtitle ??= null;
$actionLabel ??= null;
$actionUrl ??= null;
$actionClick ??= null;
$actionVariant ??= 'primary';
$actionIcon ??= 'plus-lg';
$breadcrumbs ??= [];
?>

<?php if (!empty($breadcrumbs)): ?>
    <nav aria-label="Navegación de migas" class="mb-3">
        <ol class="breadcrumb mb-0">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php $isLast = $index === count($breadcrumbs) - 1; ?>
                <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>"
                    <?= $isLast ? 'aria-current="page"' : '' ?>>
                    <?php if (!$isLast && !empty($crumb['url'])): ?>
                        <a href="<?= e($crumb['url']) ?>">
                            <?php if (!empty($crumb['icon'])): ?>
                                <i class="bi bi-<?= e($crumb['icon']) ?> me-1"></i>
                            <?php endif; ?>
                            <?= e($crumb['label']) ?>
                        </a>
                    <?php else: ?>
                        <?= e($crumb['label']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>

<header class="page-header">
    <div class="page-header__content">
        <h1 class="page-header__title">
            <?php if ($icon): ?>
                <i class="bi bi-<?= e($icon) ?>"></i>
            <?php endif; ?>
            <?= e($title) ?>
        </h1>

        <?php if ($subtitle): ?>
            <p class="page-header__subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($actionLabel): ?>
        <div class="page-header__actions">
            <?php if ($actionUrl): ?>
                <!-- Enlace -->
                <a href="<?= e($actionUrl) ?>"
                   class="btn btn-<?= e($actionVariant) ?>">
                    <?php if ($actionIcon): ?>
                        <i class="bi bi-<?= e($actionIcon) ?> me-2"></i>
                    <?php endif; ?>
                    <?= e($actionLabel) ?>
                </a>
            <?php elseif ($actionClick): ?>
                <!-- Botón con Alpine -->
                <button type="button"
                        class="btn btn-<?= e($actionVariant) ?>"
                        @click="<?= e($actionClick) ?>">
                    <?php if ($actionIcon): ?>
                        <i class="bi bi-<?= e($actionIcon) ?> me-2"></i>
                    <?php endif; ?>
                    <?= e($actionLabel) ?>
                </button>
            <?php else: ?>
                <!-- Botón simple -->
                <button type="button" class="btn btn-<?= e($actionVariant) ?>">
                    <?php if ($actionIcon): ?>
                        <i class="bi bi-<?= e($actionIcon) ?> me-2"></i>
                    <?php endif; ?>
                    <?= e($actionLabel) ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>