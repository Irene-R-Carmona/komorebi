<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\NewsletterSubscriptionDTO;

interface NewsletterSubscriptionRepositoryInterface
{
    public function findByEmail(string $email): ?NewsletterSubscriptionDTO;

    public function findByToken(string $token): ?NewsletterSubscriptionDTO;

    public function getTokenByEmail(string $email): ?string;

    public function subscribe(string $email, string $token, string $expiresAt): bool;

    /** Reactiva una suscripción dada de baja: asigna nuevo token y limpia unsubscribed_at. */
    public function reactivate(string $email, string $token, string $expiresAt): bool;

    public function markConfirmed(string $token): bool;

    public function markUnsubscribed(string $token): bool;

    /** @return array<int, string> Lista de emails confirmados y activos */
    public function getConfirmedEmails(int $limit = 500): array;

    /**
     * Paginación para el panel admin.
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, has_next: bool}
     */
    public function getAllPaginated(int $page, int $perPage, array $filters = []): array;

    /**
     * @return array{total: int, confirmed: int, pending: int, unsubscribed: int, this_month: int}
     */
    public function getAdminStats(): array;

    public function deleteByEmail(string $email): bool;
}
