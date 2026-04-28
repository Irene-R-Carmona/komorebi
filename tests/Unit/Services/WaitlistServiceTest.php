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
        $this->pdoStub          = $this->createStub(PDO::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);
        $this->waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
    }

    private function makeService(?TimeSlotDTO $slot = null): WaitlistService
    {
        $timeSlotRepoStub = $this->createStub(TimeSlotRepositoryInterface::class);
        $timeSlotRepoStub->method('findById')->willReturn($slot);
        $reservationRepoStub = $this->createStub(ReservationRepositoryInterface::class);

        return new WaitlistService(
            $this->pdoStub,
            $this->emailServiceStub,
            $this->waitlistRepoStub,
            $timeSlotRepoStub,
            $reservationRepoStub
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

    public function testPromoteNextReturnsOkWithPromotedFalseWhenWaitlistEmpty(): void
    {
        $waitlistRepoStub = $this->createStub(WaitlistRepositoryInterface::class);
        $waitlistRepoStub->method('getNextInLine')->willReturn([]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('inTransaction')->willReturn(false);
        $pdoStub->method('beginTransaction')->willReturn(true);
        $pdoStub->method('commit')->willReturn(true);

        $timeSlotRepoStub    = $this->createStub(TimeSlotRepositoryInterface::class);
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

        $timeSlotRepoStub    = $this->createStub(TimeSlotRepositoryInterface::class);
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

        $timeSlotRepoStub    = $this->createStub(TimeSlotRepositoryInterface::class);
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
}
