<?php

/**
 * Vista: Moderación de Reseñas
 * Ruta: GET /admin/reviews/pending
 *
 * @var array $pending - Reseñas pendientes de moderación
 * @var string $csrf_token - Token CSRF
 */

use App\Core\Csrf;
use App\Core\View;

$reviews = $pending ?? [];
$csrfToken = Csrf::token();

$alpineConfig = json_encode([
    'reviews' => $reviews,
    'csrfToken' => $csrfToken,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="container-fluid" x-data='reviewsModeration(<?= $alpineConfig ?>)' x-cloak>

    <!-- Header -->
    <?= View::componentToString('components/admin/page-header', [
        'icon' => 'star',
        'title' => 'Reseñas',
        'subtitle' => 'Revisa las opiniones de nuestros visitantes',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'house'],
            ['label' => 'Reseñas Pendientes'],
        ],
    ]) ?>

    <!-- Estadísticas -->
    <?php include __DIR__ . '/partials/_stats.php'; ?>

    <!-- Lista de Reseñas -->
    <?php if (empty($reviews)): ?>
        <div class="reviews-empty">
            <span class="reviews-empty__icon">✨</span>
            <h3 class="reviews-empty__title">¡Todo revisado!</h3>
            <p class="reviews-empty__text">No hay reseñas por revisar ahora mismo.</p>
        </div>
    <?php else: ?>
        <div class="reviews-queue">
            <?php foreach ($reviews as $review): ?>
                <?= View::componentToString('components/admin/review-card', [
                    'review' => $review,
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal Rechazar -->
    <?php include __DIR__ . '/partials/_reject-modal.php'; ?>

</div>
