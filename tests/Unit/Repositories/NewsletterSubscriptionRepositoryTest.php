<?php

/**
 * ¿Qué pruebas aquí? NewsletterSubscriptionRepository: findByEmail, findByToken,
 *   getTokenByEmail, create, reactivate, markConfirmed, markUnsubscribed,
 *   getConfirmedEmails.
 * ¿Qué me quieres demostrar? Que getTokenByEmail retorna null cuando fetchColumn()
 *   devuelve false, que getConfirmedEmails usa bindValue antes de execute(), y
 *   que create/reactivate/markConfirmed/markUnsubscribed devuelven el bool de
 *   execute().
 * ¿Qué va a fallar en este test si se cambia el código? Si getTokenByEmail deja
 *   de retornar null cuando no hay resultado, o si getConfirmedEmails deja de
 *   usar bindValue/fetchAll.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\NewsletterSubscriptionRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterSubscriptionRepository::class)]
final class NewsletterSubscriptionRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeStmt(
        array $fetchAllReturn = [],
        array|false $fetchReturn = false,
        bool $executeReturn = true,
        mixed $fetchColumnReturn = false,
    ): PDOStatement {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn($executeReturn);
        $stmt->method('fetchAll')->willReturn($fetchAllReturn);
        $stmt->method('fetch')->willReturn($fetchReturn);
        $stmt->method('fetchColumn')->willReturn($fetchColumnReturn);
        $stmt->method('bindValue')->willReturn(true);
        return $stmt;
    }

    private function makePdo(PDOStatement $stmt): PDO
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        return $pdo;
    }

    // -------------------------------------------------------------------------
    // findByEmail
    // -------------------------------------------------------------------------

    public function testFindByEmailReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'confirmed_at' => '2024-01-01', 'unsubscribed_at' => null];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $result = $repo->findByEmail('user@example.com');
        $this->assertNotNull($result);
        $this->assertSame(1, $result['id']);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertNull($repo->findByEmail('noexiste@example.com'));
    }

    // -------------------------------------------------------------------------
    // findByToken
    // -------------------------------------------------------------------------

    public function testFindByTokenReturnsArrayWhenFound(): void
    {
        $row = ['id' => 2, 'email' => 'u@example.com', 'confirmed_at' => null, 'expires_at' => '2025-01-01'];
        $stmt = $this->makeStmt(fetchReturn: $row);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $result = $repo->findByToken('abc123');
        $this->assertNotNull($result);
        $this->assertSame('u@example.com', $result['email']);
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchReturn: false);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertNull($repo->findByToken('token-invalido'));
    }

    // -------------------------------------------------------------------------
    // getTokenByEmail
    // -------------------------------------------------------------------------

    public function testGetTokenByEmailReturnsStringWhenFound(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: 'mi-token-secreto');
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $result = $repo->getTokenByEmail('user@example.com');
        $this->assertSame('mi-token-secreto', $result);
    }

    public function testGetTokenByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->makeStmt(fetchColumnReturn: false);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertNull($repo->getTokenByEmail('noexiste@example.com'));
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->create('new@example.com', 'token-abc', '2025-06-01 00:00:00'));
    }

    // -------------------------------------------------------------------------
    // reactivate
    // -------------------------------------------------------------------------

    public function testReactivateReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->reactivate('user@example.com', 'new-token', '2025-06-01 00:00:00'));
    }

    // -------------------------------------------------------------------------
    // markConfirmed
    // -------------------------------------------------------------------------

    public function testMarkConfirmedReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markConfirmed('token-confirm'));
    }

    public function testMarkConfirmedReturnsFalseOnFailure(): void
    {
        $stmt = $this->makeStmt(executeReturn: false);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertFalse($repo->markConfirmed('token-invalido'));
    }

    // -------------------------------------------------------------------------
    // markUnsubscribed
    // -------------------------------------------------------------------------

    public function testMarkUnsubscribedReturnsTrueOnSuccess(): void
    {
        $stmt = $this->makeStmt(executeReturn: true);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertTrue($repo->markUnsubscribed('token-unsub'));
    }

    // -------------------------------------------------------------------------
    // getConfirmedEmails (usa bindValue + fetchAll)
    // -------------------------------------------------------------------------

    public function testGetConfirmedEmailsReturnsArray(): void
    {
        $emails = ['a@example.com', 'b@example.com'];
        $stmt = $this->makeStmt(fetchAllReturn: $emails);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $result = $repo->getConfirmedEmails(100);
        $this->assertSame(['a@example.com', 'b@example.com'], $result);
    }

    public function testGetConfirmedEmailsReturnsEmptyWhenNone(): void
    {
        $stmt = $this->makeStmt(fetchAllReturn: []);
        $repo = new NewsletterSubscriptionRepository($this->makePdo($stmt));

        $this->assertSame([], $repo->getConfirmedEmails());
    }
}
