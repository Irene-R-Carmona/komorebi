<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Queue;
use App\Core\Result;
use App\Core\TransactionalService;
use App\Core\WideEvent;
use App\Jobs\WaitlistPromotionJob;
use App\Models\Waitlist;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Services\Contracts\EmailServiceInterface;
use App\Services\Contracts\WaitlistServiceInterface;
use Exception;
use Override;
use PDO;
use Random\RandomException;

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
final class WaitlistService extends TransactionalService implements WaitlistServiceInterface
{
    private TimeSlotRepositoryInterface $timeSlotRepo;
    private ReservationRepositoryInterface $reservationRepo;
    private EmailServiceInterface $emailService;
    private WaitlistRepositoryInterface $waitlistRepository;

    public function __construct(
        PDO $db,
        EmailServiceInterface $emailService,
        WaitlistRepositoryInterface $waitlistRepository,
        TimeSlotRepositoryInterface $timeSlotRepo,
        ReservationRepositoryInterface $reservationRepo
    ) {
        parent::__construct($db);
        $this->timeSlotRepo = $timeSlotRepo;
        $this->reservationRepo = $reservationRepo;
        $this->emailService = $emailService;
        $this->waitlistRepository = $waitlistRepository;
    }

    /**
     * Añadir un usuario a la waitlist
     *
     * @param array $data Datos adicionales (email, phone, guest_count, special_requests)
     * @throws RandomException
     */
    #[Override]
    public function joinWaitlist(int $timeSlotId, int $userId, array $data): Result
    {
        $slot = $this->timeSlotRepo->findById($timeSlotId);
        if ($slot === null) {
            return Result::fail('Time slot no encontrado');
        }

        $availableSpots = $slot->available_spots;
        if ($availableSpots > 0) {
            return Result::fail('El time slot todavía tiene plazas disponibles. No es necesario entrar en lista de espera.');
        }

        if ($this->waitlistRepository->userInWaitlist($userId, $timeSlotId)) {
            return Result::fail('Ya estás en la lista de espera para este horario');
        }

        $guestCount = (int) ($data['guest_count'] ?? 1);
        if ($guestCount < 1 || $guestCount > 10) {
            return Result::fail('El número de comensales debe estar entre 1 y 10', 'invalid_guest_count');
        }

        $token = \bin2hex(\random_bytes(16));
        $responseTimeout = (int) ($data['response_timeout_minutes'] ?? Waitlist::DEFAULT_RESPONSE_TIMEOUT);
        $expiresAt = \date('Y-m-d H:i:s', \time() + ($responseTimeout * 60));

        $waitlistData = [
            'user_id' => $userId,
            'time_slot_id' => $timeSlotId,
            'contact_email' => $data['email'] ?? $data['contact_email'] ?? '',
            'contact_phone' => $data['phone'] ?? $data['contact_phone'] ?? null,
            'guest_count' => $guestCount,
            'special_requests' => $data['special_requests'] ?? null,
            'confirmation_token' => $token,
            'expires_at' => $expiresAt,
            'status' => 'waiting',
        ];

        $waitlistId = $this->waitlistRepository->create($waitlistData);

        if ($waitlistId <= 0) {
            Logger::warning('[WaitlistService::addToWaitlist] DB insert returned 0', ['time_slot_id' => $timeSlotId, 'user_id' => $userId]);

            return Result::fail('Error al añadir a la lista de espera');
        }

        $position = $this->waitlistRepository->getPosition($timeSlotId, $userId) ?? 0;

        try {
            $userName = (string) ($data['user_name'] ?? 'Usuario');
            $userEmail = (string) ($data['email'] ?? '');

            if ($userEmail !== '') {
                $this->emailService->sendWaitlistConfirmation(
                    $userEmail,
                    $userName,
                    $token,
                    [
                        'slot_date' => $slot->slot_date,
                        'slot_time' => $slot->slot_time,
                        'position' => $position,
                    ]
                );
            }
        } catch (Exception $e) {
            // Log error but don't fail the waitlist join
            Logger::warning('[WaitlistService] Error enviando email de waitlist', ['error' => $e->getMessage()]);
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
     */
    #[Override]
    public function promoteNext(int $timeSlotId): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $next = $this->waitlistRepository->getNextInLine($timeSlotId);

            if (!\is_array($next) || empty($next)) {
                if ($startedTransaction) {
                    $this->db->commit();
                }

                return Result::ok(['promoted' => false, 'message' => 'No hay nadie en la waitlist']);
            }

            /** @var array<string,mixed> $next */
            $rawNextId = $next['id'] ?? null;
            $nextId = \is_scalar($rawNextId) ? (int) $rawNextId : 0;

            $rawUserId = $next['user_id'] ?? null;
            $userId = \is_scalar($rawUserId) ? (int) $rawUserId : 0;

            $rawTimeout = $next['response_timeout_minutes'] ?? null;
            $responseTimeout = \is_scalar($rawTimeout) ? (int) $rawTimeout : Waitlist::DEFAULT_RESPONSE_TIMEOUT;
            $expiresAt = \date('Y-m-d H:i:s', \time() + ($responseTimeout * 60));

            $rawToken = $next['token'] ?? null;
            $token = \is_scalar($rawToken) ? (string) $rawToken : '';

            $rawContact = $next['contact_email'] ?? null;
            $contactEmail = \is_scalar($rawContact) ? (string) $rawContact : '';

            $rawGuestCount = $next['guest_count'] ?? null;
            $guestCount = \is_scalar($rawGuestCount) ? (int) $rawGuestCount : 1;

            if ($nextId <= 0 || $userId <= 0) {
                if ($startedTransaction) {
                    $this->db->commit();
                }

                return Result::ok(['promoted' => false, 'message' => 'Siguiente en cola inválido']);
            }

            $this->waitlistRepository->updateStatusWithData($nextId, Waitlist::STATUS_NOTIFIED, ['expires_at' => $expiresAt]);

            Queue::push(WaitlistPromotionJob::class, [
                'waitlist_id' => $nextId,
                'user_id' => $userId,
                'time_slot_id' => $timeSlotId,
                'token' => $token,
                'contact_email' => $contactEmail,
                'expires_at' => $expiresAt,
                'guest_count' => $guestCount,
                '_correlation_id' => WideEvent::get('request_id') ?? '',
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
        } catch (Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }

            Logger::warning('[WaitlistService::promoteNext] transaction failure', ['exception' => $e->getMessage()]);

            return Result::fail('Error al promocionar waitlist: ' . $e->getMessage());
        }
    }

    /**
     * Confirmar la promoción de waitlist y crear la reserva
     */
    #[Override]
    public function confirmPromotion(string $token, array $reservationData = []): Result
    {
        try {
            // Solo iniciar transacción si no hay una activa
            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $waitlistEntry = $this->waitlistRepository->findByToken($token);

            if ($waitlistEntry === null) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Token de waitlist no válido');
            }

            if ($waitlistEntry->status !== Waitlist::STATUS_NOTIFIED) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Esta promoción ya fue procesada o expiró');
            }

            $waitlistId = $waitlistEntry->id;
            $timeSlotIdInt = $waitlistEntry->time_slot_id;
            $position = $waitlistEntry->position ?? 0;
            $guestCount = $waitlistEntry->guest_count;

            $expiresAtStr = $waitlistEntry->expires_at ?? '';
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
            $slot = $this->timeSlotRepo->findById($timeSlotIdInt);

            if ($slot === null) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Time slot no disponible');
            }

            $availableSpots = $slot->available_spots;
            if ($availableSpots <= 0) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('El time slot ya no tiene plazas disponibles');
            }

            $userIdInt = $waitlistEntry->user_id;
            $specialReq = $waitlistEntry->special_requests ?? '';

            $rawPassProduct = $reservationData['pass_product_id'] ?? null;
            $passProductId = \is_scalar($rawPassProduct) ? (int) $rawPassProduct : 0;

            $rawPassName = $reservationData['pass_name'] ?? null;
            $passName = \is_scalar($rawPassName) ? (string) $rawPassName : 'Reserva desde Waitlist';

            $rawUnitPrice = $reservationData['pass_unit_price'] ?? null;
            $unitPrice = \is_scalar($rawUnitPrice) ? (int) $rawUnitPrice : 0;

            $rawDuration = $reservationData['pass_duration_minutes'] ?? null;
            $duration = \is_scalar($rawDuration) ? (int) $rawDuration : 60;

            $reservationPayload = [
                'user_id' => $userIdInt,
                'cafe_id' => $slot->cafe_id,
                'time_slot_id' => $timeSlotIdInt,
                'reservation_date' => $slot->slot_date,
                'reservation_time' => $slot->slot_time,
                'guests' => $guestCount,
                'pass_product_id' => $passProductId,
                'pass_name' => $passName,
                'pass_unit_price' => $unitPrice,
                'pass_duration_minutes' => $duration,
                'comments' => $specialReq,
            ];

            $newReservationId = $this->reservationRepo->create($reservationPayload);

            if ($newReservationId <= 0) {
                if ($startedTransaction) {
                    $this->db->rollBack();
                }

                return Result::fail('Error al crear reserva desde lista de espera');
            }

            $this->timeSlotRepo->reserveSpots($timeSlotIdInt, $guestCount);

            $this->waitlistRepository->updateStatusWithData($waitlistId, Waitlist::STATUS_CONFIRMED, ['reservation_id' => $newReservationId]);

            $this->waitlistRepository->reorderPositions($timeSlotIdInt, $position);

            if ($startedTransaction) {
                $this->db->commit();
            }

            return Result::ok([
                'reservation_id' => $newReservationId,
                'waitlist_id' => $waitlistId,
                'message' => '¡Reserva confirmada desde lista de espera!',
            ]);
        } catch (Exception $e) {
            if ($startedTransaction) {
                $this->db->rollBack();
            }

            Logger::warning('[WaitlistService::confirmPromotion] transaction failure', ['exception' => $e->getMessage()]);

            return Result::fail('Error al confirmar promoción: ' . $e->getMessage());
        }
    }

    /**
     * Expirar tokens de waitlist que no han sido confirmados
     *
     * Método pensado para ejecutarse periódicamente (cron/scheduler)
     */
    #[Override]
    public function expireTokens(): Result
    {
        try {
            $expiredCount = $this->waitlistRepository->expireTokens();

            return Result::ok([
                'expired_count' => $expiredCount,
                'message' => "Se expiraron {$expiredCount} tokens y se promovieron los siguientes en cola",
            ]);
        } catch (Exception $e) {
            return Result::fail('Error al expirar tokens: ' . $e->getMessage());
        }
    }

    #[Override]
    public function getPosition(int $userId, int $timeSlotId): Result
    {
        $position = $this->waitlistRepository->getPosition($timeSlotId, $userId);
        $totalWaiting = $this->waitlistRepository->countByTimeSlotAndStatus($timeSlotId, Waitlist::STATUS_WAITING);

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
    #[Override]
    public function cancelWaitlist(int $waitlistId, int $userId): Result
    {
        return $this->transact(function () use ($waitlistId, $userId): Result {
            $entry = $this->waitlistRepository->findByIdAndUser($waitlistId, $userId);

            if (!$entry) {
                return Result::fail('Entrada de waitlist no encontrada o no autorizada');
            }

            // Solo se puede cancelar si está en estado 'waiting' o 'notified'
            if (!\in_array($entry['status'], [Waitlist::STATUS_WAITING, Waitlist::STATUS_NOTIFIED], true)) {
                return Result::fail('No se puede cancelar una entrada en estado: ' . $entry['status']);
            }

            $this->waitlistRepository->updateStatus($waitlistId, Waitlist::STATUS_CANCELLED);

            $rawTimeSlot = $entry['time_slot_id'] ?? null;
            $timeSlotIdInt = \is_scalar($rawTimeSlot) ? (int) $rawTimeSlot : 0;
            $rawPos = $entry['position'] ?? null;
            $position = \is_scalar($rawPos) ? (int) $rawPos : 0;
            $this->waitlistRepository->reorderPositions($timeSlotIdInt, $position);

            return Result::ok([
                'cancelled' => true,
                'message' => 'Has sido eliminado de la lista de espera',
            ]);
        });
    }

    #[Override]
    public function getUserHistory(int $userId, int $limit = 10): Result
    {
        $entries = $this->waitlistRepository->getUserHistory($userId, $limit);

        return Result::ok([
            'entries' => $entries,
            'count' => \count($entries),
        ]);
    }

    /**
     * Usado para la vista pública /waitlist/status/{token}
     */
    #[Override]
    public function getWaitlistStatus(string $token): Result
    {
        $entry = $this->waitlistRepository->findByToken($token);

        if ($entry === null) {
            return Result::fail('Token de waitlist no válido o expirado');
        }

        $timeSlotId = $entry->time_slot_id;
        if ($timeSlotId <= 0) {
            return Result::fail('Time slot inválido');
        }

        $slotResult = $this->timeSlotRepo->findById($timeSlotId);

        if ($slotResult === null) {
            return Result::fail('Error al obtener información del horario');
        }

        $slot = $slotResult;

        // Calcular tiempo estimado de espera (15 min por posición)
        $positionInt = $entry->position ?? 0;
        $estimatedWaitMinutes = \max(0, ($positionInt - 1) * 15);

        return Result::ok([
            'id' => $entry->id,
            'token' => $entry->token,
            'position' => $positionInt,
            'status' => $entry->status,
            'guest_count' => $entry->guest_count,
            'special_requests' => $entry->special_requests,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'expires_at' => $entry->expires_at,
            'notified_at' => null,
            'created_at' => $entry->created_at,
            'user_name' => null,
            'time_slot' => [
                'id' => $slot->id,
                'date' => $slot->slot_date,
                'time' => $slot->slot_time,
                'cafe_id' => $slot->cafe_id,
                'available_spots' => $slot->available_spots,
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
    #[Override]
    public function getUserWaitlists(int $userId, bool $activeOnly = true): Result
    {
        if ($activeOnly) {
            $waitlists = $this->waitlistRepository->findActiveByUserId($userId);
        } else {
            $waitlists = $this->waitlistRepository->getUserHistory($userId, 50);
        }

        return Result::ok([
            'waitlists' => $waitlists,
            'count' => \count($waitlists),
        ]);
    }
}
