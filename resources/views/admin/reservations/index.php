<?php

/**
 * Vista: Gestión de Reservas
 * Ruta: GET /admin/reservas
 *
 * @var array $reservations - Lista de reservas
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Csrf;
use App\Core\View;

$reservations ??= [];
$csrfToken = Csrf::token();

$alpineConfig = json_encode([
    'reservations' => $reservations,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='reservationManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'calendar-check',
        'title' => 'Reservas',
        'subtitle' => 'Gestiona las visitas programadas',
    ]) ?>

    <!-- Estadísticas Rápidas -->
    <?php include __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include __DIR__ . '/partials/_filters.php'; ?>

    <!-- Tabla -->
    <?php include __DIR__ . '/partials/_table.php'; ?>

    <!-- Modal -->
    <?php include __DIR__ . '/partials/_modal.php'; ?>

</div>
