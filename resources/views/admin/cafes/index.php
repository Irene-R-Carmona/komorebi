<?php

/**
 * Vista: Gestión de Cafés
 * Ruta: GET /admin/cafes
 *
 * @var array $cafes - Lista de cafés
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Csrf;
use App\Core\View;

$cafes ??= [];
$csrfToken = Csrf::token();

// Preparar configuración para Alpine.js
$alpineConfig = json_encode([
    'cafes' => $cafes,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
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
    <?php include __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include __DIR__ . '/partials/_filters.php'; ?>

    <!-- Grid de Cafés -->
    <?php include __DIR__ . '/partials/_cafe-grid.php'; ?>

    <!-- Modal de Café -->
    <?php include __DIR__ . '/partials/_cafe-modal.php'; ?>

</div>
