<?php

/**
 * Vista: Moderación de Reseñas
 * Ruta: GET /admin/reviews/pending
 *
 * @var array $pending - Reseñas pendientes de moderación
 */

use App\Core\Csrf;
use App\Core\View;

$pending ??= [];

$alpineConfig = json_encode([
    'csrfToken' => Csrf::token(),
], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>

<div class="container-fluid" x-data='reviewsMod(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon'        => 'star',
        'title'       => 'Reseñas',
        'subtitle'    => 'Revisa las opiniones de nuestros visitantes',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'house'],
            ['label' => 'Reseñas Pendientes'],
        ],
    ]) ?>

    <!-- Estadísticas -->
    <?php include_once __DIR__ . '/partials/_stats.php'; ?>

    <!-- Lista de Reseñas -->
    <?php if ($pending === []): ?>
    <div class="reviews-empty">
        <span class="reviews-empty__icon">✨</span>
        <h3 class="reviews-empty__title">¡Todo revisado!</h3>
        <p class="reviews-empty__text">No hay reseñas por revisar ahora mismo.</p>
    </div>
    <?php else: ?>
    <div class="reviews-queue">
        <?php foreach ($pending as $review): ?>
        <?= View::componentToString('components/admin/review-card', ['review' => $review]) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Modal Rechazar -->
    <?php include_once __DIR__ . '/partials/_reject-modal.php'; ?>

</div>
