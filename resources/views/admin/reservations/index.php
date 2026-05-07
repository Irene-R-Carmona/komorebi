<?php

/**
 * Vista: Gestión de Reservas
 * Ruta: GET /admin/reservas
 *
 * @var array $reservations  - Reservas paginadas (raw rows con JOINs)
 * @var array $stats         - Conteos por estado
 * @var array $cafeNames     - Nombres de cafés para filtro dropdown
 * @var array $meta          - Metadatos de paginación
 * @var array $currentParams - Parámetros de filtro actuales
 */

use App\Core\Csrf;
use App\Core\View;

$reservations ??= [];
$stats ??= ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
$cafeNames ??= [];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='reservationManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'calendar-check',
        'title' => 'Reservas',
        'subtitle' => 'Gestiona las visitas programadas',
    ]) ?>

    <!-- Estadísticas -->
    <?php include_once __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include_once __DIR__ . '/partials/_filters.php'; ?>

    <!-- Tabla -->
    <?php include_once __DIR__ . '/partials/_table.php'; ?>

    <!-- Modal -->
    <?php include_once __DIR__ . '/partials/_modal.php'; ?>

</div>
