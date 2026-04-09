<?php
/**
 * Componente: Stat Card
 *
 * Tarjeta de estadística para dashboards y vistas de administración.
 * Soporta valores estáticos y dinámicos con Alpine.js.
 *
 * @var string $icon - Nombre del icono Bootstrap (sin prefijo 'bi-')
 * @var string $variant - primary|success|warning|error|info
 * @var string $label - Etiqueta descriptiva
 * @var mixed $value - Valor a mostrar (string para Alpine expression si $alpine=true)
 * @var bool $alpine - Si true, $value es una expresión Alpine.js
 * @var string|null $trend - Texto de tendencia (ej: '+12%')
 * @var string|null $trendDirection - up|down|neutral
 * @var string|null $subtitle - Texto adicional debajo del valor
 *
 * Ejemplo de uso:
 *
 * // Valor estático
 * <?= View::componentToString('admin/stat-card', [
 *     'icon' => 'people',
 *     'variant' => 'primary',
 *     'label' => 'Usuarios',
 *     'value' => 150
 * ]) ?>
 *
 * // Valor dinámico con Alpine
 * <?= View::componentToString('admin/stat-card', [
 *     'icon' => 'people',
 *     'variant' => 'primary',
 *     'label' => 'Usuarios',
 *     'value' => 'users.length',
 *     'alpine' => true
 * ]) ?>
 */

// Valores por defecto
$icon ??= 'circle';
$variant ??= 'primary';
$label ??= 'Estadística';
$value ??= 0;
$alpine ??= false;
$trend ??= null;
$trendDirection ??= null;
$subtitle ??= null;

// Mapeo de variantes válidas
$validVariants = ['primary', 'success', 'warning', 'error', 'info'];
if (!in_array($variant, $validVariants, true)) {
    $variant = 'primary';
}

// Clase del icono
$iconClass = "stat-card__icon stat-card__icon--$variant";

// Clase de tendencia
$trendClass = 'stat-card__trend';
if ($trendDirection === 'up') {
    $trendClass .= ' stat-card__trend--up';
} elseif ($trendDirection === 'down') {
    $trendClass .= ' stat-card__trend--down';
} else {
    $trendClass .= ' stat-card__trend--neutral';
}
?>

<div class="stat-card">
    <div class="stat-card__inner">
        <!-- Icono -->
        <div class="<?= $iconClass ?>">
            <i class="bi bi-<?= e($icon) ?>"></i>
        </div>

        <!-- Contenido -->
        <div class="stat-card__content">
            <!-- Label -->
            <p class="stat-card__label"><?= e($label) ?></p>

            <!-- Valor -->
            <?php if ($alpine): ?>
                <h3 class="stat-card__value" x-text="<?= e($value) ?>">0</h3>
            <?php else: ?>
                <h3 class="stat-card__value"><?= e((string) $value) ?></h3>
            <?php endif; ?>

            <!-- Subtítulo (opcional) -->
            <?php if ($subtitle): ?>
                <small class="text-muted"><?= e($subtitle) ?></small>
            <?php endif; ?>

            <!-- Tendencia (opcional) -->
            <?php if ($trend): ?>
                <span class="<?= $trendClass ?>">
                    <?php if ($trendDirection === 'up'): ?>
                        <i class="bi bi-arrow-up"></i>
                    <?php elseif ($trendDirection === 'down'): ?>
                        <i class="bi bi-arrow-down"></i>
                    <?php endif; ?>
                    <?= e($trend) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>