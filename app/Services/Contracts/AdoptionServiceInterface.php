<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Result;

interface AdoptionServiceInterface
{
    public function requestAdoption(int $userId, int $animalId, ?string $message): Result;

    public function withdrawRequest(int $userId, int $requestId): Result;

    public function approveRequest(int $keeperId, int $requestId, int $cafeId): Result;

    public function rejectRequest(int $keeperId, int $requestId, ?string $notes, int $cafeId): Result;

    /** @return array<int, array<string, mixed>> */
    public function getAdoptableAnimals(): array;

    /** @return array<int, array<string, mixed>> */
    public function getPendingRequests(?int $cafeId = null): array;

    /** @return array<int, array<string, mixed>> */
    public function getUserRequests(int $userId): array;

    /** @return array<int, array<string, mixed>> */
    public function getProcessedRequests(?int $cafeId = null): array;
}
