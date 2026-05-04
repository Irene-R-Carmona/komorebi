<?php

declare(strict_types=1);

/**
 * Componente: Listado de reseñas aprobadas
 *
 * Variables esperadas:
 * - int $cafeId
 * - array $approvedReviews (data, total, pages)
 * - int $page (actual)
 */

$reviews = $approvedReviews['data'] ?? [];
$total = $approvedReviews['total'] ?? 0;
$pages = $approvedReviews['pages'] ?? 1;
$page = (int) ($page ?? 1);

if ($total === 0): ?>
    <div class="reviews-list__empty">
        <p>Aún no hay reseñas. ¡Sé el primero!</p>
    </div>
<?php else: ?>
    <div class="reviews-list">
        <?php foreach ($reviews as $review): ?>
            <article class="review-card">
                <!-- Header: Avatar + Nombre + Fecha -->
                <div class="review-card__header">
                    <?php
                    $name = e($review['user_name'] ?? $review['name'] ?? 'Anónimo');
                    $firstLetter = mb_strtoupper(mb_substr($name, 0, 1));
                    $colorIndex = \ord($firstLetter) % 6;
                    $avatarClass = $colorIndex > 0 ? ' review-card__avatar--c' . $colorIndex : '';
                    ?>
                    <div class="review-card__avatar<?= $avatarClass ?>">
                        <span><?= $firstLetter ?></span>
                    </div>
                    <div class="review-card__meta">
                        <h4 class="review-card__author"><?= $name ?></h4>
                        <time class="review-card__date">
                            <?= date('d \d\e F, Y', strtotime($review['created_at'] ?? 'now')) ?>
                        </time>
                    </div>
                </div>

                <!-- Rating -->
                <div class="review-card__rating">
                    <?php
                    $rating = (int) ($review['rating'] ?? 0);
                    for ($i = 1; $i <= 5; $i++):
                        $filled = $i <= $rating ? 'review-star--filled' : '';
                    ?>
                        <span class="review-star <?= $filled ?>">★</span>
                    <?php endfor; ?>
                    <span class="review-card__rating-value"><?= $rating ?>/5</span>
                </div>

                <!-- Título -->
                <h3 class="review-card__title">
                    <?= e($review['title'] ?? '') ?>
                </h3>

                <!-- Body -->
                <p class="review-card__body">
                    <?= e($review['body'] ?? '') ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- Paginación -->
    <?php if ($pages > 1): ?>
        <nav class="reviews-pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>#reviews-section" class="btn btn--pequeno btn--secundario">
                    ← Anterior
                </a>
            <?php endif; ?>

            <span class="pagination-info">
                Página <strong><?= $page ?></strong> de <strong><?= $pages ?></strong>
            </span>

            <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?>#reviews-section" class="btn btn--pequeno btn--secundario">
                    Siguiente →
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
