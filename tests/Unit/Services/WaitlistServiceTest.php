<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios de WaitlistService con stubs de PDO y WaitlistRepositoryInterface.
 *
 * ¿Qué me quieres demostrar?
 * Que las validaciones y flujos de control del servicio funcionan correctamente
 * sin necesitar una base de datos real, inyectando dependencias stubbed.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si joinWaitlist deja de validar la existencia del slot o las plazas disponibles.
 * - Si confirmPromotion deja de validar tokens nulos o expirados.
 * - Si getPosition deja de retornar Result::ok() en todos los casos.
 * - Si cancelWaitlist deja de fallar cuando la entrada no existe.
 * - Si getUserWaitlists deja de retornar Result::ok() con una clave 'waitlists'.
 * - Si getWaitlistStatus deja de fallar con tokens nulos.
 */

namespace Tests\Unit\Services;

use App\Core\Result;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\WaitlistService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WaitlistService::class)]
#[UsesClass(Result::class)]
#[UsesClass(Reservation::class)]
#[UsesClass(TimeSlot::class)]
#[Group('unit')]
final class WaitlistServiceTest extends TestCase
{
    private WaitlistService $service;
    /** @var \PHPUnit\Framework\MockObject\Stub&PDO */
    private PDO $dbMock;
    /** @var \PHPUnit\Framework\MockObject\Stub&WaitlistRepositoryInterface */
    private WaitlistRepositoryInterface $waitlistMock;
    /** @var \PHPUnit\Framework\MockObject\Stub&EmailServiceInterface */
    private EmailServiceInterface $emailServiceStub;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(PDO::class);
        $this->waitlistMock = $this->createStub(WaitlistRepositoryInterface::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);

        // Ensure prepare() always returns a usable PDOStatement stub by default
        $stmtDefault = $this->createStub(PDOStatement::class);
        $this->dbMock->method('prepare')->willReturn($stmtDefault);

        $this->service = new WaitlistService($this->dbMock, $this->emailServiceStub, $this->waitlistMock, new TimeSlot($this->dbMock), new Reservation($this->dbMock));
    }

    // ─────────────────────────────────────────────────────────────
    // joinWaitlist() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('joinWaitlist falla cuando el time slot no existe')]
    public function testJoinWaitlistFailsWhenTimeSlotNotFound(): void
    {
        // Default PDOStatement::fetch() returns null → TimeSlot::findById returns Result::ok(null)
        // WaitlistService checks !is_array(null) → Result::fail('Time slot no encontrado')
        $result = $this->service->joinWaitlist(99999, 1, [
            'email' => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', $result->error);
    }

    #[TestDox('joinWaitlist falla cuando el time slot tiene plazas disponibles')]
    public function testJoinWaitlistFailsWhenSlotsAvailable(): void
    {
        $slot = [
            'id' => 1,
            'available_spots' => 5,
            'cafe_id' => 1,
            'slot_date' => '2030-01-01',
            'slot_time' => '10:00:00',
        ];

        // Use a fresh PDO stub so fetch() returns the slot (can't reconfigure an already-configured stub)
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($slot);

        $dbMock = $this->createStub(PDO::class);
        $dbMock->method('prepare')->willReturn($stmt);

        $service = new WaitlistService($dbMock, $this->createStub(EmailServiceInterface::class), $this->waitlistMock, new TimeSlot($dbMock), new Reservation($dbMock));
        $result = $service->joinWaitlist(1, 1, [
            'email' => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('plazas disponibles', $result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // confirmPromotion() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('confirmPromotion falla con token inválido')]
    public function testConfirmPromotionFailsWithInvalidToken(): void
    {
        // waitlistMock::findByToken() returns null by default → 'Token de waitlist no válido'
        $result = $this->service->confirmPromotion('token-invalido', []);

        $this->assertFalse($result->ok);
    }

    #[TestDox('confirmPromotion falla con token expirado')]
    public function testConfirmPromotionFailsWithExpiredToken(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id' => 1,
            'status' => 'notified',
            'expires_at' => '2000-01-01 00:00:00',
            'time_slot_id' => 1,
            'position' => 1,
            'user_id' => 1,
            'guest_count' => 1,
        ]);
        $this->waitlistMock->method('updateStatus')->willReturn(true);
        $this->waitlistMock->method('reorderPositions')->willReturn(true);

        $result = $this->service->confirmPromotion('token-expirado', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expirado', $result->error);
    }

    // ─────────────────────────────────────────────────────────────
    // getPosition() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getPosition siempre retorna Result::ok con posición null cuando no está en lista')]
    public function testGetPositionReturnsResultWithPosition(): void
    {
        // waitlistRepository::getPosition() returns null (default stub → not in waitlist)
        // PDO COUNT query → fetchColumn() returns null → (int) null = 0
        $result = $this->service->getPosition(99999, 1);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok); // getPosition ALWAYS returns ok
        $this->assertNull(($result->data ?? [])['position'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────
    // cancelWaitlist() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('cancelWaitlist falla cuando la entrada no existe')]
    public function testCancelWaitlistFailsWhenNotFound(): void
    {
        // PDOStatement::fetch() returns null (default) → 'Entrada de waitlist no encontrada o no autorizada'
        $result = $this->service->cancelWaitlist(99999, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrada', $result->error);
    }

    #[TestDox('cancelWaitlist falla cuando el usuario no es propietario')]
    public function testCancelWaitlistFailsWhenNotOwner(): void
    {
        // The query uses WHERE id = ? AND user_id = ?; wrong user returns no row (null fetch)
        $result = $this->service->cancelWaitlist(1, 9999);

        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // getUserWaitlists() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getUserWaitlists retorna Result::ok con clave waitlists')]
    public function testGetUserWaitlistsReturnsResultWithArray(): void
    {
        // findActiveByUserId returns [] by default (stub returns empty array for array return type)
        $result = $this->service->getUserWaitlists(99999);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
        $this->assertIsArray(($result->data ?? [])['waitlists'] ?? null);
    }

    // ─────────────────────────────────────────────────────────────
    // getWaitlistStatus() — Validaciones
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getWaitlistStatus falla con token inválido')]
    public function testGetWaitlistStatusFailsWithInvalidToken(): void
    {
        // waitlistMock::findByToken() returns null by default
        $result = $this->service->getWaitlistStatus('token-invalido');

        $this->assertFalse($result->ok);
    }
}
