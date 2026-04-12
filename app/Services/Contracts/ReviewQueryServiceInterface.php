<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ReviewQueryServiceInterface
{
    public function getReviewsByUserId(int $userId): array;

    public function getReviewsByCafeId(int $cafeId): array;

    public function calculateAverageRating(int $cafeId): float;

    public function listApprovedReviews(int $cafeId, int $page = 1): array;

    public function listUserReviews(int $userId): array;

    public function getCafeRatingStats(int $cafeId): array;

    public function getReview(int $reviewId): ?array;
}
