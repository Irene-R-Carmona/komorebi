<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Result;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Models\Reservation;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TrackerRepositoryInterface;
use App\Services\Contracts\LoyaltyServiceInterface;
use App\Services\Contracts\ReceptionServiceInterface;
use Override;
use PDOException;
use Throwable;

/**
 * Servicio de Recepción
 *
 * Gestiona el flujo operativo de llegada y salida de clientes.
 */
final class ReceptionService implements ReceptionServiceInterface
{
    private ReservationRepositoryInterface $reservationRepo;
    private TrackerRepositoryInterface $trackerRepo;
    private CafeRepositoryInterface $cafeRepo;

    public function __construct(
        ?ReservationRepositoryInterface $reservationRepo = null,
        ?TrackerRepositoryInterface $trackerRepo = null,
        ?CafeRepositoryInterface $cafeRepo = null
    ) {
        $this->reservationRepo = $reservationRepo ?? Container::make(ReservationRepositoryInterface::class);
        $this->trackerRepo     = $trackerRepo ?? Container::make(TrackerRepositoryInterface::class);
        $this->cafeRepo        = $cafeRepo ?? Container::make(CafeRepositoryInterface::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard de Recepción
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function getDashboard(int $cafeId): array
    {
        $today = \date('Y-m-d');

        return [
            'pending_arrivals'    => $this->getPendingArrivals($cafeId),
            'active_groups'       => $this->getActiveGroups($cafeId),
            'available_trackers'  => $this->trackerRepo->findAvailable($cafeId),
            'capacity'            => $this->getCapacityInfo($cafeId),
            'stats'               => $this->getDailyStats($cafeId, $today),
        ];
    }

    #[Override]
    public function getPendingArrivals(int $cafeId): array
    {
        $reservations = $this->reservationRepo->findByCafeAndDate($cafeId, \date('Y-m-d'));

        return \array_filter($reservations, static fn ($r) => $r['status'] === Reservation::STATUS_CONFIRMED);
    }

    #[Override]
    public function getActiveGroups(int $cafeId): array
    {
        $groups = $this->reservationRepo->findActiveByCafe($cafeId);

        foreach ($groups as &$group) {
            $group = $this->enrichActiveGroup($group);
        }

        return $groups;
    }

    // ─────────────────────────────────────────────────────────────
    // Check-in / Check-out
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function processCheckin(int $reservationId, int $trackerId): Result
    {
        try {
            $success = Database::transaction(function () use ($reservationId, $trackerId) {
                $reservation = $this->reservationRepo->findById($reservationId);

                if (!$reservation) {
                    throw NotFoundException::reservation($reservationId);
                }

                if ($reservation['status'] !== Reservation::STATUS_CONFIRMED) {
                    throw BusinessRuleException::invalidStateForOperation(
                        'check-in',
                        $reservation['status']
                    );
                }

                $tracker = $this->trackerRepo->findById($trackerId);

                if (!$tracker || $tracker['status'] !== 'available') {
                    throw new BusinessRuleException(
                        'Tracker no disponible',
                        'tracker_not_available',
                        ['tracker_id' => $trackerId]
                    );
                }

                if ((int) $tracker['cafe_id'] !== (int) $reservation['cafe_id']) {
                    throw new BusinessRuleException(
                        'Tracker no pertenece a este café',
                        'tracker_wrong_cafe',
                        ['tracker_cafe' => $tracker['cafe_id'], 'reservation_cafe' => $reservation['cafe_id']]
                    );
                }

                $this->reservationRepo->checkIn($reservationId, ['tracker_id' => $trackerId]);

                return true;
            });

            return Result::ok($success);
        } catch (NotFoundException $e) {
            return Result::fail($e->getMessage(), 'not_found');
        } catch (BusinessRuleException $e) {
            return Result::fail($e->getMessage(), $e->getRuleCode() ?? 'business_rule_error');
        } catch (PDOException $e) {
            Logger::error('[ReceptionService] DB error in processCheckin()', ['exception' => $e->getMessage()]);

            return Result::fail('Error de base de datos', 'db_error');
        }
    }

    #[Override]
    public function processCheckout(int $reservationId): Result
    {
        try {
            $checkoutData = Database::transaction(function () use ($reservationId) {
                $reservation = $this->reservationRepo->findById($reservationId);

                if (!$reservation) {
                    throw NotFoundException::reservation($reservationId);
                }

                if ($reservation['status'] !== Reservation::STATUS_ACTIVE) {
                    throw BusinessRuleException::invalidStateForOperation(
                        'check-out',
                        $reservation['status']
                    );
                }

                $this->reservationRepo->checkOut($reservationId);
                $updated = $this->reservationRepo->findById($reservationId);

                if ($updated['status'] === Reservation::STATUS_COMPLETED && $updated['user_id']) {
                    try {
                        $loyaltyService = Container::make(LoyaltyServiceInterface::class);
                        $loyaltyService->addStamp((int) $updated['user_id'], 1, $reservationId);
                    } catch (Throwable $e) {
                        Logger::warning('Error al añadir sello de fidelización', [
                            'reservation_id' => $reservationId,
                            'user_id'        => $updated['user_id'],
                            'error'          => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'success'     => true,
                    'final_price' => $updated['final_amount'] ?? 0,
                    'duration'    => $this->calculateDuration($updated),
                ];
            });

            return Result::ok($checkoutData);
        } catch (NotFoundException $e) {
            return Result::fail($e->getMessage(), 'not_found');
        } catch (BusinessRuleException $e) {
            return Result::fail($e->getMessage(), $e->getRuleCode() ?? 'business_rule_error');
        } catch (PDOException $e) {
            Logger::error('[ReceptionService] DB error in processCheckout()', ['exception' => $e->getMessage()]);

            return Result::fail('Error de base de datos', 'db_error');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Trackers
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function assignTracker(int $reservationId, int $trackerId): bool
    {
        return $this->reservationRepo->assignTracker($reservationId, $trackerId);
    }

    #[Override]
    public function getAvailableTrackers(int $cafeId): array
    {
        return $this->trackerRepo->findAvailable($cafeId);
    }

    // ─────────────────────────────────────────────────────────────
    // Protocolos
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function completeProtocol(int $reservationId, string $protocol): bool
    {
        return $this->reservationRepo->completeProtocol($reservationId, $protocol);
    }

    #[Override]
    public function getProtocolStatus(int $reservationId): Result
    {
        $reservation = $this->reservationRepo->findById($reservationId);

        if (!$reservation) {
            return Result::fail('Reserva no encontrada', 'not_found');
        }

        return Result::ok([
            'hygiene'      => (bool) $reservation['protocol_hygiene'],
            'briefing'     => (bool) $reservation['protocol_briefing'],
            'shoes'        => (bool) $reservation['protocol_shoes'],
            'all_complete' => $reservation['protocol_hygiene']
                           && $reservation['protocol_briefing']
                           && $reservation['protocol_shoes'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Capacidad
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function getCapacityInfo(int $cafeId): array
    {
        $cafe        = $this->cafeRepo->findById($cafeId);
        $maxCapacity = (int) ($cafe['capacity_max'] ?? 0);

        $activeGroups  = $this->reservationRepo->findActiveByCafe($cafeId);
        $currentGuests = \array_sum(\array_column($activeGroups, 'guests'));

        return [
            'max'        => $maxCapacity,
            'current'    => $currentGuests,
            'available'  => \max(0, $maxCapacity - $currentGuests),
            'percentage' => $maxCapacity > 0 ? \round(($currentGuests / $maxCapacity) * 100) : 0,
            'is_full'    => $currentGuests >= $maxCapacity,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    #[Override]
    public function getDailyStats(int $cafeId, string $date): array
    {
        return $this->reservationRepo->getDailyStats($cafeId, $date);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function enrichActiveGroup(array $group): array
    {
        if (!empty($group['check_in_at'])) {
            $checkin  = \strtotime($group['check_in_at']);
            $elapsed  = (\time() - $checkin) / 60;
            $duration = (int) ($group['pass_duration_minutes'] ?? 60);
            $remaining = $duration - $elapsed;

            $group['elapsed_minutes']  = (int) $elapsed;
            $group['remaining_minutes'] = \max(0, (int) $remaining);
            $group['is_overtime']       = $remaining < 0;
            $group['overtime_minutes']  = $remaining < 0 ? \abs((int) $remaining) : 0;
        }

        return $group;
    }

    private function calculateDuration(array $reservation): int
    {
        if (empty($reservation['check_in_at']) || empty($reservation['check_out_at'])) {
            return 0;
        }

        return (int) ((\strtotime($reservation['check_out_at']) - \strtotime($reservation['check_in_at'])) / 60);
    }
}
