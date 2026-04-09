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
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Repositories\WaitlistRepository;
use PDO;

/**
 * WaitlistService - Gestión de lista de espera para reservas
 *
 * FASE 2.3: Sistema completo de waitlist con promoción automática
 *
 * Funcionalidades:
 * - Añadir usuarios a waitlist cuando no hay disponibilidad
 * - Promoción automática al liberarse un slot
 * - Notificaciones asíncronas vía email
 * - Expiración automática de tokens (15 min)
 * - Reordenamiento de posiciones FIFO
 */
final class WaitlistService extends TransactionalService
{
    private TimeSlot $timeSlotModel;
    private Reservation $reservationModel;
    private EmailService $emailService;
    private WaitlistRepositoryInterface $waitlistRepository;

    public function __construct(
        PDO $db,
        ?EmailService $emailService = null,
        ?WaitlistRepositoryInterface $waitlistRepository = null
    ) {
        parent::__construct($db);
        $this->timeSlotModel = new TimeSlot($db);
        $this->reservationModel = new Reservation($db);
        $this->emailService = $emailService ?? new EmailService();
        $this->waitlistRepository = $waitlistRepository ?? new WaitlistRepository($db);
    }

    /**
     * Añadir un usuario a la waitlist
     *
     * @param integer $timeSlotId ID del time slot deseado
     * @param integer $userId     ID del usuario
     * @param array   $data       Datos adicionales (email, phone, guest_count, special_requests)
     * @return Result
     */
    public function joinWaitlist(int $timeSlotId, int $userId, array $data): Result
    {
        // Validar que el slot existe
        $slotResult = $this->timeSlotModel->findById($timeSlotId);
        if (!$slotResult->isOk()) {
            return Result::fail('Time slot no encontrado');
        }

        $slot = $slotResult->getDataOr(null);

        if (!is_array($slot) || empty($slot)) {
            return Result::fail('Time slot no encontrado');
        }

        // Verificar que el slot esté completo (no tiene sentido waitlist si hay espacio)
        $availableSpots = (int) ($slot['available_spots'] ?? 0);
        if ($availableSpots > 0) {
            return Result::fail('El time slot todavía tiene plazas disponibles. No es necesario entrar en lista de espera.');
        }

        // Verificar si el usuario ya está en la waitlist
        if ($this->waitlistRepository->userInWaitlist($userId, $timeSlotId)) {
            return Result::fail('Ya estás en la lista de espera para este horario');
        }

        // Generar token único
        $token = bin2hex(random_bytes(16));
        $responseTimeout = (int) ($data['response_timeout_minutes'] ?? Waitlist::DEFAULT_RESPONSE_TIMEOUT);
        $expiresAt = date('Y-m-d H:i:s', time() + ($responseTimeout * 60));

        // Crear entrada en waitlist usando repositorio
        $waitlistData = [
            'user_id' => $userId,
            'time_slot_id' => $timeSlotId,
            'contact_email' => $data['email'] ?? $data['contact_email'] ?? '',
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'guest_count' => $data['guest_count'] ?? 1,
            'special_requests' => $data['special_requests'] ?? null,
            'confirmation_token' => $token,
            'expires_at' => $expiresAt,
            'status' => 'waiting',
        ];

        $waitlistId = $this->waitlistRepository->create($waitlistData);

        if ($waitlistId <= 0) {
            return Result::fail('Error al añadir a la lista de espera');
        }

        // Obtener la posición del usuario
        $position = $this->waitlistRepository->getPosition($timeSlotId, $userId) ?? 0;

        // Enviar email de confirmación con el token
        try {
            $userName = (string) ($data['user_name'] ?? 'Usuario');
            $userEmail = (string) ($data['email'] ?? '');

            if ($userEmail !== '') {
                $this->emailService->sendWaitlistConfirmation(
                    $userEmail,
                    $userName,
                    $token,
                    [
                        'slot_date' => (string) ($slot['slot_date'] ?? ''),
                        'slot_time' => (string) ($slot['slot_time'] ?? ''),
                        'position' => $position,
                    ]
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the waitlist join
            \error_log('Error enviando email de waitlist: ' . $e->getMessage());
        }

        return Result::ok([
            'waitlist_id' => $waitlistId,
            'token' => $token,
            'position' => $position,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Promocionar al siguiente usuario en la waitlist de un slot
     *
     * Se llama cuando se libera un espacio (cancelación, no-show, etc.)
     *
     * @param integer $timeSlotId ID del time slot que se ha liberado
     * @return Result
     */
    public function promoteNext(int $timeSlotId): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }


            // Obtener el siguiente en la cola
            $next = $this->waitlistRepository->getNextInLine($timeSlotId);

            if (!is_array($next) || empty($next)) {
                if ($startedTransaction) {
                    $this->db->commit();
                }

                return Result::ok(['promoted' => false, 'message' => 'No hay nadie en la waitlist']);
            }

            // Normalizar y validar campos esenciales
            /** @var array<string,mixed> $next */
            $rawNextId = $next['id'] ?? null;
            $nextId = is_scalar($rawNextId) ? (int) $rawNextId : 0;

            $rawUserId = $next['user_id'] ?? null;
            $userId = is_scalar($rawUserId) ? (int) $rawUserId : 0;

            $rawTimeout = $next['response_timeout_minutes'] ?? null;
            $responseTimeout = is_scalar($rawTimeout) ? (int) $rawTimeout : Waitlist::DEFAULT_RESPONSE_TIMEOUT;
            $expiresAt = \date('Y-m-d H:i:s', \time() + ($responseTimeout * 60));

            $rawToken = $next['token'] ?? null;
            $token = is_scalar($rawToken) ? (string) $rawToken : '';

            $rawContact = $next['contact_email'] ?? null;
            $contactEmail = is_scalar($rawContact) ? (string) $rawContact : '';

            $rawGuestCount = $next['guest_count'] ?? null;
            $guestCount = is_scalar($rawGuestCount) ? (int) $rawGuestCount : 1;

            if ($nextId <= 0 || $userId <= 0) {
                if ($startedTransaction) {
                    $this->db->commit();
                }

                return Result::ok(['promoted' => false, 'message' => 'Siguiente en cola inválido']);
            }

            // Actualizar estado a 'notified'
            $this->waitlistRepository->updateStatusWithData($nextId, Waitlist::STATUS_NOTIFIED, ['expires_at' => $expiresAt]);

            // Enviar notificación asíncrona vía cola
            Queue::push(WaitlistPromotionJob::class, [
                'waitlist_id' => $nextId,
                'user_id' => $userId,
                'time_slot_id' => $timeSlotId,
                'token' => $token,
                'contact_email' => $contactEmail,
                'expires_at' => $expiresAt,
                'guest_count' => $guestCount,
            ], 'default');

            if ($startedTransaction) {
                $this->db->commit();
            }

            return Result::ok([
                'promoted' => true,
                'waitlist_id' => $nextId,
                'token' => $token,
                'user_id' => $userId,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }

            return Result::fail('Error al promocionar waitlist: ' . $e->getMessage());
        }
    }

    /**
     * Confirmar la promoción de waitlist y crear la reserva
     *
     * @param string $token           Token único de confirmación
     * @param array  $reservationData Datos adicionales para la reserva
     * @return Result
     */
    public function confirmPromotion(string $token, array $reservationData = []): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            // Buscar entrada de waitlist por token
            $waitlistEntry = $this->waitlistRepository->findByToken($token);

            if (!is_array($waitlistEntry) || empty($waitlistEntry)) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Token de waitlist no válido');
            }

            // Verificar que esté en estado 'notified'
            if (($waitlistEntry['status'] ?? '') !== Waitlist::STATUS_NOTIFIED) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Esta promoción ya fue procesada o expiró');
            }

            // Normalizar campos importantes
            /** @var array<string,mixed> $waitlistEntry */
            $rawWaitlistId = $waitlistEntry['id'] ?? null;
            $waitlistId = is_scalar($rawWaitlistId) ? (int) $rawWaitlistId : 0;

            $rawTimeSlotId = $waitlistEntry['time_slot_id'] ?? null;
            $timeSlotIdInt = is_scalar($rawTimeSlotId) ? (int) $rawTimeSlotId : 0;

            $rawPosition = $waitlistEntry['position'] ?? null;
            $position = is_scalar($rawPosition) ? (int) $rawPosition : 0;

            $rawGuestCount = $waitlistEntry['guest_count'] ?? null;
            $guestCount = is_scalar($rawGuestCount) ? (int) $rawGuestCount : 1;

            // Verificar que no haya expirado
            $rawExpires = $waitlistEntry['expires_at'] ?? null;
            $expiresAtStr = is_scalar($rawExpires) ? (string) $rawExpires : '';
            $expiresTimestamp = $expiresAtStr === '' ? false : \strtotime($expiresAtStr);

            if ($expiresTimestamp === false || $expiresTimestamp < \time()) {
                // Marcar como expirado y promocionar al siguiente
                $this->waitlistRepository->updateStatus($waitlistId, Waitlist::STATUS_EXPIRED);
                $this->waitlistRepository->reorderPositions($timeSlotIdInt, $position);

                // Intentar promocionar al siguiente
                if ($startedTransaction) {
                    $this->db->commit();
                }
                $this->promoteNext($timeSlotIdInt);

                return Result::fail('El tiempo para confirmar ha expirado. Hemos notificado al siguiente en la lista.');
            }

            // Verificar disponibilidad del slot (bloqueo pesimista)
            $slotResult = $this->timeSlotModel->findByIdForUpdate($timeSlotIdInt);

            if (!$slotResult->isOk()) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Time slot no disponible');
            }

            $slot = $slotResult->getDataOr(null);

            /** @var array<string,mixed>|null $slot */
            $rawAvailable = $slot['available_spots'] ?? null;
            $availableSpots = is_scalar($rawAvailable) ? (int) $rawAvailable : 0;
            if (!is_array($slot) || $availableSpots <= 0) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('El time slot ya no tiene plazas disponibles');
            }

            // Construir payload tipado para Reservation::create()
            $rawUserId = $waitlistEntry['user_id'] ?? null;
            $userIdInt = is_scalar($rawUserId) ? (int) $rawUserId : 0;

            $rawCafeId = $slot['cafe_id'] ?? null;
            $cafeIdInt = is_scalar($rawCafeId) ? (int) $rawCafeId : 0;

            $rawSlotDate = $slot['slot_date'] ?? null;
            $rawSlotTime = $slot['slot_time'] ?? null;

            $rawPassProduct = $reservationData['pass_product_id'] ?? null;
            $passProductId = is_scalar($rawPassProduct) ? (int) $rawPassProduct : 0;

            $rawPassName = $reservationData['pass_name'] ?? null;
            $passName = is_scalar($rawPassName) ? (string) $rawPassName : 'Reserva desde Waitlist';

            $rawUnitPrice = $reservationData['pass_unit_price'] ?? null;
            $unitPrice = is_scalar($rawUnitPrice) ? (int) $rawUnitPrice : 0;

            $rawDuration = $reservationData['pass_duration_minutes'] ?? null;
            $duration = is_scalar($rawDuration) ? (int) $rawDuration : 60;

            $rawSpecial = $waitlistEntry['special_requests'] ?? null;
            $specialReq = is_scalar($rawSpecial) ? (string) $rawSpecial : '';

            $reservationPayload = [
                'user_id' => $userIdInt,
                'cafe_id' => $cafeIdInt,
                'time_slot_id' => $timeSlotIdInt,
                'reservation_date' => is_scalar($rawSlotDate) ? (string) $rawSlotDate : '',
                'reservation_time' => is_scalar($rawSlotTime) ? (string) $rawSlotTime : '',
                'guests' => $guestCount,
                'pass_product_id' => $passProductId,
                'pass_name' => $passName,
                'pass_unit_price' => $unitPrice,
                'pass_duration_minutes' => $duration,
                'comments' => $specialReq,
            ];

            $reservationResult = $this->reservationModel->create($reservationPayload);

            if (!$reservationResult->isOk()) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Error al crear reserva: ' . $reservationResult->getMessage());
            }

            $reservation = $reservationResult->getDataOr([]);
            if (!is_array($reservation)) {
                $reservation = [];
            }

            // Decrementar spots disponibles del slot
            $this->timeSlotModel->decrementSpots($timeSlotIdInt, $guestCount);

            // Actualizar waitlist a 'confirmed'
            $this->waitlistRepository->updateStatusWithData($waitlistId, Waitlist::STATUS_CONFIRMED, ['reservation_id' => (int) ($reservation['reservation_id'] ?? 0)]);

            // Reordenar posiciones
            $this->waitlistRepository->reorderPositions($timeSlotIdInt, $position);

            if ($startedTransaction) {
                $this->db->commit();
            }

            return Result::ok([
                'reservation_id' => $reservation['reservation_id'] ?? null,
                'waitlist_id' => $waitlistId,
                'message' => '¡Reserva confirmada desde lista de espera!',
            ]);
        } catch (\Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }

            return Result::fail('Error al confirmar promoción: ' . $e->getMessage());
        }
    }

    /**
     * Expirar tokens de waitlist que no han sido confirmados
     *
     * Método pensado para ejecutarse periódicamente (cron/scheduler)
     *
     * @return Result
     */
    public function expireTokens(): Result
    {
        try {
            // Usar método del repositorio que expira y devuelve cantidad
            $expiredCount = $this->waitlistRepository->expireTokens();

            return Result::ok([
                'expired_count' => $expiredCount,
                'message' => "Se expiraron {$expiredCount} tokens y se promovieron los siguientes en cola",
            ]);
        } catch (\Exception $e) {
            return Result::fail('Error al expirar tokens: ' . $e->getMessage());
        }
    }

    /**
     * Obtener la posición de un usuario en la waitlist de un slot
     *
     * @param integer $userId
     * @param integer $timeSlotId
     * @return Result
     */
    public function getPosition(int $userId, int $timeSlotId): Result
    {
        $position = $this->waitlistRepository->getPosition($timeSlotId, $userId);

        // Contar todos los que están esperando en este slot
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM waitlist WHERE time_slot_id = ? AND status = ?'
        );
        $stmt->execute([$timeSlotId, Waitlist::STATUS_WAITING]);
        $totalWaiting = (int) $stmt->fetchColumn();

        return Result::ok([
            'position' => $position,
            'total_waiting' => $totalWaiting,
            'message' => $position
                ? "Estás en posición #{$position} de {$totalWaiting} personas"
                : 'No estás en la lista de espera para este horario',
        ]);
    }

    /**
     * Cancelar una entrada de waitlist
     *
     * @param integer $waitlistId
     * @param integer $userId     Usuario que cancela (para verificar ownership)
     * @return Result
     */
    public function cancelWaitlist(int $waitlistId, int $userId): Result
    {
        return $this->transact(function () use ($waitlistId, $userId): Result {
            // Verificar que el waitlist pertenece al usuario
            $stmt = $this->db->prepare('
                SELECT * FROM waitlist WHERE id = ? AND user_id = ?
            ');
            $stmt->execute([$waitlistId, $userId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$entry) {
                return Result::fail('Entrada de waitlist no encontrada o no autorizada');
            }

            // Solo se puede cancelar si está en estado 'waiting' o 'notified'
            if (!\in_array($entry['status'], [Waitlist::STATUS_WAITING, Waitlist::STATUS_NOTIFIED], true)) {
                return Result::fail('No se puede cancelar una entrada en estado: ' . $entry['status']);
            }

            // Actualizar a 'cancelled'
            $this->waitlistRepository->updateStatus($waitlistId, Waitlist::STATUS_CANCELLED);

            // Reordenar posiciones (usar valores tipados)
            $rawTimeSlot = $entry['time_slot_id'] ?? null;
            $timeSlotIdInt = is_scalar($rawTimeSlot) ? (int) $rawTimeSlot : 0;
            $rawPos = $entry['position'] ?? null;
            $position = is_scalar($rawPos) ? (int) $rawPos : 0;
            $this->waitlistRepository->reorderPositions($timeSlotIdInt, $position);

            return Result::ok([
                'cancelled' => true,
                'message' => 'Has sido eliminado de la lista de espera',
            ]);
        });
    }

    /**
     * Obtener historial de waitlist de un usuario
     *
     * @param integer $userId
     * @param integer $limit
     * @return Result
     */
    public function getUserHistory(int $userId, int $limit = 10): Result
    {
        $entries = $this->waitlistRepository->getUserHistory($userId, $limit);

        return Result::ok([
            'entries' => $entries,
            'count' => \count($entries),
        ]);
    }

    /**
     * Obtener estado completo de una entrada de waitlist por token
     *
     * Usado para la vista pública /waitlist/status/{token}
     *
     * @param string $token
     * @return Result
     */
    public function getWaitlistStatus(string $token): Result
    {
        $entry = $this->waitlistRepository->findByToken($token);

        if (!is_array($entry) || empty($entry)) {
            return Result::fail('Token de waitlist no válido o expirado');
        }

        // Obtener información del time slot (usar id tipado)
        $rawTimeSlotId = $entry['time_slot_id'] ?? null;
        $timeSlotId = is_scalar($rawTimeSlotId) ? (int) $rawTimeSlotId : 0;
        if ($timeSlotId <= 0) {
            return Result::fail('Time slot inválido');
        }

        $slotResult = $this->timeSlotModel->findById($timeSlotId);

        if (!$slotResult->isOk()) {
            return Result::fail('Error al obtener información del horario');
        }

        $slot = $slotResult->getDataOr([]);
        if (!is_array($slot)) {
            $slot = [];
        }

        // Calcular tiempo estimado de espera (15 min por posición)
        $rawPos = $entry['position'] ?? null;
        $positionInt = is_scalar($rawPos) ? (int) $rawPos : 0;
        $estimatedWaitMinutes = max(0, ($positionInt - 1) * 15);

        return Result::ok([
            'id' => $entry['id'],
            'position' => $positionInt,
            'status' => $entry['status'],
            'guest_count' => is_scalar($entry['guest_count'] ?? null) ? (int) $entry['guest_count'] : 0,
            'special_requests' => $entry['special_requests'] ?? null,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'expires_at' => $entry['expires_at'] ?? null,
            'notified_at' => $entry['notified_at'] ?? null,
            'created_at' => $entry['created_at'] ?? null,
            'user_name' => $entry['user_name'] ?? null,
            'time_slot' => [
                'id' => $slot['id'] ?? null,
                'date' => $slot['slot_date'] ?? null,
                'time' => $slot['slot_time'] ?? null,
                'cafe_id' => $slot['cafe_id'] ?? null,
                'available_spots' => $slot['available_spots'] ?? null,
            ],
        ]);
    }

    /**
     * Obtener todas las listas de espera de un usuario
     *
     * @param integer $userId     ID del usuario
     * @param boolean $activeOnly Solo listas activas (waiting, notified)
     *
     * @psalm-return Result
     */
    public function getUserWaitlists(int $userId, bool $activeOnly = true): Result
    {
        if ($activeOnly) {
            $waitlists = $this->waitlistRepository->findActiveByUserId($userId);
        } else {
            $waitlists = $this->waitlistRepository->getUserHistory($userId, 50);
        }

        return Result::ok([
            'waitlists' => $waitlists,
            'count' => count($waitlists),
        ]);
    }
}
