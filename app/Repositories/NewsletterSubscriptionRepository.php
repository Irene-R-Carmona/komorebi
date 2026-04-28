<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\DTO\NewsletterSubscriptionDTO;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use Override;
use PDO;

final class NewsletterSubscriptionRepository extends AbstractRepository implements NewsletterSubscriptionRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
    }

    #[Override]
    protected function getTable(): string
    {
        return 'newsletter_subscriptions';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'email', 'token', 'confirmed_at', 'unsubscribed_at', 'created_at', 'expires_at'];
    }

    public function findByEmail(string $email): ?NewsletterSubscriptionDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, email, token, confirmed_at, unsubscribed_at, created_at, expires_at FROM newsletter_subscriptions WHERE email = ?'
        );
        $stmt->execute([$email]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? NewsletterSubscriptionDTO::fromArray($row) : null;
    }

    public function findByToken(string $token): ?NewsletterSubscriptionDTO
    {
        $stmt = $this->getDb()->prepare(
            'SELECT id, email, token, confirmed_at, unsubscribed_at, created_at, expires_at FROM newsletter_subscriptions WHERE token = ?'
        );
        $stmt->execute([$token]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? NewsletterSubscriptionDTO::fromArray($row) : null;
    }

    public function getTokenByEmail(string $email): ?string
    {
        $stmt = $this->getDb()->prepare(
            'SELECT token FROM newsletter_subscriptions WHERE email = ?'
        );
        $stmt->execute([$email]);

        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function subscribe(string $email, string $token, string $expiresAt): bool
    {
        $stmt = $this->getDb()->prepare(
            'INSERT INTO newsletter_subscriptions (email, token, expires_at) VALUES (?, ?, ?)'
        );

        return $stmt->execute([$email, $token, $expiresAt]);
    }

    public function reactivate(string $email, string $token, string $expiresAt): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE newsletter_subscriptions
             SET token = ?, expires_at = ?, unsubscribed_at = NULL, updated_at = NOW()
             WHERE email = ?'
        );

        return $stmt->execute([$token, $expiresAt, $email]);
    }

    public function markConfirmed(string $token): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE newsletter_subscriptions SET confirmed_at = NOW() WHERE token = ?'
        );

        return $stmt->execute([$token]);
    }

    public function markUnsubscribed(string $token): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE newsletter_subscriptions SET unsubscribed_at = NOW() WHERE token = ?'
        );

        return $stmt->execute([$token]);
    }

    public function getConfirmedEmails(int $limit = 500): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT email
             FROM newsletter_subscriptions
             WHERE confirmed_at IS NOT NULL
               AND unsubscribed_at IS NULL
             ORDER BY confirmed_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
