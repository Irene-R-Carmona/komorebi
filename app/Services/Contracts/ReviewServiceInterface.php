<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReviewServiceInterface
{
    public function createReview(int $userId, int $cafeId, int $rating, string $title, string $body): Result;

    public function updateReview(int $reviewId, int $userId, int $rating, string $title, string $body): Result;

    public function deleteReview(int $reviewId, ?int $userId = null): bool|Result;

    public function canUserReview(int $userId, int $cafeId): array;

    public function userHasCompletedReservation(int $userId, int $cafeId): bool;

    public function userHasReviewInCafe(int $userId, int $cafeId): bool;
}
