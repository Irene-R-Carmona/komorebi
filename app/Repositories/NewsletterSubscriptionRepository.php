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

    /**
     * @param array<string, mixed> $filters  Claves opcionales: email (LIKE), status (confirmed|pending|unsubscribed)
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, has_next: bool}
     */
    public function getAllPaginated(int $page, int $perPage, array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['email'])) {
            $where[] = 'email LIKE ?';
            $params[] = '%' . $filters['email'] . '%';
        }

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'confirmed' => $where[] = 'confirmed_at IS NOT NULL AND unsubscribed_at IS NULL',
                'pending' => $where[] = 'confirmed_at IS NULL AND unsubscribed_at IS NULL',
                'unsubscribed' => $where[] = 'unsubscribed_at IS NOT NULL',
                default => null,
            };
        }

        $whereClause = $where !== [] ? 'WHERE ' . \implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->getDb()->prepare("SELECT COUNT(*) FROM newsletter_subscriptions {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataParams = $params;
        $dataParams[] = $perPage;
        $dataParams[] = $offset;
        $dataStmt = $this->getDb()->prepare(
            "SELECT id, email, confirmed_at, unsubscribed_at, created_at
             FROM newsletter_subscriptions
             {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $dataStmt->execute($dataParams);

        return [
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_next' => ($page * $perPage) < $total,
        ];
    }

    /**
     * @return array{total: int, confirmed: int, pending: int, unsubscribed: int, this_month: int}
     */
    public function getAdminStats(): array
    {
        $stmt = $this->getDb()->query(
            'SELECT
               COUNT(*) AS total,
               SUM(confirmed_at IS NOT NULL AND unsubscribed_at IS NULL) AS confirmed,
               SUM(confirmed_at IS NULL AND unsubscribed_at IS NULL)     AS pending,
               SUM(unsubscribed_at IS NOT NULL)                          AS unsubscribed,
               SUM(YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())) AS this_month
             FROM newsletter_subscriptions'
        );

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'unsubscribed' => 0, 'this_month' => 0];
        }

        return [
            'total' => (int) $row['total'],
            'confirmed' => (int) $row['confirmed'],
            'pending' => (int) $row['pending'],
            'unsubscribed' => (int) $row['unsubscribed'],
            'this_month' => (int) $row['this_month'],
        ];
    }

    public function deleteByEmail(string $email): bool
    {
        $stmt = $this->getDb()->prepare(
            'DELETE FROM newsletter_subscriptions WHERE email = ?'
        );

        return $stmt->execute([$email]);
    }
}
