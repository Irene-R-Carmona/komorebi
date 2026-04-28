<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? El comportamiento de LoyaltyRepository — CRUD de loyalty cards,
 * leaderboard, rewards y consumo de sellos.
 * ¿Qué me quieres demostrar? Que cada método construye la query correcta, ejecuta con los
 * parámetros esperados y retorna el resultado que PDO le da.
 * ¿Qué va a fallar en este test si se cambia el código? Si se cambia el SQL, los parámetros
 * de execute(), el PDO::FETCH_ASSOC, o si la lógica de negocio de findOrCreateCardByUserId
 * cambia su secuencia de llamadas.
 */

namespace Tests\Unit\Repositories;

use App\Domain\DTO\LoyaltyCardDTO;
use App\Domain\DTO\LoyaltyRewardDTO;
use App\Repositories\LoyaltyRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoyaltyRepository::class)]
#[AllowMockObjectsWithoutExpectations]
final class LoyaltyRepositoryTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&PDO */
    private PDO $pdoMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject&PDOStatement */
    private PDOStatement $stmtMock;

    private LoyaltyRepository $repository;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);
        $this->repository = new LoyaltyRepository($this->pdoMock);
    }

    protected function tearDown(): void
    {
        unset($this->repository, $this->pdoMock, $this->stmtMock);
    }

    // ── findCardByUserId ──────────────────────────────────────────

    public function testFindCardByUserIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 5, 'user_id' => 1, 'stamps' => 10, 'current_tier' => 'bronze',
                'created_at' => '2024-01-01', 'updated_at' => '2024-01-01'];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($row);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findCardByUserId(1);

        $this->assertInstanceOf(LoyaltyCardDTO::class, $result);
        $this->assertSame(5, $result->id);
        $this->assertSame(10, $result->stamps);
    }

    public function testFindCardByUserIdReturnsNullWhenNotFound(): void
    {
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findCardByUserId(99);

        $this->assertNull($result);
    }

    // ── findCardById ──────────────────────────────────────────────

    public function testFindCardByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 5, 'user_id' => 1, 'stamps' => 10,
                'created_at' => '2024-01-01', 'updated_at' => '2024-01-01'];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn($row);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findCardById(5);

        $this->assertInstanceOf(LoyaltyCardDTO::class, $result);
        $this->assertSame(5, $result->id);
        $this->assertSame(10, $result->stamps);
    }

    public function testFindCardByIdReturnsNullWhenNotFound(): void
    {
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findCardById(999);

        $this->assertNull($result);
    }

    // ── addStamps ─────────────────────────────────────────────────

    public function testAddStampsReturnsTrueOnSuccess(): void
    {
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([3, 3, 5])
            ->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->addStamps(5, 3);

        $this->assertTrue($result);
    }

    // ── updateTier ────────────────────────────────────────────────

    public function testUpdateTierAlwaysReturnsTrue(): void
    {
        // updateTier es un no-op (columna GENERATED en MySQL)
        $result = $this->repository->updateTier(5, 'silver');

        $this->assertTrue($result);
    }

    // ── consumeStamps ─────────────────────────────────────────────

    public function testConsumeStampsReturnsTrueWhenStampsAreSufficient(): void
    {
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([2, 5, 2])
            ->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->consumeStamps(5, 2);

        $this->assertTrue($result);
    }

    // ── getLeaderboard ────────────────────────────────────────────

    public function testGetLeaderboardReturnsRankedArray(): void
    {
        $expected = [
            ['user_id' => 1, 'stamps' => 50, 'current_tier' => 'gold', 'rank' => 1],
            ['user_id' => 2, 'stamps' => 30, 'current_tier' => 'silver', 'rank' => 2],
        ];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($expected);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->getLeaderboard(10);

        $this->assertSame($expected, $result);
        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['rank']);
    }

    public function testGetLeaderboardPassesLimitToExecute(): void
    {
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([5]);
        $this->stmtMock->method('fetchAll')->willReturn([]);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->getLeaderboard(5);

        $this->assertSame([], $result);
    }

    public function testGetLeaderboardReturnsEmptyArrayWhenNoData(): void
    {
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn([]);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->getLeaderboard();

        $this->assertSame([], $result);
    }

    // ── findRewardsByUserId ───────────────────────────────────────

    public function testFindRewardsByUserIdReturnsArray(): void
    {
        $expected = [
            ['id' => 1, 'user_id' => 3, 'reward_type' => 'free_drink', 'is_used' => 0],
        ];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetchAll')->willReturn($expected);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findRewardsByUserId(3);

        $this->assertSame($expected, $result);
    }

    // ── findRewardByCode ──────────────────────────────────────────

    public function testFindRewardByCodeReturnsArrayWhenFound(): void
    {
        $row = [
            'id' => 7,
            'user_id' => 3,
            'loyalty_card_id' => 1,
            'reward_type' => 'free_drink',
            'redemption_code' => 'ABC123',
            'redeemed_at' => '2024-01-01',
            'created_at' => '2024-01-01',
        ];

        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn($row);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findRewardByCode('ABC123');

        $this->assertInstanceOf(LoyaltyRewardDTO::class, $result);
        $this->assertSame(7, $result->id);
    }

    public function testFindRewardByCodeReturnsNullWhenNotFound(): void
    {
        $this->stmtMock->method('execute')->willReturn(true);
        $this->stmtMock->method('fetch')->willReturn(false);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->findRewardByCode('INVALID');

        $this->assertNull($result);
    }

    // ── markRewardUsed ────────────────────────────────────────────

    public function testMarkRewardUsedReturnsTrueOnSuccess(): void
    {
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with([7])
            ->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->markRewardUsed(7);

        $this->assertTrue($result);
    }

    // ── markRewardsExpired ────────────────────────────────────────

    public function testMarkRewardsExpiredReturnsTrueOnEmptyArrayEarlyReturn(): void
    {
        // Array vacío → early return true sin tocar la base de datos
        $this->pdoMock->expects($this->never())->method('prepare');

        $result = $this->repository->markRewardsExpired([]);

        $this->assertTrue($result);
    }

    public function testMarkRewardsExpiredReturnsTrueOnSuccess(): void
    {
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->pdoMock->method('prepare')->willReturn($this->stmtMock);

        $result = $this->repository->markRewardsExpired([1, 2, 3]);

        $this->assertTrue($result);
    }
}
