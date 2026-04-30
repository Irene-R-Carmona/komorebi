<?php

/**
 * Vista: Gestión de Usuarios
 * Ruta: GET /admin/usuarios
 *
 * @var array $users - Lista de usuarios
 * @var array $roles - Roles disponibles
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Csrf;
use App\Core\View;

$users ??= [];
$roles ??= [];
$meta ??= ['page' => 1, 'has_next_page' => false];
$currentParams ??= [];
$currentUserId = $_SESSION['user_id'] ?? null;

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
    'currentUserId' => $currentUserId,
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='userManagement(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'people-fill',
        'title' => 'Usuarios',
        'subtitle' => 'Gestiona el equipo y permisos',
        'actionLabel' => 'Añadir Usuario',
        'actionClick' => 'openCreateModal()',
        'actionIcon' => 'plus-lg',
    ]) ?>

    <!-- Estadísticas -->
    <?php include_once __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include_once __DIR__ . '/partials/_filters.php'; ?>

    <!-- Tabla de Usuarios -->
    <?php include_once __DIR__ . '/partials/_user-table.php'; ?>

    <!-- Modal de Usuario -->
    <?php include_once __DIR__ . '/partials/_user-modal.php'; ?>

</div>
