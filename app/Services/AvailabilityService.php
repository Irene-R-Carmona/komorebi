<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Result;
use App\Core\Time;
use JsonException;
use PDO;

final class AvailabilityService
{
    private PDO $db;
    private int $maxDaysAhead;
    private int $stepMinutes;

    public function __construct(?PDO $db = null, int $maxDaysAhead = 30, int $stepMinutes = 30)
    {
        $this->db = $db ?? Database::getConnection();
        $this->maxDaysAhead = $maxDaysAhead;
        $this->stepMinutes = $stepMinutes;
    }

    /**
     * @param integer $cafeId
     * @param integer $passId
     * @param string  $dateYmd
     * @param integer $guests
     * @return Result
     * @throws \DateMalformedStringException
     */
    public function getAvailableSlots(int $cafeId, int $passId, string $dateYmd, int $guests): Result
    {
        // Validación básica
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

        $cafe = $this->getCafe($cafeId);
        if (!$cafe) {
            return Result::fail('Café no encontrado.', 'cafe_not_found');
        }
        if ((int) $cafe['is_active'] !== 1 || (int) $cafe['has_reservations'] !== 1) {
            return Result::fail('Este café no admite reservas.', 'cafe_not_reservable');
        }

        $pass = $this->getPass($passId);
        if (!$pass) {
            return Result::fail('Pase no encontrado.', 'pass_not_found');
        }
        if ((int) $pass['is_active'] !== 1 || (string) $pass['product_type'] !== 'pass') {
            return Result::fail('Pase no disponible.', 'pass_not_available');
        }

        // Reglas pax
        $min = (int) ($pass['min_pax'] ?? 1);
        $max = $pass['max_pax'] !== null ? (int) $pass['max_pax'] : null;

        if ($guests < $min) {
            return Result::fail("Este pase requiere al menos $min persona(s).", 'pax_not_allowed');
        }
        if ($max !== null && $guests > $max) {
            return Result::fail("Este pase permite como máximo $max persona(s).", 'pax_not_allowed');
        }

        // Validar compatibilidad pase/café (tipo y animal) para consistencia
        try {
            if (!$this->passMatchesCafe($pass, $cafe)) {
                return Result::fail('Este pase no aplica a este café.', 'pass_not_allowed');
            }
        } catch (JsonException) {
            return Result::fail('Configuración del pase inválida.', 'pass_config_invalid');
        }

        $duration = (int) ($pass['duration_minutes'] ?? 0);
        if ($duration <= 0) {
            return Result::fail('Duración de pase inválida.', 'pass_duration_invalid');
        }

        $capacityMax = (int) $cafe['capacity_max'];
        if ($capacityMax <= 0) {
            return Result::fail('Capacidad del café inválida.', 'cafe_capacity_invalid');
        }
        if ($guests > $capacityMax) {
            return Result::fail('Demasiadas personas para la capacidad del café.', 'capacity_exceeded');
        }

        // Ventana opcional del pase
        $allowedStart = null;
        $allowedEnd = null;

        try {
            $attrs = $this->safeJsonObject((string) ($pass['attributes'] ?? ''));
            if (isset($attrs['allowed_start'])) {
                $allowedStart = $this->timeToMinutes((string) $attrs['allowed_start']);
            }
            if (isset($attrs['allowed_end'])) {
                $allowedEnd = $this->timeToMinutes((string) $attrs['allowed_end']);
            }
        } catch (JsonException) {
            return Result::fail('Atributos del pase inválidos.', 'pass_config_invalid');
        }

        $open = $this->timeToMinutes((string) $cafe['opening_time']);
        $close = $this->timeToMinutes((string) $cafe['closing_time']);

        // Primer slot: redondeo hacia arriba a múltiplo de step
        $first = (int) (\ceil($open / $this->stepMinutes) * $this->stepMinutes);

        // Si es hoy (negocio), no devolver horas pasadas
        if ($daysAhead === 0) {
            $now = Time::nowBusiness();
            $nowMins = ((int) $now->format('H') * 60) + (int) $now->format('i');
            $minStart = (int) (\ceil($nowMins / $this->stepMinutes) * $this->stepMinutes);
            if ($minStart > $first) {
                $first = $minStart;
            }
        }

        // Reservas contables del día
        $reservations = $this->getCountableReservationsForDay($cafeId, $dateYmd);

        $slots = [];
        for ($t = $first; ($t + $duration) <= $close; $t += $this->stepMinutes) {
            // Ventana allowed_start/end
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

                // solape: resStart < slotEnd AND resEnd > slotStart
                if ($resStart < $slotEnd && $resEnd > $t) {
                    $occupied += (int) $r['guests'];
                    if (($occupied + $guests) > $capacityMax) {
                        break;
                    }
                }
            }

            if (($occupied + $guests) <= $capacityMax) {
                $slots[] = $this->minutesToHHMM($t);
            }
        }

        return Result::ok([
            'cafe_id' => $cafeId,
            'pass_product_id' => $passId,
            'date' => $dateYmd,
            'guests' => $guests,
            'step_minutes' => $this->stepMinutes,
            'max_days_ahead' => $this->maxDaysAhead,
            'timezone' => Env::get('APP_BUSINESS_TIMEZONE', 'Asia/Tokyo'),
            'slots' => $slots,
        ]);
    }

    /**
     * Validación puntual para Reserva (anti-manipulación). Devuelve Result ok/fail.
     * @param integer $cafeId
     * @param integer $passId
     * @param string  $dateYmd
     * @param string  $timeHHMM
     * @param integer $guests
     * @return Result
     * @throws \DateMalformedStringException
     */
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
        if (!\in_array($timeHHMM, $slots, true)) {
            return Result::fail('No hay disponibilidad en esa hora.', 'no_availability');
        }

        return Result::ok();
    }

    private function getCafe(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, category, animal_type, opening_time, closing_time, capacity_max, is_active, has_reservations
            FROM cafes
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function getPass(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, product_type, is_active, duration_minutes, min_pax, max_pax,
                   target_cafe_types, target_animal_types, attributes
            FROM products
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function getCountableReservationsForDay(int $cafeId, string $dateYmd): array
    {
        $stmt = $this->db->prepare("
            SELECT reservation_time, pass_duration_minutes, guests
            FROM reservations
            WHERE cafe_id = :cid
              AND reservation_date = :d
              AND status IN ('pending','confirmed','active')
        ");
        $stmt->execute(['cid' => $cafeId, 'd' => $dateYmd]);

        return $stmt->fetchAll();
    }

    /**
     * @throws JsonException
     */
    private function passMatchesCafe(array $pass, array $cafe): bool
    {
        // Cafe types
        $targetsRaw = $pass['target_cafe_types'] ?? null;
        if ($targetsRaw !== null && $targetsRaw !== '') {
            $targets = \json_decode((string) $targetsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($targets) && !empty($targets) && !\in_array((string) $cafe['category'], $targets, true)) {
                return false;
            }
        }

        // Animal types
        $animalTargetsRaw = $pass['target_animal_types'] ?? null;
        if ($animalTargetsRaw !== null && $animalTargetsRaw !== '') {
            $animalTargets = \json_decode((string) $animalTargetsRaw, true, 512, JSON_THROW_ON_ERROR);
            if (\is_array($animalTargets) && !empty($animalTargets) && !\in_array((string) $cafe['animal_type'], $animalTargets, true)) {
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
