<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOStatement;

/**
 * AnimalRelationshipSeeder
 *
 * Crea relaciones entre animales del mismo café.
 * La PK es (animal_a, animal_b) — sin id, con restricción animal_a < animal_b
 * para evitar pares duplicados invertidos.
 *
 * Depende de: AnimalSeeder
 */
final class AnimalRelationshipSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[AnimalRelationshipSeeder] starting');

        $animalsByCafe = $this->getAnimalsByCafe();

        if (empty($animalsByCafe)) {
            Logger::warning('[AnimalRelationshipSeeder] no animals found — skipping');

            return;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO animal_relationships (animal_a, animal_b, type)
             VALUES (:animal_a, :animal_b, :type)'
        );

        $relationTypes = ['friendly', 'friendly', 'friendly', 'family', 'hostile']; // 60% friendly, 20% family, 20% hostile
        $total = 0;

        foreach ($animalsByCafe as $animals) {
            $total += $this->insertCafeRelationships($stmt, $animals, $relationTypes);
        }

        Logger::info('[AnimalRelationshipSeeder] done', ['relationships_created' => $total]);
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

    /**
     * @param array<int, array{id: string}> $animals
     * @param array<int, string> $relationTypes
     */
    private function insertCafeRelationships(PDOStatement $stmt, array $animals, array $relationTypes): int
    {
        $count = \count($animals);
        if ($count < 2) {
            return 0;
        }

        $maxPairs = (int) \floor($count * ($count - 1) / 2);
        $targetPairs = $count <= 5 ? $maxPairs : (int) \ceil($maxPairs * 0.6);
        $createdPairs = [];
        $total = 0;

        for ($attempt = 0; $attempt < $targetPairs * 3 && \count($createdPairs) < $targetPairs; $attempt++) {
            $idxA = \random_int(0, $count - 1);
            $idxB = \random_int(0, $count - 1);

            if ($idxA === $idxB) {
                continue;
            }

            $aId = (int) $animals[$idxA]['id'];
            $bId = (int) $animals[$idxB]['id'];

            if ($aId > $bId) {
                [$aId, $bId] = [$bId, $aId];
            }

            $pairKey = $aId . '-' . $bId;
            if (isset($createdPairs[$pairKey])) {
                continue;
            }

            $stmt->execute([
                'animal_a' => $aId,
                'animal_b' => $bId,
                'type' => $relationTypes[\array_rand($relationTypes)],
            ]);
            $createdPairs[$pairKey] = true;
            $total++;
        }

        return $total;
    }
}
