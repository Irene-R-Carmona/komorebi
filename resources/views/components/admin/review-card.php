<?php

/**
 * Componente: Card de Reseña para Moderación
 *
 * @var array $review - Datos de la reseña
 */

$review ??= [];

$id = $review['id'] ?? 0;
$author = $review['user_name'] ?? 'Anónimo';
$cafe = $review['cafe_name'] ?? 'Café desconocido';
$date = $review['created_at'] ?? date('Y-m-d');
$rating = (int) ($review['rating'] ?? 0);
$title = $review['title'] ?? '';
$body = $review['body'] ?? '';
$initial = strtoupper(mb_substr($author, 0, 1));
?>

<article class="review-moderation-card" data-review-id="<?= (int) $id ?>">
    <!-- Header -->
    <div class="review-mod__header">
        <div class="review-mod__avatar">
            <?= e($initial) ?>
        </div>

        <div class="review-mod__info">
            <h4 class="review-mod__author"><?= e($author) ?></h4>
            <p class="review-mod__cafe">
                Para: <strong><?= e($cafe) ?></strong>
            </p>
            <time class="review-mod__date">
                <?= date('d \d\e F, Y \a \l\a\s H:i', strtotime($date)) ?>
            </time>
        </div>

        <!-- Rating -->
        <div class="review-mod__rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star-fill review-mod__star<?= $i > $rating ? '--empty' : '' ?>"></i>
            <?php endfor; ?>
            <span class="review-mod__rating-text"><?= $rating ?>/5</span>
        </div>
    </div>

    <!-- Content -->
    <div class="review-mod__content">
        <?php if ($title): ?>
            <h3 class="review-mod__title"><?= e($title) ?></h3>
        <?php endif; ?>
        <p class="review-mod__body"><?= e($body) ?></p>
    </div>

    <!-- Actions -->
    <div class="review-mod__actions">
        <!-- Aprobar -->
        <form method="POST" action="/admin/reviews/approve" class="d-inline" @submit.prevent="approve(<?= (int) $id ?>)">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-success" :disabled="processing.includes(<?= (int) $id ?>)">
                <i class="bi bi-check-lg me-1"></i>
                <span x-show="!processing.includes(<?= (int) $id ?>)">Aprobar</span>
                <span x-show="processing.includes(<?= (int) $id ?>)" class="spinner-border spinner-border-sm"></span>
            </button>
        </form>

        <!-- Rechazar -->
        <button
            type="button"
            class="btn btn-outline-danger"
            @click="openRejectModal({id: <?= (int) $id ?>, author: <?= json_encode($author) ?>})"
            :disabled="processing.includes(<?= (int) $id ?>)">
            <i class="bi bi-x-lg me-1"></i>
            Rechazar
        </button>
    </div>
</article>
