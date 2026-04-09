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
$currentUserId = $_SESSION['user_id'] ?? null;
$csrfToken = Csrf::token();

// Preparar configuración para Alpine.js
$alpineConfig = json_encode([
    'users' => $users,
    'roles' => $roles,
    'currentUserId' => $currentUserId,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
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
    <?php include __DIR__ . '/partials/_stats.php'; ?>

    <!-- Filtros -->
    <?php include __DIR__ . '/partials/_filters.php'; ?>

    <!-- Tabla de Usuarios -->
    <?php include __DIR__ . '/partials/_user-table.php'; ?>

    <!-- Modal de Usuario -->
    <?php include __DIR__ . '/partials/_user-modal.php'; ?>

</div>
