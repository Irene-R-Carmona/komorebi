<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Models\Cafe;
use App\Models\Reservation;
use App\Models\Tracker;
use PDO;
use Throwable;
use App\Core\Logger;
use App\Core\Result;
use App\Services\Contracts\ReceptionServiceInterface;

/**
 * Servicio de Recepción
 *
 * Gestiona el flujo operativo de llegada y salida de clientes.
 */
final class ReceptionService implements ReceptionServiceInterface
{
    private ?PDO $db = null;
    private ?Reservation $reservationModel = null;
    private ?Tracker $trackerModel = null;
    private ?Cafe $cafeModel = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    private function getReservationModel(): Reservation
    {
        return $this->reservationModel ??= new Reservation($this->getDb());
    }

    private function getTrackerModel(): Tracker
    {
        return $this->trackerModel ??= new Tracker($this->getDb());
    }

    private function getCafeModel(): Cafe
    {
        return $this->cafeModel ??= new Cafe($this->getDb());
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard de Recepción
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene todos los datos necesarios para el dashboard de recepción.
     */
    #[\Override]
    public function getDashboard(int $cafeId): array
    {
        $today = \date('Y-m-d');

        return [
            'pending_arrivals' => $this->getPendingArrivals($cafeId),
            'active_groups' => $this->getActiveGroups($cafeId),
            'available_trackers' => $this->getTrackerModel()->findAvailable($cafeId),
            'capacity' => $this->getCapacityInfo($cafeId),
            'stats' => $this->getDailyStats($cafeId, $today),
        ];
    }

    /**
     * Obtiene las reservas confirmadas pendientes de llegada (hoy).
     */
    #[\Override]
    public function getPendingArrivals(int $cafeId): array
    {
        $reservations = $this->getReservationModel()->findByCafeAndDate($cafeId, \date('Y-m-d'));

        // Filtrar solo confirmadas
        return \array_filter($reservations, static fn($r) => $r['status'] === Reservation::STATUS_CONFIRMED);
    }

    /**
     * Obtiene los grupos actualmente en el café.
     */
    #[\Override]
    public function getActiveGroups(int $cafeId): array
    {
        $groups = $this->getReservationModel()->findActiveByCafe($cafeId);

        // Enriquecer con tiempo transcurrido y tiempo restante
        foreach ($groups as &$group) {
            $group = $this->enrichActiveGroup($group);
        }

        return $groups;
    }

    // ─────────────────────────────────────────────────────────────
    // Check-in / Check-out
    // ─────────────────────────────────────────────────────────────

    /**
     * Procesa el check-in de un cliente.
     */
    #[\Override]
    public function processCheckin(int $reservationId, int $trackerId): Result
    {
        try {
            $success = Database::transaction(function () use ($reservationId, $trackerId) {
                // Verificar reserva
                $reservation = $this->getReservationModel()->findById($reservationId);

                if (!$reservation) {
                    throw NotFoundException::reservation($reservationId);
                }

                if ($reservation['status'] !== Reservation::STATUS_CONFIRMED) {
                    throw BusinessRuleException::invalidStateForOperation(
                        'check-in',
                        $reservation['status']
                    );
                }

                // Verificar tracker disponible
                $tracker = $this->getTrackerModel()->findById($trackerId);

                if (!$tracker || $tracker['status'] !== Tracker::STATUS_AVAILABLE) {
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

                // Realizar check-in
                $this->getReservationModel()->checkIn($reservationId, $trackerId);

                return true;
            });

            return Result::ok($success);
        } catch (NotFoundException $e) {
            return Result::fail($e->getMessage(), 'not_found');
        } catch (BusinessRuleException $e) {
            return Result::fail($e->getMessage(), $e->getRuleCode() ?? 'business_rule_error');
        } catch (\PDOException $e) {
            Logger::error('[ReceptionService] DB error in processCheckin()', ['exception' => $e->getMessage()]);
            return Result::fail('Error de base de datos', 'db_error');
        }
    }

    /**
     * Procesa el check-out de un cliente.
     */
    #[\Override]
    public function processCheckout(int $reservationId): Result
    {
        try {
            $checkoutData = Database::transaction(function () use ($reservationId) {
                $reservation = $this->getReservationModel()->findById($reservationId);

                if (!$reservation) {
                    throw NotFoundException::reservation($reservationId);
                }

                if ($reservation['status'] !== Reservation::STATUS_ACTIVE) {
                    throw BusinessRuleException::invalidStateForOperation(
                        'check-out',
                        $reservation['status']
                    );
                }

                // Realizar check-out (el modelo libera el tracker automáticamente)
                $this->getReservationModel()->checkOut($reservationId);

                // Obtener reserva actualizada con precio final
                $updated = $this->getReservationModel()->findById($reservationId);

                // 🎴 LOYALTY: Añadir sello al completar la visita
                if ($updated['status'] === Reservation::STATUS_COMPLETED && $updated['user_id']) {
                    try {
                        $loyaltyService = new LoyaltyService();
                        $loyaltyService->addStamp((int)$updated['user_id'], 1, $reservationId);
                    } catch (\Throwable $e) {
                        // Log error pero no falla el checkout
                        Logger::warning('Error al añadir sello de fidelización', [
                            'reservation_id' => $reservationId,
                            'user_id' => $updated['user_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'final_price' => $updated['final_price'] ?? 0,
                    'duration' => $this->calculateDuration($updated),
                ];
            });

            return Result::ok($checkoutData);
        } catch (NotFoundException $e) {
            return Result::fail($e->getMessage(), 'not_found');
        } catch (BusinessRuleException $e) {
            return Result::fail($e->getMessage(), $e->getRuleCode() ?? 'business_rule_error');
        } catch (\PDOException $e) {
            Logger::error('[ReceptionService] DB error in processCheckout()', ['exception' => $e->getMessage()]);
            return Result::fail('Error de base de datos', 'db_error');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Trackers
    // ─────────────────────────────────────────────────────────────

    /**
     * Asigna un tracker a una reserva activa.
     */
    #[\Override]
    public function assignTracker(int $reservationId, int $trackerId): bool
    {
        return $this->getReservationModel()->assignTracker($reservationId, $trackerId);
    }

    /**
     * Obtiene trackers disponibles para un café.
     */
    #[\Override]
    public function getAvailableTrackers(int $cafeId): array
    {
        return $this->getTrackerModel()->findAvailable($cafeId);
    }

    // ─────────────────────────────────────────────────────────────
    // Protocolos
    // ─────────────────────────────────────────────────────────────

    /**
     * Marca un protocolo como completado.
     */
    #[\Override]
    public function completeProtocol(int $reservationId, string $protocol): bool
    {
        return $this->getReservationModel()->completeProtocol($reservationId, $protocol);
    }

    /**
     * Obtiene el estado de los protocolos de una reserva.
     */
    #[\Override]
    public function getProtocolStatus(int $reservationId): Result
    {
        $reservation = $this->getReservationModel()->findById($reservationId);

        if (!$reservation) {
            return Result::fail('Reserva no encontrada', 'not_found');
        }

        return Result::ok([
            'hygiene' => (bool) $reservation['protocol_hygiene'],
            'briefing' => (bool) $reservation['protocol_briefing'],
            'shoes' => (bool) $reservation['protocol_shoes'],
            'all_complete' => $this->getReservationModel()->allProtocolsCompleted($reservation),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Capacidad
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene información de capacidad del café.
     */
    #[\Override]
    public function getCapacityInfo(int $cafeId): array
    {
        $cafe = $this->getCafeModel()->findById($cafeId);
        $maxCapacity = (int) ($cafe['capacity_max'] ?? 0);

        // Contar guests activos
        $activeGroups = $this->getReservationModel()->findActiveByCafe($cafeId);
        $currentGuests = \array_sum(\array_column($activeGroups, 'guests'));

        return [
            'max' => $maxCapacity,
            'current' => $currentGuests,
            'available' => \max(0, $maxCapacity - $currentGuests),
            'percentage' => $maxCapacity > 0 ? \round(($currentGuests / $maxCapacity) * 100) : 0,
            'is_full' => $currentGuests >= $maxCapacity,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene estadísticas diarias.
     */
    #[\Override]
    public function getDailyStats(int $cafeId, string $date): array
    {
        return $this->getReservationModel()->getDailyStats($cafeId, $date);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Enriquece un grupo activo con datos calculados.
     */
    private function enrichActiveGroup(array $group): array
    {
        // Tiempo transcurrido desde check-in
        if (!empty($group['check_in_at'])) {
            $checkin = \strtotime($group['check_in_at']);
            $elapsed = (\time() - $checkin) / 60;
            $group['elapsed_minutes'] = (int) $elapsed;

            // Tiempo restante (basado en duración del pase)
            $duration = (int) ($group['pass_duration_minutes'] ?? 60);
            $remaining = $duration - $elapsed;
            $group['remaining_minutes'] = \max(0, (int) $remaining);
            $group['is_overtime'] = $remaining < 0;
            $group['overtime_minutes'] = $remaining < 0 ? \abs((int) $remaining) : 0;
        }

        return $group;
    }

    /**
     * Calcula la duración de una visita.
     */
    private function calculateDuration(array $reservation): int
    {
        if (empty($reservation['check_in_at']) || empty($reservation['check_out_at'])) {
            return 0;
        }

        $checkin = \strtotime($reservation['check_in_at']);
        $checkout = \strtotime($reservation['check_out_at']);

        return (int) (($checkout - $checkin) / 60);
    }
}
