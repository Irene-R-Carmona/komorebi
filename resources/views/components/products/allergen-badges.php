<?php
/**
 * Componente: Badges de Alérgenos
 *
 * Muestra iconos de alérgenos en formato compacto para tablas/listas.
 * Incluye tooltips con el nombre del alérgeno.
 *
 * @var array $allergens - Lista de alérgenos del producto
 *
 * Estructura esperada de cada alérgeno:
 * [
 *     'id' => int,
 *     'name' => string,
 *     'icon' => string (clase FontAwesome),
 *     'icon_color' => string (color hex)
 * ]
 */

$allergens ??= [];
?>

<?php if (!empty($allergens)): ?>
    <div class="allergen-badges">
        <?php foreach ($allergens as $allergen): ?>
            <span
                    class="allergen-badge"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="<?= e($allergen['name'] ?? 'Alérgeno') ?>"
            >
                <i class="fa-solid <?= e($allergen['icon'] ?? 'fa-question') ?>"
                   style="color: <?= e($allergen['icon_color'] ?? '#666') ?>"></i>
            </span>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <span class="text-muted small">Sin alérgenos</span>
<?php endif; ?>