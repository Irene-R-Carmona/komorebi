<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Repositories\Contracts\NewsletterSubscriptionRepositoryInterface;
use PDO;

final class NewsletterSubscriptionRepository implements NewsletterSubscriptionRepositoryInterface
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, confirmed_at, unsubscribed_at FROM newsletter_subscriptions WHERE email = ?'
        );
        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, confirmed_at, expires_at FROM newsletter_subscriptions WHERE token = ?'
        );
        $stmt->execute([$token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTokenByEmail(string $email): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT token FROM newsletter_subscriptions WHERE email = ?'
        );
        $stmt->execute([$email]);

        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function create(string $email, string $token, string $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO newsletter_subscriptions (email, token, expires_at) VALUES (?, ?, ?)'
        );

        return $stmt->execute([$email, $token, $expiresAt]);
    }

    public function reactivate(string $email, string $token, string $expiresAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE newsletter_subscriptions
             SET token = ?, expires_at = ?, unsubscribed_at = NULL, updated_at = NOW()
             WHERE email = ?'
        );

        return $stmt->execute([$token, $expiresAt, $email]);
    }

    public function markConfirmed(string $token): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE newsletter_subscriptions SET confirmed_at = NOW() WHERE token = ?'
        );

        return $stmt->execute([$token]);
    }

    public function markUnsubscribed(string $token): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE newsletter_subscriptions SET unsubscribed_at = NOW() WHERE token = ?'
        );

        return $stmt->execute([$token]);
    }

    public function getConfirmedEmails(int $limit = 500): array
    {
        $stmt = $this->db->prepare(
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
