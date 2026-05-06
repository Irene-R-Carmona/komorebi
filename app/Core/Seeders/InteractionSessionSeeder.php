<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * InteractionSessionSeeder
 *
 * Crea sesiones de interacción retroactivas para todas las reservas
 * con status='completed' que tengan checked_in_at y checked_out_at.
 * Vincula cada reserva con los animales activos del café en ese momento.
 *
 * Depende de: ReservationSeeder, AnimalSeeder
 */
final class InteractionSessionSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[InteractionSessionSeeder] starting');

        $reservations = $this->getCompletedReservations();
        $animalsByCafe = $this->getAnimalsByCafe();

        if (\count($reservations) === 0) {
            Logger::warning('[InteractionSessionSeeder] no completed reservations — skipping');

            return;
        }

        Logger::info('[InteractionSessionSeeder] data loaded', [
            'reservations' => \count($reservations),
        ]);

        $stmt = $this->db->prepare(
            'INSERT INTO interaction_sessions
                (animal_id, reservation_id, start_time, end_time, intensity)
             VALUES
                (:animal_id, :reservation_id, :start_time, :end_time, :intensity)'
        );

        $intensities = ['low', 'medium', 'medium', 'high'];
        $total = 0;

        foreach ($reservations as $reservation) {
            $cafeId = (int) $reservation['cafe_id'];
            $animals = $animalsByCafe[$cafeId] ?? [];

            if (\count($animals) === 0) {
                continue;
            }

            $startTime = $reservation['check_in_at'];
            $endTime = $reservation['check_out_at'];

            foreach ($animals as $animal) {
                $stmt->execute([
                    'animal_id' => (int) $animal['id'],
                    'reservation_id' => (int) $reservation['id'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'intensity' => $intensities[\array_rand($intensities)],
                ]);
                $total++;
            }
        }

        Logger::info('[InteractionSessionSeeder] done', ['interaction_sessions_created' => $total]);
    }

    /**
     * @return array<int, array{id: string, cafe_id: string, check_in_at: string, check_out_at: string|null}>
     */
    private function getCompletedReservations(): array
    {
        $stmt = $this->db->query(
            "SELECT id, cafe_id, check_in_at, check_out_at
             FROM reservations
             WHERE status = 'completed'
               AND check_in_at IS NOT NULL
             ORDER BY id"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<int, array{id: string}>>
     */
    private function getAnimalsByCafe(): array
    {
        $stmt = $this->db->query(
            "SELECT id, cafe_id FROM animals WHERE current_status = 'active' ORDER BY cafe_id, id"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byCafe = [];
        foreach ($rows as $row) {
            $byCafe[(int) $row['cafe_id']][] = $row;
        }

        return $byCafe;
    }
}
