<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\NewsletterSubscriptionDTO;

interface NewsletterSubscriptionRepositoryInterface
{
    public function findByEmail(string $email): ?NewsletterSubscriptionDTO;

    public function findByToken(string $token): ?NewsletterSubscriptionDTO;

    public function getTokenByEmail(string $email): ?string;

    public function create(string $email, string $token, string $expiresAt): bool;

    /** Reactiva una suscripción dada de baja: asigna nuevo token y limpia unsubscribed_at. */
    public function reactivate(string $email, string $token, string $expiresAt): bool;

    public function markConfirmed(string $token): bool;

    public function markUnsubscribed(string $token): bool;

    /** @return array<int, string> Lista de emails confirmados y activos */
    public function getConfirmedEmails(int $limit = 500): array;
}
