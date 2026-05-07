<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface ReviewModerationServiceInterface
{
    public function approveReview(int $reviewId): Result;

    public function rejectReview(int $reviewId, string $reason): Result;

    public function moderateReview(int $reviewId, string $status): bool;

    public function listPendingReviews(int $page = 1): array;

    public function deleteReviewById(int $reviewId): bool;
}
