<?php

/**
 * Componente: Checkbox de Alérgeno
 *
 * Card de checkbox para selección de alérgenos en formularios.
 * Compatible con Alpine.js para estado reactivo.
 *
 * @var array $allergen - Datos del alérgeno
 * @var bool $checked - Si está seleccionado (solo para valor inicial)
 *
 * Estructura esperada de $allergen:
 * [
 *     'id' => int,
 *     'name' => string,
 *     'name_jp' => string|null,
 *     'icon' => string (clase FontAwesome),
 *     'icon_color' => string (color hex),
 *     'severity' => 'high'|'medium'|'low'
 * ]
 */

$allergen ??= [];
$checked ??= false;

// Datos del alérgeno con defaults
$id = (int) ($allergen['id'] ?? 0);
$name = $allergen['name'] ?? 'Desconocido';
$nameJp = $allergen['name_jp'] ?? null;
$icon = $allergen['icon'] ?? 'fa-question';
$iconColor = $allergen['icon_color'] ?? '#666666';
$severity = $allergen['severity'] ?? 'low';

// Mapeo de severidad
$severityLabels = [
    'high' => 'Alto',
    'medium' => 'Medio',
    'low' => 'Bajo',
];

$severityLabel = $severityLabels[$severity] ?? 'N/A';
?>

<div class="allergen-card">
    <input
        type="checkbox"
        class="allergen-card__input"
        name="allergens[]"
        value="<?= $id ?>"
        id="allergen_<?= $id ?>"
        @click="toggleAllergen(<?= $id ?>)"
        :checked="hasAllergen(<?= $id ?>)">
    <label class="allergen-card__label" for="allergen_<?= $id ?>">
        <!-- Icono -->
        <span class="allergen-card__icon">
            <i class="<?= e($icon !== '' ? $icon : 'bi bi-question-circle') ?>" style="color: <?= e($iconColor) ?>"></i>
        </span>

        <!-- Contenido -->
        <span class="allergen-card__content">
            <span class="allergen-card__name"><?= e($name) ?></span>
            <?php if ($nameJp): ?>
                <span class="allergen-card__name-jp"><?= e($nameJp) ?></span>
            <?php endif; ?>
        </span>

        <!-- Severidad -->
        <span class="allergen-card__severity">
            <span class="severity-badge severity-badge--<?= e($severity) ?>">
                <?= e($severityLabel) ?>
            </span>
        </span>
    </label>
</div>
