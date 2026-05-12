<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use PDOException;

/**
 * AnimalAdoptionRequestSeeder
 *
 * Marca 4 animales como adoptables y crea 10 solicitudes de adopción
 * con estados variados (pending, approved, rejected, withdrawn) para la demo.
 *
 * Depende de: AnimalSeeder, UserSeeder
 */
final class AnimalAdoptionRequestSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function run(): void
    {
        Logger::info('[AnimalAdoptionRequestSeeder] starting');

        $existing = (int) $this->db->query('SELECT COUNT(*) FROM animal_adoption_requests')->fetchColumn();
        if ($existing > 0) {
            Logger::info('[AnimalAdoptionRequestSeeder] already seeded — skipping');

            return;
        }

        // 1. Seleccionar 4 animales activos y marcarlos como adoptables
        $animals = $this->db->query(
            'SELECT id FROM animals WHERE deleted_at IS NULL ORDER BY RAND() LIMIT 4'
        )->fetchAll(PDO::FETCH_COLUMN);

        if (\count($animals) < 2) {
            Logger::warning('[AnimalAdoptionRequestSeeder] not enough animals — skipping');

            return;
        }

        $placeholders = \implode(',', \array_fill(0, \count($animals), '?'));
        $this->db->prepare("UPDATE animals SET is_adoptable = 1 WHERE id IN ({$placeholders})")
            ->execute($animals);

        // 2. Obtener 10 usuarios normales
        $users = $this->db->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'user' AND u.is_active = 1
             ORDER BY RAND() LIMIT 10"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (\count($users) < 4) {
            Logger::warning('[AnimalAdoptionRequestSeeder] not enough users — skipping');

            return;
        }

        // 3. Obtener un keeper para las revisiones
        $keeperId = $this->db->query(
            "SELECT u.id FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = 'keeper' AND u.is_active = 1
             LIMIT 1"
        )->fetchColumn();

        $keeperIdInt = ($keeperId !== false) ? (int) $keeperId : null;

        // 4. Definir 10 solicitudes con estados variados
        $messages = [
            'Tengo experiencia cuidando conejos y un jardín amplio.',
            'Mi familia lleva años queriendo adoptar un animal de compañía.',
            'Vivo sola pero tengo mucho tiempo libre para cuidar bien al animal.',
            'Buscamos un compañero para nuestro hijo de 7 años.',
            'Somos una familia con experiencia en cuidado de animales exóticos.',
            'Tengo casa propia y jardín vallado, ideal para animales.',
            'Trabajo desde casa y puedo dedicar atención constante.',
            'Mi veterinaria nos recomendó adoptar antes de comprar.',
            'Llevamos meses pensando en adoptar responsablemente.',
            'Queremos darle un hogar permanente a un animal necesitado.',
        ];

        /** @var array<int, array{status: string, reviewed_by: int|null, review_offset_days: int|null, days_ago: int}> */
        $requests = [
            ['status' => 'pending',   'reviewed_by' => null,         'review_offset_days' => null, 'days_ago' => 2],
            ['status' => 'pending',   'reviewed_by' => null,         'review_offset_days' => null, 'days_ago' => 5],
            ['status' => 'pending',   'reviewed_by' => null,         'review_offset_days' => null, 'days_ago' => 7],
            ['status' => 'pending',   'reviewed_by' => null,         'review_offset_days' => null, 'days_ago' => 10],
            ['status' => 'approved',  'reviewed_by' => $keeperIdInt, 'review_offset_days' => 12,   'days_ago' => 20],
            ['status' => 'approved',  'reviewed_by' => $keeperIdInt, 'review_offset_days' => 25,   'days_ago' => 30],
            ['status' => 'approved',  'reviewed_by' => $keeperIdInt, 'review_offset_days' => 40,   'days_ago' => 45],
            ['status' => 'rejected',  'reviewed_by' => $keeperIdInt, 'review_offset_days' => 18,   'days_ago' => 22],
            ['status' => 'rejected',  'reviewed_by' => $keeperIdInt, 'review_offset_days' => 35,   'days_ago' => 38],
            ['status' => 'withdrawn', 'reviewed_by' => null,         'review_offset_days' => null, 'days_ago' => 60],
        ];

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO animal_adoption_requests
                (animal_id, user_id, status, message, keeper_notes, reviewed_by, reviewed_at, created_at)
             VALUES
                (:animal_id, :user_id, :status, :message, :keeper_notes, :reviewed_by, :reviewed_at, :created_at)'
        );

        $total = 0;
        foreach ($requests as $i => $req) {
            $animalId  = (int) $animals[$i % \count($animals)];
            $userId    = (int) $users[$i % \count($users)];
            $createdAt = \date('Y-m-d H:i:s', \strtotime('-' . $req['days_ago'] . ' days'));

            $reviewedAt = null;
            if ($req['review_offset_days'] !== null) {
                $reviewedAt = \date('Y-m-d H:i:s', \strtotime('-' . $req['review_offset_days'] . ' days'));
            }

            $keeperNotes = match ($req['status']) {
                'approved' => 'Solicitante verificado. Domicilio apto. Aprobado sin observaciones.',
                'rejected' => 'No cumple los requisitos mínimos de espacio habitable.',
                default    => null,
            };

            try {
                $stmt->execute([
                    'animal_id'    => $animalId,
                    'user_id'      => $userId,
                    'status'       => $req['status'],
                    'message'      => $messages[$i],
                    'keeper_notes' => $keeperNotes,
                    'reviewed_by'  => $req['reviewed_by'],
                    'reviewed_at'  => $reviewedAt,
                    'created_at'   => $createdAt,
                ]);
                $total++;
            } catch (PDOException $e) {
                Logger::warning('[AnimalAdoptionRequestSeeder] insert skipped', [
                    'index'     => $i,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        Logger::info('[AnimalAdoptionRequestSeeder] done', ['requests_created' => $total]);
    }
}
