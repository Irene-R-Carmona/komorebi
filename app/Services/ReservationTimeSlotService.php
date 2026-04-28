<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Queue;
use App\Core\Result;
use App\Core\TransactionalService;
use App\Core\WideEvent;
use App\Domain\DTO\ReservationDTO;
use App\Jobs\WaitlistPromotionJob;
use App\Models\Waitlist;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\Contracts\ReservationTimeSlotServiceInterface;
use Override;
use PDO;

/**
 * Servicio de integración entre Reservations y TimeSlots
 * Coordina la gestión atómica de capacidad y promoción de waitlist
 */
final class ReservationTimeSlotService extends TransactionalService implements ReservationTimeSlotServiceInterface
{
    private ReservationRepositoryInterface $reservation;
    private TimeSlotRepositoryInterface $timeSlot;
    private WaitlistRepositoryInterface $waitlist;

    public function __construct(
        PDO $db,
        ReservationRepositoryInterface $reservation,
        TimeSlotRepositoryInterface $timeSlot,
        WaitlistRepositoryInterface $waitlist
    ) {
        parent::__construct($db);
        $this->reservation = $reservation;
        $this->timeSlot = $timeSlot;
        $this->waitlist = $waitlist;
    }

    /**
     * Crear reserva con slot de tiempo (atómico)
     *
     * @param array{
     *   user_id: int,
     *   cafe_id: int,
     *   pass_product_id: int,
     *   pass_name: string,
     *   pass_unit_price: int,
     *   pass_duration_minutes: int,
     *   reservation_date: string,
     *   reservation_time: string,
     *   guest_count: int,
     *   contact_name?: string,
     *   contact_email?: string,
     *   contact_phone?: string,
     *   special_requests?: string
     * } $data
     * @return Result
     */
    #[Override]
    public function createReservationWithSlot(array $data): Result
    {
        return $this->transact(function () use ($data): Result {
            // 1. Validar datos de entrada mínimos y castear
            $cafeId = isset($data['cafe_id']) ? (int) $data['cafe_id'] : 0;
            $reservationDate = isset($data['reservation_date']) ? (string) $data['reservation_date'] : '';
            $reservationTime = isset($data['reservation_time']) ? (string) $data['reservation_time'] : '';
            $guestCount = isset($data['guest_count']) ? (int) $data['guest_count'] : 1;

            if ($cafeId <= 0 || $reservationDate === '' || $reservationTime === '') {
                return Result::fail('Datos de reserva incompletos.');
            }

            // 1. Buscar slot disponible
            $slots = $this->timeSlot->findAvailableByDateFiltered($reservationDate, $cafeId, $guestCount);

            if (empty($slots)) {
                return Result::fail('No hay slots disponibles para la fecha/hora seleccionada');
            }

            $slot = $slots[0]; // Primer slot disponible

            // 2. Reservar spots (atómico con lock)
            if (!$this->timeSlot->reserveSpots((int) $slot['id'], $guestCount)) {
                return Result::fail('No se pudo reservar el slot seleccionado');
            }

            // 3. Crear reserva
            $data['time_slot_id'] = (int) $slot['id'];
            $data['status'] = 'confirmed'; // Auto-confirmar si hay slot
            $data['guests'] = $guestCount; // Mapear guest_count -> guests para ReservationRepository::create()

            $reservationId = $this->reservation->create($data);
            if ($reservationId <= 0) {
                $this->timeSlot->releaseSpots((int) $slot['id'], $guestCount);
                return Result::fail('Error al crear la reserva');
            }

            return Result::ok([
                'reservation_id' => $reservationId,
                'time_slot_id' => (int) $slot['id'],
                'status' => 'confirmed',
                'message' => 'Reserva creada y confirmada exitosamente',
            ]);
        });
    }

    /**
     * Cancelar reserva y liberar slot + promover waitlist
     *
     * @param integer $reservationId
     * @return Result
     */
    #[Override]
    public function cancelReservationAndPromote(int $reservationId): Result
    {
        return $this->transact(function () use ($reservationId): Result {
            // 1. Validar y obtener reserva
            $result = $this->validateAndFetchReservation($reservationId);
            if (!$result->ok) {
                return $result;
            }

            $reservationData = $result->data;

            // 2. Cancelar reserva
            if (!$this->reservation->updateStatus($reservationId, 'cancelled')) {
                return Result::fail('No se pudo cancelar la reserva');
            }

            // 3. Liberar slots y promover waitlist si aplica
            $promotedCount = $this->releaseSlotsAndPromote($reservationData);

            return Result::ok([
                'reservation_id' => $reservationId,
                'promoted_users' => $promotedCount,
                'message' => 'Reserva cancelada exitosamente',
            ]);
        });
    }

    /**
     * Valida y obtiene datos de reserva
     */
    private function validateAndFetchReservation(int $reservationId): Result
    {
        $dto = $this->reservation->findById($reservationId);

        if ($dto === null) {
            return Result::fail('Reserva no encontrada');
        }

        return Result::ok($dto);
    }

    /**
     * Libera spots y promueve siguiente en waitlist
     */
    private function releaseSlotsAndPromote(ReservationDTO $reservationData): int
    {
        if ($reservationData->time_slot_id === null) {
            return 0;
        }

        $timeSlotId = $reservationData->time_slot_id;
        $guestCount = $reservationData->guest_count;

        // Liberar spots
        if (!$this->timeSlot->releaseSpots($timeSlotId, $guestCount)) {
            return 0;
        }

        // Promover siguiente en waitlist
        return $this->promoteNextInWaitlist($timeSlotId);
    }

    /**
     * Promueve siguiente persona en waitlist
     */
    private function promoteNextInWaitlist(int $timeSlotId): int
    {
        $waitlistEntry = $this->waitlist->getNextInLine($timeSlotId);
        if ($waitlistEntry === null || !isset($waitlistEntry['id'])) {
            return 0;
        }

        if ($this->waitlist->updateStatus((int) $waitlistEntry['id'], Waitlist::STATUS_NOTIFIED)) {
            Queue::push(WaitlistPromotionJob::class, [
                'waitlist_entry_id' => (int) $waitlistEntry['id'],
                '_correlation_id' => WideEvent::get('request_id') ?? '',
            ]);

            return 1;
        }

        return 0;
    }

    /**
     * Añadir usuario a waitlist
     *
     * @param array{
     *   user_id: int,
     *   time_slot_id: int,
     *   guest_count: int,
     *   contact_email: string,
     *   contact_phone?: string,
     *   notes?: string
     * } $data
     * @return Result
     */
    #[Override]
    public function addToWaitlist(array $data): Result
    {
        $id = $this->waitlist->create($data);

        return $id > 0
            ? Result::ok(['waitlist_id' => $id])
            : Result::fail('Error al añadir a lista de espera');
    }

    /**
     * Confirmar entrada de waitlist con token
     *
     * @param string $token
     * @return Result
     */
    #[Override]
    public function confirmWaitlistEntry(string $token): Result
    {
        return $this->transact(function () use ($token): Result {
            // 1. Buscar entrada por token
            $entry = $this->waitlist->findByToken($token);
            if ($entry === null) {
                return Result::fail('Token no válido o expirado');
            }

            if ($entry->status !== Waitlist::STATUS_NOTIFIED) {
                return Result::fail('Esta promoción ya fue procesada o expiró');
            }

            // 2. Verificar que el slot sigue disponible
            $capacity = $this->timeSlot->getAvailableCapacity($entry->time_slot_id);
            if ($capacity < $entry->guest_count) {
                return Result::fail('El slot ya no tiene capacidad disponible');
            }

            // 3. Confirmar entrada
            if (!$this->waitlist->updateStatus($entry->id, Waitlist::STATUS_CONFIRMED)) {
                return Result::fail('Error al confirmar la entrada en lista de espera');
            }

            return Result::ok([
                'waitlist_id' => $entry->id,
                'time_slot_id' => $entry->time_slot_id,
                'guest_count' => $entry->guest_count,
                'message' => 'Confirmación exitosa. Proceda a crear su reserva.',
            ]);
        });
    }
}
