<?php

/**
 * Vista: Gestión de Cafés
 * Ruta: GET /admin/cafes
 *
 * @var array $cafes         - Lista de cafés (PHP-rendered)
 * @var array $stats         - Estadísticas del panel
 * @var array $meta          - Metadatos de paginación
 * @var array $currentParams - Parámetros de filtro/sort actuales
 */

use App\Core\Csrf;
use App\Core\View;

$cafes ??= [];
$stats ??= [];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='cafeManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'shop',
        'title' => 'Cafeterías',
        'subtitle' => 'Espacios acogedores con gatitos',
        'actionLabel' => 'Nueva Cafetería',
        'actionClick' => 'openCreateModal()',
        'actionIcon' => 'plus-lg',
    ]) ?>

    <!-- Estadísticas -->
    <?php include_once __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include_once __DIR__ . '/partials/_filters.php'; ?>

    <!-- Grid de Cafés -->
    <?php include_once __DIR__ . '/partials/_cafe-grid.php'; ?>

    <!-- Modal de Café -->
    <?php include_once __DIR__ . '/partials/_cafe-modal.php'; ?>

</div>
