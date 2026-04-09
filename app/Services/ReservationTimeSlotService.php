<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Queue;
use App\Core\Result;
use App\Core\TransactionalService;
use App\Jobs\WaitlistPromotionJob;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\Waitlist;
use PDO;
use PDOException;

/**
 * Servicio de integración entre Reservations y TimeSlots
 * Coordina la gestión atómica de capacidad y promoción de waitlist
 */
final class ReservationTimeSlotService extends TransactionalService
{
    private Reservation $reservation;
    private TimeSlot $timeSlot;
    private Waitlist $waitlist;

    public function __construct(
        PDO $db,
        Reservation $reservation,
        TimeSlot $timeSlot,
        Waitlist $waitlist
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
     *   reservation_date: string,
     *   reservation_time: string,
     *   guest_count: int,
     *   contact_name?: string,
     *   contact_email?: string,
     *   contact_phone?: string,
     *   special_requests?: string,
     *   pass_duration_minutes?: int
     * } $data
     * @return Result{reservation_id: int, time_slot_id: int}
     */
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

            // 1. Buscar slot disponible (usar la API del modelo con parámetros posicionales)
            $slotResult = $this->timeSlot->findAvailable(
                $cafeId,
                $reservationDate,
                $reservationDate,
                $guestCount
            );

            if (!$slotResult->isOk()) {
                return Result::fail('No hay slots disponibles para la fecha/hora seleccionada');
            }

            $slots = [];
            if (is_array($slotResult->data ?? null) && isset($slotResult->data['slots']) && is_array($slotResult->data['slots'])) {
                $slots = $slotResult->data['slots'];
            }
            if (empty($slots)) {
                return Result::fail('No se encontraron slots con capacidad suficiente');
            }

            $slot = $slots[0]; // Primer slot disponible

            // 2. Decrementar spots (atómico con lock)
            $decrementResult = $this->timeSlot->decrementSpots((int) $slot['id'], $guestCount);
            if (!$decrementResult->isOk()) {
                return Result::fail($decrementResult->error);
            }

            // 3. Crear reserva
            $data['time_slot_id'] = (int) $slot['id'];
            $data['status'] = 'confirmed'; // Auto-confirmar si hay slot

            $reservationResult = $this->reservation->create($data);
            if (!$reservationResult->isOk()) {
                return Result::fail($reservationResult->error);
            }

            $reservationId = (int) ($reservationResult->data['reservation_id'] ?? 0);

            return Result::ok([
                'reservation_id' => $reservationId,
                'time_slot_id' => isset($slot['id']) ? (int) $slot['id'] : 0,
                'status' => 'confirmed',
                'message' => 'Reserva creada y confirmada exitosamente',
            ]);
        });
    }

    /**
     * Cancelar reserva y liberar slot + promover waitlist
     *
     * @param integer $reservationId
     * @return Result{promoted_users: int}
     */
    public function cancelReservationAndPromote(int $reservationId): Result
    {
        return $this->transact(function () use ($reservationId): Result {
            // 1. Validar y obtener reserva
            $result = $this->validateAndFetchReservation($reservationId);
            if (!$result->isOk()) {
                return $result;
            }

            $reservationData = $result->data;

            // 2. Cancelar reserva
            $cancelResult = $this->reservation->cancel($reservationId);
            if (!$cancelResult->isOk()) {
                return Result::fail($cancelResult->error);
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
        $reservationData = $this->reservation->findById($reservationId);

        if (!$reservationData) {
            return Result::fail('Reserva no encontrada');
        }

        return Result::ok($reservationData);
    }

    /**
     * Libera spots y promueve siguiente en waitlist
     */
    private function releaseSlotsAndPromote(array $reservationData): int
    {
        if (!isset($reservationData['time_slot_id']) || !$reservationData['time_slot_id']) {
            return 0;
        }

        $timeSlotId = (int) $reservationData['time_slot_id'];
        $guestCount = isset($reservationData['guest_count']) ? (int) $reservationData['guest_count'] : 1;

        // Liberar spots
        $incrementResult = $this->timeSlot->incrementSpots($timeSlotId, $guestCount);
        if (!$incrementResult->isOk()) {
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
        $nextResult = $this->waitlist->getNextInQueue($timeSlotId);
        if (!$nextResult->isOk() || !is_array($nextResult->data ?? null) || !isset($nextResult->data['id'])) {
            return 0;
        }

        $waitlistEntry = $nextResult->data;
        $notifyResult = $this->waitlist->markAsNotified((int) $waitlistEntry['id']);

        if ($notifyResult->isOk()) {
            Queue::push(WaitlistPromotionJob::class, ['waitlist_entry_id' => (int) $waitlistEntry['id']]);
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
     * @return Result{waitlist_id: int, position: int, token: string}
     */
    public function addToWaitlist(array $data): Result
    {
        return $this->waitlist->addToWaitlist($data);
    }

    /**
     * Confirmar entrada de waitlist con token
     *
     * @param string $token
     * @return Result{waitlist_id: int, time_slot_id: int}
     */
    public function confirmWaitlistEntry(string $token): Result
    {
        return $this->transact(function () use ($token): Result {
            // 1. Confirmar por token
            $confirmResult = $this->waitlist->confirmByToken($token);
            if (!$confirmResult->isOk()) {
                return Result::fail($confirmResult->error);
            }

            $waitlistEntry = $confirmResult->data;

            // 2. Verificar que el slot sigue disponible
            $slotResult = $this->timeSlot->hasAvailability(
                (int) $waitlistEntry['time_slot_id'],
                (int) $waitlistEntry['guest_count']
            );

            if (!$slotResult->isOk() || empty($slotResult->data)) {
                return Result::fail('El slot ya no tiene capacidad disponible');
            }

            return Result::ok([
                'waitlist_id' => $waitlistEntry['id'],
                'time_slot_id' => $waitlistEntry['time_slot_id'],
                'guest_count' => $waitlistEntry['guest_count'],
                'message' => 'Confirmación exitosa. Proceda a crear su reserva.',
            ]);
        });
    }
}
