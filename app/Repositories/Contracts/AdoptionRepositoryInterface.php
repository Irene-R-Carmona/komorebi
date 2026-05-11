<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AdoptionRepositoryInterface
{
    // ─── Animales adoptables ─────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function findAdoptable(): array;

    // ─── Solicitudes ─────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function findPendingRequests(?int $cafeId = null): array;

    /** @return array<int, array<string, mixed>> */
    public function findRequestsByUser(int $userId): array;

    /** @return array<string, mixed>|null */
    public function findRequestById(int $id): ?array;

    public function hasPendingRequest(int $animalId, int $userId): bool;

    public function createRequest(int $animalId, int $userId, ?string $message): int;

    public function updateRequest(
        int    $id,
        string $status,
        ?int   $reviewedBy,
        ?string $keeperNotes
    ): bool;

    /** @return array<int, array<string, mixed>> */
    public function findProcessedRequests(?int $cafeId = null): array;
}
