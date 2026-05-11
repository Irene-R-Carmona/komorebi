<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Result;
use App\Core\Time;
use App\Domain\DTO\CafeDTO;
use App\Domain\DTO\ProductDTO;
use App\Repositories\Contracts\CafeRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\TimeSlotRepositoryInterface;
use App\Services\Contracts\AvailabilityServiceInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Override;

final class AvailabilityService implements AvailabilityServiceInterface
{
    private CafeRepositoryInterface $cafeRepo;
    private ProductRepositoryInterface $productRepo;
    private ReservationRepositoryInterface $reservationRepo;
    private ?TimeSlotRepositoryInterface $timeSlotRepo;
    private int $maxDaysAhead;
    private int $stepMinutes;

    public function __construct(
        ?CafeRepositoryInterface $cafeRepo = null,
        ?ProductRepositoryInterface $productRepo = null,
        ?ReservationRepositoryInterface $reservationRepo = null,
        ?TimeSlotRepositoryInterface $timeSlotRepo = null,
        int $maxDaysAhead = 30,
        int $stepMinutes = 30,
    ) {
        $this->cafeRepo = $cafeRepo ?? Container::make(CafeRepositoryInterface::class);
        $this->productRepo = $productRepo ?? Container::make(ProductRepositoryInterface::class);
        $this->reservationRepo = $reservationRepo ?? Container::make(ReservationRepositoryInterface::class);
        $this->timeSlotRepo = $timeSlotRepo;
        $this->maxDaysAhead = $maxDaysAhead;
        $this->stepMinutes = $stepMinutes;
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function getAvailableSlots(int $cafeId, int $passId, string $dateYmd, int $guests): Result
    {
        if ($cafeId <= 0 || $passId <= 0 || $guests <= 0) {
            return Result::fail('Datos inválidos.', 'invalid_input');
        }

        if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return Result::fail('Fecha inválida.', 'invalid_input');
        }

        $daysAhead = Time::daysAheadBusiness($dateYmd);
        if ($daysAhead < 0 || $daysAhead > $this->maxDaysAhead) {
            return Result::fail('Fecha fuera de rango.', 'out_of_range', [
                'max_days_ahead' => $this->maxDaysAhead,
            ]);
        }

        $cafe = $this->cafeRepo->findById($cafeId);
        if (!$cafe) {
            return Result::fail('Café no encontrado.', 'cafe_not_found');
        }
        if (!$cafe->is_active || !$cafe->has_reservations) {
            return Result::fail('Este café no admite reservas.', 'cafe_not_reservable');
        }

        $pass = $this->productRepo->findById($passId);
        if (!$pass) {
            return Result::fail('Pase no encontrado.', 'pass_not_found');
        }
        if (!$pass->is_active || $pass->product_type !== 'pass') {
            return Result::fail('Pase no disponible.', 'pass_not_available');
        }

        $min = $pass->min_pax ?? 1;
        $max = $pass->max_pax;

        if ($guests < $min) {
            return Result::fail("Este pase requiere al menos $min persona(s).", 'pax_not_allowed');
        }
        if ($max !== null && $guests > $max) {
            return Result::fail("Este pase permite como máximo $max persona(s).", 'pax_not_allowed');
        }

        try {
            if (!$this->passMatchesCafe($pass, $cafe)) {
                return Result::fail('Este pase no aplica a este café.', 'pass_not_allowed');
            }
        } catch (JsonException) {
            return Result::fail('Configuración del pase inválida.', 'pass_config_invalid');
        }

        $duration = $pass->duration_minutes ?? 0;
        if ($duration <= 0) {
            return Result::fail('Duración de pase inválida.', 'pass_duration_invalid');
        }

        $capacityMax = $cafe->capacity_max;
        if ($capacityMax <= 0) {
            return Result::fail('Capacidad del café inválida.', 'cafe_capacity_invalid');
        }
        if ($guests > $capacityMax) {
            return Result::fail('Demasiadas personas para la capacidad del café.', 'capacity_exceeded');
        }

        $allowedStart = null;
        $allowedEnd = null;

        try {
            $attrs = $this->safeJsonObject((string) ($pass->attributes ?? ''));
            if (isset($attrs['allowed_start'])) {
                $allowedStart = $this->timeToMinutes((string) $attrs['allowed_start']);
            }
            if (isset($attrs['allowed_end'])) {
                $allowedEnd = $this->timeToMinutes((string) $attrs['allowed_end']);
            }
        } catch (JsonException) {
            return Result::fail('Atributos del pase inválidos.', 'pass_config_invalid');
        }

        $open = $this->timeToMinutes($cafe->opening_time);
        $close = $this->timeToMinutes($cafe->closing_time);
        $first = (int) (\ceil($open / $this->stepMinutes) * $this->stepMinutes);

        if ($daysAhead === 0) {
            $cafeTz = new DateTimeZone($cafe->timezone ?: 'Europe/Madrid');
            $now = new DateTimeImmutable('now', $cafeTz);
            $nowMins = ((int) $now->format('H') * 60) + (int) $now->format('i');
            $minStart = (int) (\ceil($nowMins / $this->stepMinutes) * $this->stepMinutes);
            if ($minStart > $first) {
                $first = $minStart;
            }
        }

        $reservations = $this->reservationRepo->findByCafeAndDate($cafeId, $dateYmd);

        $slotMeta = [];
        if ($this->timeSlotRepo !== null) {
            $rawSlots = $this->timeSlotRepo->findAvailableByDateFiltered($dateYmd, $cafeId, $guests);
            foreach ($rawSlots as $ts) {
                $key = \substr((string) $ts['slot_time'], 0, 5);
                $slotMeta[$key] = [
                    'capacity' => (int) $ts['total_capacity'],
                ];
            }
        }

        $slots = [];
        for ($t = $first; ($t + $duration) <= $close; $t += $this->stepMinutes) {
            if ($allowedStart !== null && $t < $allowedStart) {
                continue;
            }
            if ($allowedEnd !== null && ($t + $duration) > $allowedEnd) {
                continue;
            }

            $occupied = 0;
            $slotEnd = $t + $duration;

            foreach ($reservations as $r) {
                $resStart = $this->timeToMinutes((string) $r['reservation_time']);
                $resEnd = $resStart + (int) $r['pass_duration_minutes'];

                if ($resStart < $slotEnd && $resEnd > $t) {
                    $occupied += (int) $r['guest_count'];
                }
            }

            $slotKey = $this->minutesToHHMM($t);
            $slotCapacity = $slotMeta[$slotKey]['capacity'] ?? $capacityMax;
            $isAvailable = ($occupied + $guests) <= $slotCapacity;
            $slots[] = [
                'time' => $slotKey,
                'available' => $isAvailable,
                'occupied_guests' => $occupied,
                'total_capacity' => $slotCapacity,
            ];
        }

        return Result::ok([
            'cafe_id' => $cafeId,
            'pass_product_id' => $passId,
            'date' => $dateYmd,
            'guests' => $guests,
            'step_minutes' => $this->stepMinutes,
            'max_days_ahead' => $this->maxDaysAhead,
            'timezone' => $cafe->timezone,
            'slots' => $slots,
        ]);
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Override]
    public function assertSlotAvailable(int $cafeId, int $passId, string $dateYmd, string $timeHHMM, int $guests): Result
    {
        if (!\preg_match('/^\d{2}:\d{2}$/', $timeHHMM)) {
            return Result::fail('Hora inválida.', 'invalid_input');
        }

        $res = $this->getAvailableSlots($cafeId, $passId, $dateYmd, $guests);
        if (!$res->ok) {
            return $res;
        }

        $slots = (array) ($res->data['slots'] ?? []);
        $availableTimes = \array_column(
            \array_filter($slots, static fn (array $s) => ($s['available'] ?? false) === true),
            'time'
        );
        if (!\in_array($timeHHMM, $availableTimes, true)) {
            return Result::fail('No hay disponibilidad en esa hora.', 'no_availability');
        }

        return Result::ok();
    }

    #[Override]
    public function getAvailableCafesForReservation(): array
    {
        return $this->cafeRepo->findAvailableForReservation();
    }

    #[Override]
    public function getAvailableCafesById(): array
    {
        return $this->cafeRepo->findAvailableForReservationById();
    }

    #[Override]
    public function getAvailablePassesForReservation(): array
    {
        return $this->productRepo->findAvailablePasses();
    }

    /**
     * @throws JsonException
     */
    private function passMatchesCafe(ProductDTO $pass, CafeDTO $cafe): bool
    {
        $targetsRaw = $pass->target_cafe_types;
        if ($targetsRaw !== null && $targetsRaw !== '') {
            $targets = \json_decode($targetsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($targets) && !empty($targets) && !\in_array($cafe->category, $targets, true)) {
                return false;
            }
        }

        $animalTargetsRaw = $pass->target_animal_types;
        if ($animalTargetsRaw !== null && $animalTargetsRaw !== '') {
            $animalTargets = \json_decode($animalTargetsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($animalTargets) && !empty($animalTargets) && !\in_array($cafe->animal_type, $animalTargets, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws JsonException
     */
    private function safeJsonObject(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private function timeToMinutes(string $time): int
    {
        $parts = \explode(':', $time);
        $h = isset($parts[0]) ? (int) $parts[0] : 0;
        $m = isset($parts[1]) ? (int) $parts[1] : 0;

        return ($h * 60) + $m;
    }

    private function minutesToHHMM(int $mins): string
    {
        $h = \intdiv($mins, 60);
        $m = $mins % 60;

        return \str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . \str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}
