<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí? WaitlistService: validaciones al unirse a la lista de espera.
 * ¿Qué me quieres demostrar? Que joinWaitlist retorna fail cuando el slot no existe o aún tiene plazas.
 * ¿Qué va a fallar en este test si se cambia el código? Si se eliminan las guardas del slot en joinWaitlist.
 */

namespace Tests\Unit\Services;

use App\Domain\DTO\TimeSlotDTO;
use App\Domain\DTO\WaitlistEntryDTO;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\SettingsServiceInterface;
use App\Services\WaitlistService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WaitlistService::class)]
final class WaitlistServiceTest extends TestCase
{
    private PDO $pdoStub;
    private EmailServiceInterface $emailServiceStub;
    private WaitlistRepositoryInterface $waitlistRepoStub;

    protected function setUp(): void
    {
        $this->pdoStub = $this->createStub(PDO::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
    }

    private function makeService(?TimeSlotDTO $slot = null, ?SettingsServiceInterface $settings = null): WaitlistService
    {
        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $timeSlotRepoStub->method('findById')->willReturn($slot);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        return new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $this->waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub,
            $settings,
        );
    }

    private function slotWithSpots(int $spots): TimeSlotDTO
    {
        return new TimeSlotDTO(
            id: 1,
            cafe_id: 1,
            slot_date: '2025-12-01',
            slot_time: '10:00:00',
            total_capacity: 20,
            available_spots: $spots,
            reserved_spots: 20 - $spots,
            is_blocked: false,
            blocked_reason: null,
            duration_minutes: 60,
            created_at: '2025-01-01 00:00:00',
            updated_at: '2025-01-01 00:00:00',
        );
    }

    public function testJoinWaitlistFailsWhenSlotNotFound(): void
    {
        $result = $this->makeService(null)->joinWaitlist(999, 1, ['guests' => 2]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    public function testJoinWaitlistFailsWhenSlotHasAvailableSpots(): void
    {
        $result = $this->makeService($this->slotWithSpots(3))->joinWaitlist(1, 1, ['guests' => 2]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('plazas disponibles', $result->error);
    }

    public function testJoinWaitlistFailsWhenUserAlreadyInWaitlist(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(true);

        $result = $this->makeService($this->slotWithSpots(0))->joinWaitlist(1, 1, []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('lista de espera', $result->error);
    }

    public function testJoinWaitlistFailsWhenGuestCountExceedsMax(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(false);

        $result = $this->makeService($this->slotWithSpots(0))->joinWaitlist(1, 1, ['guest_count' => 15]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('comensales', $result->error);
    }

    public function testJoinWaitlistFailsWhenGuestCountIsZero(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(false);

        $result = $this->makeService($this->slotWithSpots(0))->joinWaitlist(1, 1, ['guest_count' => 0]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('comensales', $result->error);
    }

    public function testJoinWaitlistFailsWhenGuestCountExceedsSettingsMax(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(false);

        $settingsStub = $this->createStub(SettingsServiceInterface::class);
        $settingsStub->method('get')->willReturnMap([['max_guests_per_reservation', '10', '5']]);

        $result = $this->makeService($this->slotWithSpots(0), $settingsStub)->joinWaitlist(1, 1, ['guest_count' => 6]);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_guest_count', $result->code);
    }

    public function testPromoteNextReturnsOkWithPromotedFalseWhenWaitlistEmpty(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('getNextInLine')->willReturn([]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('commit')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['promoted']);
    }

    public function testConfirmPromotionFailsWhenTokenNotFound(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('findByToken')->willReturn(null);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('rollBack')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->confirmPromotion('invalid-token');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Token', $result->error);
    }

    public function testConfirmPromotionFailsWhenStatusIsNotNotified(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('findByToken')->willReturn(new WaitlistEntryDTO(
            id: 1,
            token: 'some-token',
            status: 'waiting',
            position: 1,
            time_slot_id: 5,
            user_id: 2,
            slot_date: '',
            slot_time: '',
            cafe_name: '',
            guest_count: 1,
            contact_email: '',
            expires_at: null,
            special_requests: null,
        ));

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('rollBack')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->confirmPromotion('some-token');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('procesada', $result->error);
    }

    public function testExpireTokensReturnsOkWithExpiredCount(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('expireTokens')->willReturn(3);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->expireTokens();

        $this->assertTrue($result->ok);
        $this->assertSame(3, $result->data['expired_count']);
    }

    public function testExpireTokensReturnsZeroWhenNoneExpired(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('expireTokens')->willReturn(0);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->expireTokens();

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['expired_count']);
    }

    public function testGetPositionReturnsPositionData(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('getPosition')->willReturn(2);
        $waitlistRepoStub->method('countByTimeSlotAndStatus')->willReturn(5);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->getPosition(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->data['position']);
        $this->assertSame(5, $result->data['total_waiting']);
    }

    public function testGetPositionReturnsNullPositionWhenNotInWaitlist(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('getPosition')->willReturn(null);
        $waitlistRepoStub->method('countByTimeSlotAndStatus')->willReturn(0);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->getPosition(1, 1);

        $this->assertTrue($result->ok);
        $this->assertNull($result->data['position']);
    }

    public function testCancelWaitlistFailsWhenEntryNotFound(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('findByIdAndUser')->willReturn(null);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('rollBack')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->cancelWaitlist(999, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrada', $result->error);
    }

    public function testCancelWaitlistFailsWhenStatusIsNotCancellable(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('findByIdAndUser')->willReturn([
            'id' => 1,
            'status' => 'confirmed',
            'time_slot_id' => 5,
            'position' => 1,
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('rollBack')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->cancelWaitlist(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('cancelar', $result->error);
    }

    public function testCancelWaitlistSucceedsWhenWaiting(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('findByIdAndUser')->willReturn([
            'id' => 1,
            'status' => 'waiting',
            'time_slot_id' => 5,
            'position' => 2,
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('commit')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->cancelWaitlist(1, 1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cancelled']);
    }

    public function testJoinWaitlistSucceedsWhenAllValid(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(false);
        $this->waitlistRepoStub->method('create')->willReturn(10);
        $this->waitlistRepoStub->method('getPosition')->willReturn(1);

        $result = $this->makeService($this->slotWithSpots(0))->joinWaitlist(1, 1, [
            'guest_count' => 2,
            'email' => 'test@example.com',
            'user_name' => 'Test User',
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame(10, $result->data['waitlist_id']);
        $this->assertSame(1, $result->data['position']);
    }

    public function testJoinWaitlistFailsWhenRepoReturnsZero(): void
    {
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $this->waitlistRepoStub->method('userInWaitlist')->willReturn(false);
        $this->waitlistRepoStub->method('create')->willReturn(0);

        $result = $this->makeService($this->slotWithSpots(0))->joinWaitlist(1, 1, [
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
    }

    public function testPromoteNextSuccessfullyPromotesFirstInLine(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('getNextInLine')->willReturn([
            'id' => 5,
            'user_id' => 10,
            'token' => 'abc123',
            'contact_email' => 'user@example.com',
            'guest_count' => 2,
            'response_timeout_minutes' => 15,
        ]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('commit')->willReturn(true);

        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        $service = new WaitlistService(
            $pdoStub,
            $this->emailServiceStub,
            $waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
        );

        $result = $service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['promoted']);
        $this->assertSame(5, $result->data['waitlist_id']);
    }

    public function testGetUserHistoryReturnsOkWithEntries(): void
    {
        $entries = [
            ['id' => 1, 'status' => 'expired'],
            ['id' => 2, 'status' => 'cancelled'],
        ];
        $this->waitlistRepoStub->method('getUserHistory')->willReturn($entries);
        $service = $this->makeService();

        $result = $service->getUserHistory(42, 10);

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->data['count']);
        $this->assertSame($entries, $result->data['entries']);
    }

    public function testGetWaitlistStatusFailsWhenTokenNotFound(): void
    {
        $this->waitlistRepoStub->method('findByToken')->willReturn(null);
        $service = $this->makeService();

        $result = $service->getWaitlistStatus('nonexistent-token');

        $this->assertFalse($result->ok);
    }

    public function testGetWaitlistStatusFailsWhenSlotNotFound(): void
    {
        $entry = new WaitlistEntryDTO(
            id: 1,
            token: 'tok',
            status: 'waiting',
            position: 1,
            time_slot_id: 99,
            user_id: 5,
            slot_date: '2025-12-01',
            slot_time: '10:00:00',
            cafe_name: 'Café Test',
            guest_count: 2,
            contact_email: 'a@b.com',
            expires_at: null,
            special_requests: null,
        );
        $this->waitlistRepoStub->method('findByToken')->willReturn($entry);
        // makeService() returns null for findById by default
        $service = $this->makeService(null);

        $result = $service->getWaitlistStatus('tok');

        $this->assertFalse($result->ok);
    }

    public function testGetUserWaitlistsActiveOnlyDelegatesToFindActive(): void
    {
        $this->waitlistRepoStub->method('findActiveByUserId')->willReturn([
            ['id' => 1, 'status' => 'waiting'],
        ]);
        $service = $this->makeService();

        $result = $service->getUserWaitlists(7, true);

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->data['count']);
    }

    public function testGetUserWaitlistsAllHistoryDelegatesToGetUserHistory(): void
    {
        $this->waitlistRepoStub->method('getUserHistory')->willReturn([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);
        $service = $this->makeService();

        $result = $service->getUserWaitlists(7, false);

        $this->assertTrue($result->ok);
        $this->assertSame(3, $result->data['count']);
    }
}
