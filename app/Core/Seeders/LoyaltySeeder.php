<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * LoyaltySeeder
 *
 * Pobla el sistema de fidelización: tarjetas de sellos, recompensas canjeadas
 * y registro de animales vistos por usuario.
 *
 * Depende de: UserSeeder, ReservationSeeder, AnimalSeeder, CafeSeeder
 */
final class LoyaltySeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[LoyaltySeeder] starting');

        $users = $this->getRegularUsers();
        if (\count($users) === 0) {
            Logger::warning('[LoyaltySeeder] no regular users found — skipping');

            return;
        }

        $completedReservations = $this->getCompletedReservations();
        $animalsByCafe = $this->getAnimalsByCafe();
        $catalogIds = $this->getCatalogIds();

        if (\count($catalogIds) === 0) {
            Logger::warning('[LoyaltySeeder] no loyalty_reward_catalog entries — skipping loyalty_rewards');
        }

        Logger::info('[LoyaltySeeder] data loaded', [
            'users' => \count($users),
            'reservations' => \count($completedReservations),
            'catalog' => \count($catalogIds),
        ]);

        // Agrupar reservas completadas por user_id para contar visitas
        $reservationsByUser = [];
        foreach ($completedReservations as $res) {
            $reservationsByUser[(int) $res['user_id']][] = $res;
        }

        $cardInsert = $this->db->prepare(
            'INSERT INTO loyalty_cards
                (user_id, stamps, visits_count, total_rewards_redeemed, last_stamp_at)
             VALUES
                (:user_id, :stamps, :visits_count, :total_rewards_redeemed, :last_stamp_at)'
        );

        $rewardInsert = $this->db->prepare(
            'INSERT INTO loyalty_rewards
                (user_id, loyalty_card_id, reward_type, stamps_cost, redeemed_at,
                 used_at, expires_at, status, redemption_code, catalog_id)
             VALUES
                (:user_id, :loyalty_card_id, :reward_type, :stamps_cost, :redeemed_at,
                 :used_at, :expires_at, :status, :redemption_code, :catalog_id)'
        );

        $visitInsert = $this->db->prepare(
            'INSERT INTO user_animal_visits
                (user_id, animal_id, reservation_id, visited_at, interaction_rating)
             VALUES
                (:user_id, :animal_id, :reservation_id, :visited_at, :interaction_rating)'
        );

        $rewardTypes = ['drink_free', 'entry_free', 'discount_10', 'discount_20', 'merch_discount'];
        $stampCosts = [
            'drink_free' => 5,
            'entry_free' => 10,
            'discount_10' => 3,
            'discount_20' => 7,
            'merch_discount' => 4,
        ];

        $totalCards = 0;
        $totalRewards = 0;
        $totalVisits = 0;

        foreach ($users as $user) {
            $userId = (int) $user['id'];
            $userReservations = $reservationsByUser[$userId] ?? [];
            $visitsCount = \count($userReservations);

            if ($visitsCount === 0) {
                // Aun sin reservas, crear tarjeta vacía
                $visitsCount = 0;
                $stamps = 0;
                $lastStampAt = null;
            } else {
                // stamps = visitas recientes no canjeadas (al menos 1, máximo 9)
                $stamps = $visitsCount % 10 ?: \random_int(1, 9);
                $lastStampAt = \date('Y-m-d H:i:s', \strtotime('-' . \random_int(1, 14) . ' days'));
            }

            // total_rewards_redeemed = aprox visitas / 10 (cada 10 visitas canjea algo)
            $totalRedeemedCount = (int) \floor($visitsCount / 10);

            $cardInsert->execute([
                'user_id' => $userId,
                'stamps' => $stamps,
                'visits_count' => $visitsCount,
                'total_rewards_redeemed' => $totalRedeemedCount,
                'last_stamp_at' => $lastStampAt,
            ]);
            $cardId = (int) $this->db->lastInsertId();
            $totalCards++;

            // Crear recompensas canjeadas para usuarios con suficientes visitas
            if ($totalRedeemedCount > 0 && \count($catalogIds) > 0) {
                $rewardsToInsert = \min($totalRedeemedCount, 3); // máximo 3 por usuario para no inflar
                for ($i = 0; $i < $rewardsToInsert; $i++) {
                    $rewardType = $rewardTypes[\array_rand($rewardTypes)];
                    $cost = $stampCosts[$rewardType];
                    $daysAgo = \random_int(7, 90);
                    $redeemedAt = \date('Y-m-d H:i:s', \strtotime('-' . $daysAgo . ' days'));

                    // 70% de recompensas ya usadas, 20% pendientes, 10% expiradas
                    $rand = \random_int(1, 10);
                    if ($rand <= 7) {
                        $status = 'used';
                        $usedAt = \date('Y-m-d H:i:s', \strtotime($redeemedAt . ' +' . \random_int(1, 7) . ' days'));
                        $expiresAt = \date('Y-m-d H:i:s', \strtotime($redeemedAt . ' +30 days'));
                    } elseif ($rand <= 9) {
                        $status = 'pending';
                        $usedAt = null;
                        $expiresAt = \date('Y-m-d H:i:s', \strtotime('+' . \random_int(5, 25) . ' days'));
                    } else {
                        $status = 'expired';
                        $usedAt = null;
                        $expiresAt = \date('Y-m-d H:i:s', \strtotime('-' . \random_int(1, 30) . ' days'));
                    }

                    $catalogId = $catalogIds[\array_rand($catalogIds)];

                    $rewardInsert->execute([
                        'user_id' => $userId,
                        'loyalty_card_id' => $cardId,
                        'reward_type' => $rewardType,
                        'stamps_cost' => $cost,
                        'redeemed_at' => $redeemedAt,
                        'used_at' => $usedAt,
                        'expires_at' => $expiresAt,
                        'status' => $status,
                        'redemption_code' => $this->generateRedemptionCode(),
                        'catalog_id' => $catalogId,
                    ]);
                    $totalRewards++;
                }
            }

            // Crear user_animal_visits para reservas completadas de este usuario
            foreach ($userReservations as $reservation) {
                $cafeId = (int) $reservation['cafe_id'];
                $animals = $animalsByCafe[$cafeId] ?? [];

                if (\count($animals) === 0) {
                    continue;
                }

                // 2–3 animales vistos por reserva (sin duplicados)
                $visitCount = \min(\random_int(2, 3), \count($animals));
                $animalKeys = (array) \array_rand($animals, $visitCount);
                $visitedAt = $reservation['check_in_at'] ?? $reservation['created_at'];

                foreach ($animalKeys as $key) {
                    $animalId = (int) $animals[$key]['id'];
                    $rating = \random_int(1, 10) > 3 ? \random_int(3, 5) : \random_int(1, 2); // sesgado positivo

                    $visitInsert->execute([
                        'user_id' => $userId,
                        'animal_id' => $animalId,
                        'reservation_id' => (int) $reservation['id'],
                        'visited_at' => $visitedAt,
                        'interaction_rating' => $rating,
                    ]);
                    $totalVisits++;
                }
            }
        }

        Logger::info('[LoyaltySeeder] done', [
            'loyalty_cards' => $totalCards,
            'loyalty_rewards' => $totalRewards,
            'user_animal_visits' => $totalVisits,
        ]);
    }

    /** @return array<int, array{id: string}> */
    private function getRegularUsers(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'user' AND u.is_active = 1
             ORDER BY u.id"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array{id: string, user_id: string, cafe_id: string, check_in_at: string|null, created_at: string}>
     */
    private function getCompletedReservations(): array
    {
        $stmt = $this->db->query(
            "SELECT id, user_id, cafe_id, check_in_at, created_at
             FROM reservations
             WHERE status = 'completed'
             ORDER BY user_id, created_at"
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

    /** @return list<int> */
    private function getCatalogIds(): array
    {
        $stmt = $this->db->query(
            'SELECT id FROM loyalty_reward_catalog WHERE is_active = 1 ORDER BY id'
        );

        return \array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    private function generateRedemptionCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        $length = \strlen($chars) - 1;
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[\random_int(0, $length)];
        }

        return $code;
    }
}
