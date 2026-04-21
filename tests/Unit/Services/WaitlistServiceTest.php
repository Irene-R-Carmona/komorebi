<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * Tests unitarios exhaustivos de WaitlistService cubriendo todos los métodos públicos:
 * joinWaitlist, confirmPromotion, getPosition, cancelWaitlist, getUserWaitlists,
 * getWaitlistStatus, getUserHistory, promoteNext y expireTokens.
 *
 * ¿Qué me quieres demostrar?
 * Que las validaciones y los flujos de control del servicio funcionan correctamente
 * sin una base de datos real, inyectando dependencias stubbed para PDO y repositorios.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * - Si cualquier guard clause deja de validar (slot no existe, plazas disponibles,
 *   usuario ya en lista, guest_count inválido, token inválido, estado incorrecto,
 *   token expirado, slot sin plazas, reserva fallida).
 * - Si los paths de éxito dejan de retornar Result::ok con las claves esperadas.
 * - Si getPosition o getUserWaitlists dejan de retornar siempre Result::ok.
 * - Si cancelWaitlist o confirmPromotion dejan de validar ownership o estado.
 * - Si promoteNext deja de manejar correctamente waitlist vacía o datos inválidos.
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
        $this->dbMock           = $this->createStub(PDO::class);
        $this->waitlistMock     = $this->createStub(WaitlistRepositoryInterface::class);
        $this->emailServiceStub = $this->createStub(EmailServiceInterface::class);

        // Default stmt: fetch() returns null → TimeSlot::findById → Result::ok(null)
        $stmtDefault = $this->createStub(PDOStatement::class);
        $this->dbMock->method('prepare')->willReturn($stmtDefault);

        $this->service = new WaitlistService(
            $this->dbMock,
            $this->emailServiceStub,
            $this->waitlistMock,
            new TimeSlot($this->dbMock),
            new Reservation($this->dbMock),
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Crea un PDO stub cuyo PDOStatement devuelve $slotData en fetch().
     *
     * @param array<string, mixed> $slotData
     */
    private function makeDbStubWithSlot(array $slotData): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($slotData);

        $db = $this->createStub(PDO::class);
        $db->method('prepare')->willReturn($stmt);
        $db->method('lastInsertId')->willReturn('1');

        return $db;
    }

    /**
     * Crea un WaitlistService con PDO stub dado y repo stub opcional.
     */
    private function makeService(PDO $db, ?WaitlistRepositoryInterface $repo = null): WaitlistService
    {
        return new WaitlistService(
            $db,
            $this->createStub(EmailServiceInterface::class),
            $repo ?? $this->createStub(WaitlistRepositoryInterface::class),
            new TimeSlot($db),
            new Reservation($db),
        );
    }

    /** @return array<string, mixed> Slot sin plazas disponibles */
    private function fullSlot(): array
    {
        return [
            'id'              => 1,
            'cafe_id'         => 1,
            'slot_date'       => '2030-06-01',
            'slot_time'       => '10:00:00',
            'available_spots' => 0,
            'total_capacity'  => 10,
        ];
    }

    /** @return array<string, mixed> Slot con plazas disponibles */
    private function availableSlot(): array
    {
        return [
            'id'              => 1,
            'cafe_id'         => 1,
            'slot_date'       => '2030-06-01',
            'slot_time'       => '10:00:00',
            'available_spots' => 5,
        ];
    }

    /** @return array<string, mixed> Entrada de waitlist en estado 'notified', no expirada */
    private function notifiedWaitlistEntry(): array
    {
        return [
            'id'               => 10,
            'user_id'          => 1,
            'time_slot_id'     => 1,
            'status'           => 'notified',
            'expires_at'       => '2099-01-01 00:00:00',
            'position'         => 1,
            'guest_count'      => 2,
            'special_requests' => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // joinWaitlist()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('joinWaitlist falla cuando el time slot no existe')]
    public function testJoinWaitlistFailsWhenTimeSlotNotFound(): void
    {
        // PDO stmt default: fetch() → null → Result::ok(null) → !is_array(null) → fail
        $result = $this->service->joinWaitlist(99999, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrado', (string) $result->error);
    }

    #[TestDox('joinWaitlist falla cuando el time slot todavía tiene plazas disponibles')]
    public function testJoinWaitlistFailsWhenTimeSlotsAvailable(): void
    {
        $db      = $this->makeDbStubWithSlot($this->availableSlot());
        $service = $this->makeService($db);

        $result = $service->joinWaitlist(1, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('plazas disponibles', (string) $result->error);
    }

    #[TestDox('joinWaitlist falla cuando el usuario ya está en la lista de espera para ese slot')]
    public function testJoinWaitlistFailsWhenUserAlreadyInWaitlist(): void
    {
        $db   = $this->makeDbStubWithSlot($this->fullSlot());
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('userInWaitlist')->willReturn(true);

        $result = $this->makeService($db, $repo)->joinWaitlist(1, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Ya estás en la lista', (string) $result->error);
    }

    #[TestDox('joinWaitlist falla cuando guest_count es 0 (por debajo del mínimo de 1)')]
    public function testJoinWaitlistFailsWhenGuestCountIsBelowMinimum(): void
    {
        $db = $this->makeDbStubWithSlot($this->fullSlot());

        $result = $this->makeService($db)->joinWaitlist(1, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 0,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_guest_count', $result->code);
    }

    #[TestDox('joinWaitlist falla cuando guest_count supera el máximo de 10')]
    public function testJoinWaitlistFailsWhenGuestCountExceedsMaximum(): void
    {
        $db = $this->makeDbStubWithSlot($this->fullSlot());

        $result = $this->makeService($db)->joinWaitlist(1, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 11,
        ]);

        $this->assertFalse($result->ok);
        $this->assertSame('invalid_guest_count', $result->code);
    }

    #[TestDox('joinWaitlist falla cuando el repositorio devuelve 0 al intentar crear la entrada')]
    public function testJoinWaitlistFailsWhenRepositoryCreateReturnsZero(): void
    {
        $db   = $this->makeDbStubWithSlot($this->fullSlot());
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        // userInWaitlist → false (bool stub default)
        // create → 0 (int stub default)

        $result = $this->makeService($db, $repo)->joinWaitlist(1, 1, [
            'email'       => 'test@example.com',
            'guest_count' => 2,
        ]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Error al añadir', (string) $result->error);
    }

    #[TestDox('joinWaitlist retorna ok con waitlist_id, token, posición y expires_at cuando el slot está lleno')]
    public function testJoinWaitlistSuccessReturnsWaitlistData(): void
    {
        $db   = $this->makeDbStubWithSlot($this->fullSlot());
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('userInWaitlist')->willReturn(false);
        $repo->method('create')->willReturn(7);
        $repo->method('getPosition')->willReturn(3);

        $result = $this->makeService($db, $repo)->joinWaitlist(1, 5, [
            'email'       => 'user@example.com',
            'guest_count' => 2,
            'user_name'   => 'Ana García',
        ]);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data);
        $this->assertSame(7, $result->data['waitlist_id']);
        $this->assertSame(3, $result->data['position']);
        $this->assertArrayHasKey('token', $result->data);
        $this->assertArrayHasKey('expires_at', $result->data);
    }

    #[TestDox('joinWaitlist retorna ok aunque el envío de email lance una excepción (se silencia)')]
    public function testJoinWaitlistSuccessSilencesEmailErrors(): void
    {
        $emailStub = $this->createStub(EmailServiceInterface::class);
        $emailStub->method('sendWaitlistConfirmation')->willThrowException(
            new \RuntimeException('SMTP down')
        );

        $db   = $this->makeDbStubWithSlot($this->fullSlot());
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('userInWaitlist')->willReturn(false);
        $repo->method('create')->willReturn(3);
        $repo->method('getPosition')->willReturn(1);

        $service = new WaitlistService(
            $db,
            $emailStub,
            $repo,
            new TimeSlot($db),
            new Reservation($db),
        );

        $result = $service->joinWaitlist(1, 2, [
            'email'       => 'user@example.com',
            'guest_count' => 1,
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame(3, $result->data['waitlist_id']);
    }

    // ─────────────────────────────────────────────────────────────
    // confirmPromotion()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('confirmPromotion falla cuando el token no existe en la base de datos')]
    public function testConfirmPromotionFailsWithInvalidToken(): void
    {
        // findByToken → null (stub default)
        $result = $this->service->confirmPromotion('token-invalido', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Token', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando el estado de la entrada no es notified')]
    public function testConfirmPromotionFailsWhenStatusIsNotNotified(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'           => 1,
            'status'       => 'waiting',
            'expires_at'   => '2099-01-01 00:00:00',
            'time_slot_id' => 1,
            'position'     => 1,
            'user_id'      => 1,
            'guest_count'  => 1,
        ]);

        $result = $this->service->confirmPromotion('some-token', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('procesada', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando la entrada ya está en estado confirmed (no notified)')]
    public function testConfirmPromotionFailsWhenStatusIsAlreadyConfirmed(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'           => 2,
            'status'       => 'confirmed',
            'expires_at'   => '2099-01-01 00:00:00',
            'time_slot_id' => 1,
            'position'     => 1,
            'user_id'      => 1,
            'guest_count'  => 1,
        ]);

        $result = $this->service->confirmPromotion('confirmed-token', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('procesada', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando el token ha expirado')]
    public function testConfirmPromotionFailsWithExpiredToken(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'           => 1,
            'status'       => 'notified',
            'expires_at'   => '2000-01-01 00:00:00',
            'time_slot_id' => 1,
            'position'     => 1,
            'user_id'      => 1,
            'guest_count'  => 1,
        ]);
        $this->waitlistMock->method('updateStatus')->willReturn(true);
        $this->waitlistMock->method('reorderPositions')->willReturn(true);

        $result = $this->service->confirmPromotion('token-expirado', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('expirado', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando findByIdForUpdate no encuentra el slot')]
    public function testConfirmPromotionFailsWhenSlotNotFound(): void
    {
        // Default DB stub: fetch() → null → findByIdForUpdate → Result::fail('Slot no encontrado')
        $this->waitlistMock->method('findByToken')->willReturn($this->notifiedWaitlistEntry());

        $result = $this->service->confirmPromotion('valid-token', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no disponible', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando el slot no tiene plazas disponibles en el momento de confirmar')]
    public function testConfirmPromotionFailsWhenSlotHasNoAvailableSpots(): void
    {
        $db   = $this->makeDbStubWithSlot([
            'id'              => 1,
            'cafe_id'         => 1,
            'available_spots' => 0,
            'slot_date'       => '2030-06-01',
            'slot_time'       => '10:00:00',
        ]);
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('findByToken')->willReturn($this->notifiedWaitlistEntry());

        $result = $this->makeService($db, $repo)->confirmPromotion('valid-token', []);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('plazas disponibles', (string) $result->error);
    }

    #[TestDox('confirmPromotion falla cuando Reservation::create rechaza los datos (fecha vacía)')]
    public function testConfirmPromotionFailsWhenReservationCreateFails(): void
    {
        // Slot con plazas pero slot_date='' → validateRequired en Reservation::create lanza excepción
        $db   = $this->makeDbStubWithSlot([
            'id'              => 1,
            'cafe_id'         => 1,
            'available_spots' => 2,
            'slot_date'       => '',
            'slot_time'       => '',
        ]);
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('findByToken')->willReturn($this->notifiedWaitlistEntry());

        $result = $this->makeService($db, $repo)->confirmPromotion('valid-token', []);

        $this->assertFalse($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // getPosition()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getPosition retorna ok con position=null cuando el usuario no está en la lista')]
    public function testGetPositionReturnsNullWhenUserNotInWaitlist(): void
    {
        // getPosition → null (?int stub default), countByTimeSlotAndStatus → 0 (int stub default)
        $result = $this->service->getPosition(1, 99999);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
        $this->assertNull($result->data['position']);
    }

    #[TestDox('getPosition retorna ok con la posición cuando el usuario está en la lista')]
    public function testGetPositionReturnsPositionWhenUserIsInWaitlist(): void
    {
        $this->waitlistMock->method('getPosition')->willReturn(3);
        $this->waitlistMock->method('countByTimeSlotAndStatus')->willReturn(5);

        $result = $this->service->getPosition(1, 1);

        $this->assertTrue($result->ok);
        $this->assertSame(3, $result->data['position']);
        $this->assertSame(5, $result->data['total_waiting']);
    }

    #[TestDox('getPosition incluye total_waiting y mensaje descriptivo aunque el usuario no esté en la lista')]
    public function testGetPositionReturnsTotalWaitingCountWithMessage(): void
    {
        $this->waitlistMock->method('getPosition')->willReturn(null);
        $this->waitlistMock->method('countByTimeSlotAndStatus')->willReturn(4);

        $result = $this->service->getPosition(1, 1);

        $this->assertTrue($result->ok);
        $this->assertNull($result->data['position']);
        $this->assertSame(4, $result->data['total_waiting']);
        $this->assertArrayHasKey('message', $result->data);
        $this->assertStringContainsString('No estás', (string) $result->data['message']);
    }

    #[TestDox('getPosition siempre retorna Result::ok (nunca falla)')]
    public function testGetPositionAlwaysReturnsOk(): void
    {
        $result = $this->service->getPosition(0, 0);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
    }

    // ─────────────────────────────────────────────────────────────
    // cancelWaitlist()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('cancelWaitlist falla cuando la entrada de waitlist no existe')]
    public function testCancelWaitlistFailsWhenEntryNotFound(): void
    {
        // findByIdAndUser → null (stub default)
        $result = $this->service->cancelWaitlist(99999, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('no encontrada', (string) $result->error);
    }

    #[TestDox('cancelWaitlist falla cuando el user_id no coincide con el propietario')]
    public function testCancelWaitlistFailsWhenUserDoesNotOwnEntry(): void
    {
        // WHERE id=1 AND user_id=9999 no devuelve filas → null
        $result = $this->service->cancelWaitlist(1, 9999);

        $this->assertFalse($result->ok);
    }

    #[TestDox('cancelWaitlist falla cuando la entrada está en estado confirmed (no cancelable)')]
    public function testCancelWaitlistFailsWhenStatusIsNotCancellable(): void
    {
        $this->waitlistMock->method('findByIdAndUser')->willReturn([
            'id'           => 1,
            'user_id'      => 1,
            'status'       => 'confirmed',
            'time_slot_id' => 1,
            'position'     => 1,
        ]);

        $result = $this->service->cancelWaitlist(1, 1);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('estado', (string) $result->error);
    }

    #[TestDox('cancelWaitlist falla cuando la entrada está en estado expired (no cancelable)')]
    public function testCancelWaitlistFailsWhenStatusIsExpired(): void
    {
        $this->waitlistMock->method('findByIdAndUser')->willReturn([
            'id'           => 3,
            'user_id'      => 1,
            'status'       => 'expired',
            'time_slot_id' => 1,
            'position'     => 0,
        ]);

        $result = $this->service->cancelWaitlist(3, 1);

        $this->assertFalse($result->ok);
    }

    #[TestDox('cancelWaitlist retorna ok cuando la entrada está en estado waiting')]
    public function testCancelWaitlistSuccessWhenStatusIsWaiting(): void
    {
        $this->waitlistMock->method('findByIdAndUser')->willReturn([
            'id'           => 1,
            'user_id'      => 1,
            'status'       => 'waiting',
            'time_slot_id' => 1,
            'position'     => 2,
        ]);

        $result = $this->service->cancelWaitlist(1, 1);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cancelled']);
        $this->assertStringContainsString('eliminado', (string) $result->data['message']);
    }

    #[TestDox('cancelWaitlist retorna ok cuando la entrada está en estado notified')]
    public function testCancelWaitlistSuccessWhenStatusIsNotified(): void
    {
        $this->waitlistMock->method('findByIdAndUser')->willReturn([
            'id'           => 5,
            'user_id'      => 3,
            'status'       => 'notified',
            'time_slot_id' => 2,
            'position'     => 1,
        ]);

        $result = $this->service->cancelWaitlist(5, 3);

        $this->assertTrue($result->ok);
        $this->assertTrue($result->data['cancelled']);
    }

    // ─────────────────────────────────────────────────────────────
    // getUserWaitlists()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getUserWaitlists retorna ok con waitlists vacías cuando activeOnly=true y no hay entradas')]
    public function testGetUserWaitlistsActiveOnlyReturnsEmptyList(): void
    {
        // findActiveByUserId → [] (array stub default)
        $result = $this->service->getUserWaitlists(99999);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data['waitlists']);
        $this->assertEmpty($result->data['waitlists']);
        $this->assertSame(0, $result->data['count']);
    }

    #[TestDox('getUserWaitlists retorna ok con las entradas cuando activeOnly=true y hay registros')]
    public function testGetUserWaitlistsActiveOnlyReturnsEntries(): void
    {
        $entries = [
            ['id' => 1, 'status' => 'waiting',  'time_slot_id' => 1],
            ['id' => 2, 'status' => 'notified', 'time_slot_id' => 2],
        ];
        $this->waitlistMock->method('findActiveByUserId')->willReturn($entries);

        $result = $this->service->getUserWaitlists(1);

        $this->assertTrue($result->ok);
        $this->assertCount(2, $result->data['waitlists']);
        $this->assertSame(2, $result->data['count']);
    }

    #[TestDox('getUserWaitlists usa getUserHistory cuando activeOnly=false')]
    public function testGetUserWaitlistsUsesHistoryWhenNotActiveOnly(): void
    {
        $history = [['id' => 3, 'status' => 'cancelled']];
        $this->waitlistMock->method('getUserHistory')->willReturn($history);

        $result = $this->service->getUserWaitlists(1, false);

        $this->assertTrue($result->ok);
        $this->assertCount(1, $result->data['waitlists']);
        $this->assertSame(1, $result->data['count']);
    }

    #[TestDox('getUserWaitlists siempre retorna Result::ok (nunca falla)')]
    public function testGetUserWaitlistsAlwaysReturnsOk(): void
    {
        $result = $this->service->getUserWaitlists(0);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->ok);
        $this->assertArrayHasKey('waitlists', (array) $result->data);
    }

    // ─────────────────────────────────────────────────────────────
    // getWaitlistStatus()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getWaitlistStatus falla cuando el token no existe')]
    public function testGetWaitlistStatusFailsWithInvalidToken(): void
    {
        // findByToken → null (stub default)
        $result = $this->service->getWaitlistStatus('token-invalido');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Token', (string) $result->error);
    }

    #[TestDox('getWaitlistStatus falla cuando el time_slot_id de la entrada es 0')]
    public function testGetWaitlistStatusFailsWhenTimeSlotIdIsZero(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'           => 1,
            'status'       => 'waiting',
            'time_slot_id' => 0,
            'position'     => 1,
            'guest_count'  => 2,
            'expires_at'   => null,
        ]);

        $result = $this->service->getWaitlistStatus('some-token');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Time slot', (string) $result->error);
    }

    #[TestDox('getWaitlistStatus retorna ok con la información de la entrada cuando el token es válido')]
    public function testGetWaitlistStatusSuccessReturnsEntryData(): void
    {
        // Default PDO: fetch → null → TimeSlot::findById → Result::ok(null) → slot = []
        // El método continúa y retorna ok con slot vacío
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'               => 5,
            'status'           => 'waiting',
            'time_slot_id'     => 1,
            'position'         => 2,
            'guest_count'      => 3,
            'expires_at'       => '2099-01-01 00:00:00',
            'special_requests' => 'vegetariano',
            'notified_at'      => null,
            'created_at'       => '2025-01-01',
            'user_name'        => 'Carlos',
        ]);

        $result = $this->service->getWaitlistStatus('valid-token');

        $this->assertTrue($result->ok);
        $this->assertSame(5, $result->data['id']);
        $this->assertSame(2, $result->data['position']);
        $this->assertSame('waiting', $result->data['status']);
        $this->assertSame(3, $result->data['guest_count']);
        $this->assertSame(15, $result->data['estimated_wait_minutes']); // (2-1) * 15
        $this->assertArrayHasKey('time_slot', $result->data);
    }

    #[TestDox('getWaitlistStatus incluye datos reales del slot cuando TimeSlot::findById los devuelve')]
    public function testGetWaitlistStatusIncludesSlotDataWhenFound(): void
    {
        $slotData = [
            'id'              => 1,
            'slot_date'       => '2030-06-01',
            'slot_time'       => '10:00:00',
            'cafe_id'         => 2,
            'available_spots' => 0,
        ];
        $db   = $this->makeDbStubWithSlot($slotData);
        $repo = $this->createStub(WaitlistRepositoryInterface::class);
        $repo->method('findByToken')->willReturn([
            'id'           => 5,
            'status'       => 'waiting',
            'time_slot_id' => 1,
            'position'     => 1,
            'guest_count'  => 2,
            'expires_at'   => null,
        ]);

        $result = $this->makeService($db, $repo)->getWaitlistStatus('valid-token');

        $this->assertTrue($result->ok);
        $this->assertSame('2030-06-01', $result->data['time_slot']['date']);
        $this->assertSame('10:00:00', $result->data['time_slot']['time']);
        $this->assertSame(2, $result->data['time_slot']['cafe_id']);
    }

    #[TestDox('getWaitlistStatus calcula estimated_wait_minutes=0 para la posición 1')]
    public function testGetWaitlistStatusCalculatesZeroWaitForFirstPosition(): void
    {
        $this->waitlistMock->method('findByToken')->willReturn([
            'id'           => 1,
            'status'       => 'notified',
            'time_slot_id' => 1,
            'position'     => 1,
            'guest_count'  => 1,
            'expires_at'   => '2099-01-01 00:00:00',
        ]);

        $result = $this->service->getWaitlistStatus('first-token');

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['estimated_wait_minutes']);
    }

    // ─────────────────────────────────────────────────────────────
    // getUserHistory()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('getUserHistory retorna ok con lista vacía cuando no hay historial')]
    public function testGetUserHistoryReturnsEmptyList(): void
    {
        // getUserHistory → [] (array stub default)
        $result = $this->service->getUserHistory(99999);

        $this->assertTrue($result->ok);
        $this->assertIsArray($result->data['entries']);
        $this->assertSame(0, $result->data['count']);
    }

    #[TestDox('getUserHistory retorna ok con las entradas del historial del usuario')]
    public function testGetUserHistoryReturnsEntries(): void
    {
        $history = [
            ['id' => 1, 'status' => 'confirmed',  'time_slot_id' => 1],
            ['id' => 2, 'status' => 'expired',    'time_slot_id' => 2],
            ['id' => 3, 'status' => 'cancelled',  'time_slot_id' => 3],
        ];
        $this->waitlistMock->method('getUserHistory')->willReturn($history);

        $result = $this->service->getUserHistory(1);

        $this->assertTrue($result->ok);
        $this->assertCount(3, $result->data['entries']);
        $this->assertSame(3, $result->data['count']);
    }

    #[TestDox('getUserHistory acepta el parámetro limit y retorna el número correcto de entradas')]
    public function testGetUserHistoryRespectsLimitParameter(): void
    {
        $this->waitlistMock->method('getUserHistory')->willReturn([
            ['id' => 1, 'status' => 'confirmed'],
            ['id' => 2, 'status' => 'confirmed'],
        ]);

        $result = $this->service->getUserHistory(1, 2);

        $this->assertTrue($result->ok);
        $this->assertSame(2, $result->data['count']);
    }

    // ─────────────────────────────────────────────────────────────
    // promoteNext()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('promoteNext retorna ok con promoted=false cuando no hay nadie en la waitlist')]
    public function testPromoteNextReturnsNotPromotedWhenWaitlistIsEmpty(): void
    {
        // getNextInLine → null (stub default ?array)
        $result = $this->service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['promoted']);
        $this->assertStringContainsString('nadie', (string) $result->data['message']);
    }

    #[TestDox('promoteNext retorna ok con promoted=false cuando el array del siguiente está vacío')]
    public function testPromoteNextReturnsNotPromotedWhenNextIsEmptyArray(): void
    {
        $this->waitlistMock->method('getNextInLine')->willReturn([]);

        $result = $this->service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['promoted']);
    }

    #[TestDox('promoteNext retorna ok con promoted=false cuando el siguiente tiene id=0')]
    public function testPromoteNextReturnsNotPromotedWhenNextHasInvalidId(): void
    {
        $this->waitlistMock->method('getNextInLine')->willReturn([
            'id'                       => 0,
            'user_id'                  => 0,
            'token'                    => 'tok',
            'contact_email'            => 'x@x.com',
            'guest_count'              => 1,
            'response_timeout_minutes' => 15,
        ]);

        $result = $this->service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['promoted']);
        $this->assertStringContainsString('inválido', (string) $result->data['message']);
    }

    #[TestDox('promoteNext retorna ok con promoted=false cuando el siguiente tiene user_id=0')]
    public function testPromoteNextReturnsNotPromotedWhenUserIdIsZero(): void
    {
        $this->waitlistMock->method('getNextInLine')->willReturn([
            'id'                       => 5,
            'user_id'                  => 0,
            'token'                    => 'tok',
            'contact_email'            => 'x@x.com',
            'guest_count'              => 1,
            'response_timeout_minutes' => 15,
        ]);

        $result = $this->service->promoteNext(1);

        $this->assertTrue($result->ok);
        $this->assertFalse($result->data['promoted']);
    }

    // ─────────────────────────────────────────────────────────────
    // expireTokens()
    // ─────────────────────────────────────────────────────────────

    #[TestDox('expireTokens retorna ok con expired_count=0 cuando no hay tokens expirados')]
    public function testExpireTokensReturnsZeroWhenNoneExpired(): void
    {
        // expireTokens → 0 (int stub default)
        $result = $this->service->expireTokens();

        $this->assertTrue($result->ok);
        $this->assertSame(0, $result->data['expired_count']);
        $this->assertArrayHasKey('message', $result->data);
    }

    #[TestDox('expireTokens retorna ok con la cantidad de tokens que se expiraron')]
    public function testExpireTokensReturnsCountOfExpiredTokens(): void
    {
        $this->waitlistMock->method('expireTokens')->willReturn(5);

        $result = $this->service->expireTokens();

        $this->assertTrue($result->ok);
        $this->assertSame(5, $result->data['expired_count']);
        $this->assertStringContainsString('5', (string) $result->data['message']);
    }
}
