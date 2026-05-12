<?php

declare(strict_types=1);

namespace App\Core\Seeders;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use Random\RandomException;
use Throwable;

/**
 * AnimalIncidentSeeder
 *
 * Crea incidentes de animales de prueba para dashboard del keeper
 */
final class AnimalIncidentSeeder
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        Logger::info('AnimalIncidentSeeder: starting');

        // Limpiar incidentes de ejecuciones previas (datos de demo sin unique key natural)
        $this->db->exec('DELETE FROM animal_incidents');

        // Obtener animales existentes
        $stmt = $this->db->query('
            SELECT a.id, a.cafe_id, a.name
            FROM animals a
        ');
        $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($animals)) {
            Logger::warning('AnimalIncidentSeeder: no animals found');

            return;
        }

        // Obtener usuarios staff para logged_by (buscar roles que existen)
        $stmt = $this->db->query("
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.code IN ('manager', 'supervisor', 'keeper', 'reception')
            AND u.cafe_id IS NOT NULL
            LIMIT 20
        ");
        $staffUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($staffUsers)) {
            Logger::warning('AnimalIncidentSeeder: no staff users');

            return;
        }

        $incidents = [
            [
                'type' => 'health',
                'severity' => 'low',
                'description' => 'Estornudos frecuentes durante la última hora. Se movió a zona de descanso para observación. Temperatura normal.',
                'status' => 'resolved',
                'resolved' => true,
            ],
            [
                'type' => 'behavior',
                'severity' => 'medium',
                'description' => 'Mostró señales de estrés (orejas hacia atrás, cola rígida) con grupo de visitantes ruidosos. Se necesita pausa de 30 minutos.',
                'status' => 'open',
                'resolved' => false,
            ],
            [
                'type' => 'health',
                'severity' => 'high',
                'description' => 'Rechazo de comida durante dos comidas consecutivas. Aletargado. Vet contactado - revisando mañana 10:00.',
                'status' => 'monitoring',
                'resolved' => false,
            ],
            [
                'type' => 'behavior',
                'severity' => 'low',
                'description' => 'Juego muy activo con compañeros - derribó recipiente de agua. Zona limpiada. Todo normal.',
                'status' => 'resolved',
                'resolved' => true,
            ],
            [
                'type' => 'injury',
                'severity' => 'medium',
                'description' => 'Pequeño arañazo en almohadilla trasera izquierda (2cm). Limpiado con antiseptico. Revisando evolución cada 4h.',
                'status' => 'monitoring',
                'resolved' => false,
            ],
            [
                'type' => 'health',
                'severity' => 'low',
                'description' => 'Pelaje sin brillo habitual. Aumentado frecuencia de cepillado. Revisión dietaria programada.',
                'status' => 'open',
                'resolved' => false,
            ],
            [
                'type' => 'behavior',
                'severity' => 'low',
                'description' => 'Extrañamente activo después de siesta. Saltó repetidamente en área de juegos. Comportamiento normal - "zoomies".',
                'status' => 'resolved',
                'resolved' => true,
            ],
            [
                'type' => 'injury',
                'severity' => 'high',
                'description' => 'Cojea de pata delantera derecha. Sin heridas visibles. Restringido movimiento. Vet viene en 2h.',
                'status' => 'open',
                'resolved' => false,
            ],
            [
                'type' => 'health',
                'severity' => 'high',
                'description' => 'Tos persistente. Revisión veterinaria urgente programada para hoy.',
                'status' => 'open',
                'resolved' => false,
            ],
            [
                'type' => 'behavior',
                'severity' => 'low',
                'description' => 'Escondido más tiempo de lo habitual después de sesión intensa. Comportamiento normal ahora.',
                'status' => 'resolved',
                'resolved' => true,
            ],
        ];

        $stmt = $this->db->prepare('
            INSERT INTO animal_incidents (
                animal_id, incident_type, description, severity, status, reported_by, resolved_at, resolved_by, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $count = 0;
        foreach ($animals as $animal) {
            // Crear 1-2 incidentes por animal (aleatorio)
            $numIncidents = \random_int(0, 2);

            for ($i = 0; $i < $numIncidents; $i++) {
                $incident = $incidents[\array_rand($incidents)];
                $loggedBy = $staffUsers[\array_rand($staffUsers)];
                $resolvedBy = $incident['resolved'] ? $staffUsers[\array_rand($staffUsers)] : null;
                $resolvedAt = $incident['resolved'] ? \date('Y-m-d H:i:s', \strtotime('-' . \random_int(1, 24) . ' hours')) : null;

                try {
                    $stmt->execute([
                        $animal['id'],                 // animal_id
                        $incident['type'],             // incident_type
                        $incident['description'],      // description
                        $incident['severity'],         // severity
                        $incident['status'],           // status
                        $loggedBy,                     // reported_by
                        $resolvedAt,                   // resolved_at
                        $resolvedBy,                   // resolved_by
                    ]);
                    $count++;
                } catch (Throwable $e) {
                    Logger::error('AnimalIncidentSeeder: insert failed', ['animal_id' => $animal['id'], 'exception' => $e->getMessage()]);
                }
            }
        }

        Logger::info('AnimalIncidentSeeder: completed', ['created' => $count]);
    }
}
