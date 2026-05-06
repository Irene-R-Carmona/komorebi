<?php

declare(strict_types=1);

namespace App\Repositories;

use Override;
use PDO;

/**
 * Repositorio para sesiones de interacción usuario-animal.
 *
 * Escribe en `interaction_sessions` durante el flujo de check-in/out
 * de reservas, permitiendo al DashboardService calcular los animales
 * más populares mediante LEFT JOIN.
 */
final class InteractionSessionRepository extends AbstractRepository
{
    #[Override]
    protected function getTable(): string
    {
        return 'interaction_sessions';
    }

    #[Override]
    protected function getSelectFields(): array
    {
        return ['id', 'animal_id', 'reservation_id', 'start_time', 'end_time', 'intensity'];
    }

    /**
     * Crea sesiones de interacción para todos los animales activos del café
     * al momento del check-in de una reserva.
     */
    public function createForReservation(int $reservationId, int $cafeId): void
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id FROM animals WHERE cafe_id = :cafe_id AND current_status = 'active' AND deleted_at IS NULL"
        );
        $stmt->execute(['cafe_id' => $cafeId]);
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (\count($animals) === 0) {
            return;
        }

        $insert = $this->getDb()->prepare(
            'INSERT INTO interaction_sessions
                (animal_id, reservation_id, start_time, intensity)
             VALUES
                (:animal_id, :reservation_id, NOW(), :intensity)'
        );

        $intensities = ['low', 'medium', 'medium', 'high']; // sesgado hacia medium

        foreach ($animals as $animal) {
            $insert->execute([
                'animal_id' => (int) $animal['id'],
                'reservation_id' => $reservationId,
                'intensity' => $intensities[\array_rand($intensities)],
            ]);
        }
    }

    /**
     * Cierra las sesiones de interacción abiertas de una reserva al hacer checkout.
     */
    public function closeForReservation(int $reservationId): void
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE interaction_sessions
             SET end_time = NOW()
             WHERE reservation_id = :reservation_id
               AND end_time IS NULL'
        );
        $stmt->execute(['reservation_id' => $reservationId]);
    }
}
