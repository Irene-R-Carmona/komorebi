<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Componente: Formulario para crear reseña
 *
 * Variables esperadas:
 * - int $cafeId
 * - bool $canReview
 * - array $reviewEligibility (contiene 'reason' si no puede)
 */

if (!$canReview): ?>
    <div class="review-form__blocked">
        <div class="review-form__blocked-icon"><i class="bi bi-lock-fill" aria-hidden="true"></i></div>
        <h4>No puedes dejar reseña aún</h4>
        <p><?= e($reviewEligibility['reason'] ?? 'Debes tener una reserva completada para dejar reseña') ?></p>
        <a href="/reservas?cafe=<?= (int) $cafeId ?>" class="btn btn--pequeno btn--primario">
            Hacer una reserva
        </a>
    </div>
<?php else: ?>
    <form method="POST" action="/reviews" class="review-form" x-data="reviewForm()" @submit.prevent="submitForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="cafe_id" value="<?= (int) $cafeId ?>">

        <!-- Rating Stars (KISS: input hidden, visual stars) -->
        <div class="form-group">
            <label class="form-label">Tu valoración</label>
            <div class="rating-picker">
                <template x-for="i in [1, 2, 3, 4, 5]" :key="i">
                    <button
                        type="button"
                        class="rating-star"
                        :class="{ 'rating-star--active': rating >= i }"
                        @click="rating = i"
                        @mouseover="hoverRating = i"
                        @mouseleave="hoverRating = 0">
                        <span :class="{ 'star-filled': (hoverRating || rating) >= i }">★</span>
                    </button>
                </template>
            </div>
            <input type="hidden" name="rating" :value="rating" required>
            <small x-show="rating > 0" class="form-help">
                <span x-text="ratingLabel"></span>
            </small>
        </div>

        <!-- Título -->
        <div class="form-group">
            <label class="form-label" for="title">Título de tu reseña</label>
            <input
                id="title"
                type="text"
                name="title"
                class="form-input"
                placeholder="p. ej. Una experiencia increíble"
                x-model="title"
                @input="validateTitle"
                maxlength="100"
                required>
            <small class="form-help" :class="{ 'form-error': !titleValid && title }">
                <span x-text="titleLength + '/100'"></span>
            </small>
        </div>

        <!-- Descripción -->
        <div class="form-group">
            <label class="form-label" for="body">Tu opinión</label>
            <textarea
                id="body"
                name="body"
                class="form-textarea"
                placeholder="Cuéntanos qué te pareció. Sé específico y honesto."
                x-model="body"
                @input="validateBody"
                minlength="10"
                maxlength="5000"
                rows="6"
                required></textarea>
            <small class="form-help" :class="{ 'form-error': !bodyValid && body }">
                <span x-text="bodyLength + '/5000'"></span> •
                <span x-text="bodyValid ? '✓ Listo' : '⚠ Mínimo 10 caracteres'"></span>
            </small>
        </div>

        <!-- Botones -->
        <div class="form-actions">
            <button
                type="submit"
                class="btn btn--primario btn--lg"
                :disabled="!isFormValid">
                Publicar reseña
            </button>
            <p class="form-note">
                <i class="bi bi-info-circle" aria-hidden="true"></i> Tu reseña será revisada antes de aparecer públicamente.
            </p>
        </div>
    </form>

    <!-- Component JS cargado desde /js/init/alpine-components.js -->
<?php endif; ?>
