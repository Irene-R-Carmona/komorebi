<?php

/**
 * ¿Qué prueba aquí? WaitlistRepository — acceso a datos de lista de espera.
 * ¿Qué me quieres demostrar? El repositorio delega en PDO y devuelve los tipos correctos para cada
 *   método: WaitlistEntryDTO, array, bool, int, incluyendo la lógica de fallback y secuencias multi-consulta.
 * ¿Qué va a fallar en este test si se cambia el código? Cambios en la firma pública, en las queries de
 *   expireTokens/create, o en la lógica de getSummaryByStatus/getAllWithDetails.
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Domain\DTO\WaitlistEntryDTO;
use App\Repositories\WaitlistRepository;

final class WaitlistRepositoryTest extends RepositoryTestCase
{
    // ─────────────────────────────────────────────────────────────
    // findById
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsDtoWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::waitlistEntryRow());
        $repo = new WaitlistRepository($pdo);

        $dto = $repo->findById(1);

        $this->assertInstanceOf(WaitlistEntryDTO::class, $dto);
        $this->assertSame(1, $dto->id);
        $this->assertSame('waiting', $dto->status);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertNull($repo->findById(99));
    }

    // ─────────────────────────────────────────────────────────────
    // findByToken
    // ─────────────────────────────────────────────────────────────

    public function testFindByTokenReturnsDtoWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: RowFactory::waitlistEntryRow(['token' => 'abc-xyz']));
        $repo = new WaitlistRepository($pdo);

        $dto = $repo->findByToken('abc-xyz');

        $this->assertInstanceOf(WaitlistEntryDTO::class, $dto);
        $this->assertSame('abc-xyz', $dto->token);
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertNull($repo->findByToken('no-token'));
    }

    // ─────────────────────────────────────────────────────────────
    // findActiveByUserId
    // ─────────────────────────────────────────────────────────────

    public function testFindActiveByUserIdReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [
            RowFactory::waitlistEntryRow(),
            RowFactory::waitlistEntryRow(['id' => 2, 'position' => 2]),
        ]);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->findActiveByUserId(1);

        $this->assertCount(2, $result);
    }

    public function testFindActiveByUserIdReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame([], $repo->findActiveByUserId(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getPosition
    // ─────────────────────────────────────────────────────────────

    public function testGetPositionReturnsIntWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['position' => '3']);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame(3, $repo->getPosition(10, 1));
    }

    public function testGetPositionReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertNull($repo->getPosition(10, 1));
    }

    // ─────────────────────────────────────────────────────────────
    // getNextInLine
    // ─────────────────────────────────────────────────────────────

    public function testGetNextInLineReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'user_email' => 'u@test.com', 'position' => 1];
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->getNextInLine(10);

        $this->assertIsArray($result);
        $this->assertSame('u@test.com', $result['user_email']);
    }

    public function testGetNextInLineReturnsNullWhenEmpty(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertNull($repo->getNextInLine(10));
    }

    // ─────────────────────────────────────────────────────────────
    // create (2 prepares: SELECT position + INSERT)
    // ─────────────────────────────────────────────────────────────

    public function testCreateReturnsNewId(): void
    {
        $pdo = $this->makeMultiCallPdo([
            ['fetch' => ['next_position' => 2]],  // SELECT COALESCE(MAX(position), 0)+1
            ['rowCount' => 1],                     // INSERT
        ]);
        $repo = new WaitlistRepository($pdo);

        $id = $repo->create([
            'user_id'          => 1,
            'time_slot_id'     => 10,
            'guest_count'      => 2,
            'status'           => 'waiting',
            'token'            => 'tok-abc',
            'expires_at'       => null,
            'contact_email'    => 'u@test.com',
            'contact_phone'    => null,
            'special_requests' => null,
        ]);

        $this->assertIsInt($id);
    }

    // ─────────────────────────────────────────────────────────────
    // updateStatusWithData
    // ─────────────────────────────────────────────────────────────

    public function testUpdateStatusWithDataReturnsTrueNoExtras(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->updateStatusWithData(1, 'confirmed'));
    }

    public function testUpdateStatusWithDataReturnsTrueWithExtras(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->updateStatusWithData(1, 'promoted', [
            'expires_at'     => '2025-06-21 12:00:00',
            'reservation_id' => 42,
        ]));
    }

    // ─────────────────────────────────────────────────────────────
    // reorderPositions
    // ─────────────────────────────────────────────────────────────

    public function testReorderPositionsReturnsTrue(): void
    {
        $pdo = $this->makePdo(rowCount: 3);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->reorderPositions(10, 1));
    }

    // ─────────────────────────────────────────────────────────────
    // userInWaitlist
    // ─────────────────────────────────────────────────────────────

    public function testUserInWaitlistReturnsTrueWhenFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: ['1' => 1]);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->userInWaitlist(1, 10));
    }

    public function testUserInWaitlistReturnsFalseWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertFalse($repo->userInWaitlist(1, 10));
    }

    // ─────────────────────────────────────────────────────────────
    // updateStatus
    // ─────────────────────────────────────────────────────────────

    public function testUpdateStatusReturnsTrue(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->updateStatus(1, 'expired'));
    }

    // ─────────────────────────────────────────────────────────────
    // updateToken
    // ─────────────────────────────────────────────────────────────

    public function testUpdateTokenReturnsTrue(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->updateToken(1, 'new-token', '2025-06-25 10:00:00'));
    }

    // ─────────────────────────────────────────────────────────────
    // cancel
    // ─────────────────────────────────────────────────────────────

    public function testCancelReturnsTrue(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->cancel(1, 1));
    }

    // ─────────────────────────────────────────────────────────────
    // expireTokens
    // ─────────────────────────────────────────────────────────────

    public function testExpireTokensReturnsAffectedCount(): void
    {
        $pdo = $this->makePdo(rowCount: 4);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame(4, $repo->expireTokens());
    }

    public function testExpireTokensReturnsZeroWhenNoneExpired(): void
    {
        $pdo = $this->makePdo(rowCount: 0);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame(0, $repo->expireTokens());
    }

    // ─────────────────────────────────────────────────────────────
    // getUserHistory
    // ─────────────────────────────────────────────────────────────

    public function testGetUserHistoryReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::waitlistEntryRow()]);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->getUserHistory(1, 5);

        $this->assertCount(1, $result);
    }

    public function testGetUserHistoryReturnsEmptyArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame([], $repo->getUserHistory(1));
    }

    // ─────────────────────────────────────────────────────────────
    // getAllWithDetails
    // ─────────────────────────────────────────────────────────────

    public function testGetAllWithDetailsReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::waitlistEntryRow()]);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->getAllWithDetails(['status' => 'waiting', 'cafe_id' => 1]);

        $this->assertCount(1, $result);
    }

    public function testGetAllWithDetailsWithNoFiltersReturnsRows(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [RowFactory::waitlistEntryRow()]);
        $repo = new WaitlistRepository($pdo);

        $this->assertCount(1, $repo->getAllWithDetails());
    }

    // ─────────────────────────────────────────────────────────────
    // getSummaryByStatus (usa query() directamente)
    // ─────────────────────────────────────────────────────────────

    public function testGetSummaryByStatusReturnsMappedArray(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: [
            ['status' => 'waiting',   'count' => '5'],
            ['status' => 'confirmed', 'count' => '2'],
        ]);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->getSummaryByStatus();

        $this->assertSame(5, $result['waiting']);
        $this->assertSame(2, $result['confirmed']);
    }

    public function testGetSummaryByStatusReturnsEmptyWhenNoData(): void
    {
        $pdo = $this->makePdo(fetchAllReturn: []);
        $repo = new WaitlistRepository($pdo);

        $this->assertSame([], $repo->getSummaryByStatus());
    }

    // ─────────────────────────────────────────────────────────────
    // cancelById
    // ─────────────────────────────────────────────────────────────

    public function testCancelByIdReturnsTrue(): void
    {
        $pdo = $this->makePdo(rowCount: 1);
        $repo = new WaitlistRepository($pdo);

        $this->assertTrue($repo->cancelById(1));
    }

    // ─────────────────────────────────────────────────────────────
    // findByIdAndUser
    // ─────────────────────────────────────────────────────────────

    public function testFindByIdAndUserReturnsArray(): void
    {
        $row = RowFactory::waitlistEntryRow();
        $pdo = $this->makePdo(fetchReturn: $row);
        $repo = new WaitlistRepository($pdo);

        $result = $repo->findByIdAndUser(1, 1);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
    }

    public function testFindByIdAndUserReturnsNullWhenNotFound(): void
    {
        $pdo = $this->makePdo(fetchReturn: false);
        $repo = new WaitlistRepository($pdo);

        $this->assertNull($repo->findByIdAndUser(1, 99));
    }

    // ─────────────────────────────────────────────────────────────
    // countByTimeSlotAndStatus
    // ─────────────────────────────────────────────────────────────

    public function testCountByTimeSlotAndStatusReturnsInt(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '7');
        $repo = new WaitlistRepository($pdo);

        $this->assertSame(7, $repo->countByTimeSlotAndStatus(10, 'waiting'));
    }

    public function testCountByTimeSlotAndStatusReturnsZero(): void
    {
        $pdo = $this->makePdo(fetchColumnReturn: '0');
        $repo = new WaitlistRepository($pdo);

        $this->assertSame(0, $repo->countByTimeSlotAndStatus(10, 'expired'));
    }
}
