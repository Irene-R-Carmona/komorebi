<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Models\Traits\ValidatesData;
use PDO;

/**
 * Modelo Animal
 *
 * Gestiona los animales de los cafés.
 */
final class Animal
{
    use ValidatesData;

    private ?PDO $db = null;

    // ─────────────────────────────────────────────────────────────
    // Constantes
    // ─────────────────────────────────────────────────────────────

    /** Estados del animal */
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_RESTING = 'resting';
    public const string STATUS_SICK = 'sick';
    public const string STATUS_RETIRED = 'retired';

    public const array VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_RESTING,
        self::STATUS_SICK,
        self::STATUS_RETIRED,
    ];

    /** Estados de ánimo para logs */
    public const array VALID_MOODS = [
        'happy',
        'calm',
        'stressed',
        'aggressive',
        'tired',
    ];

    /** Campos SELECT */
    private const array SELECT_FIELDS = [
        'a.id',
        'a.cafe_id',
        'a.current_zone_id',
        'a.name',
        'a.species_type',
        'a.age',
        'a.personality',
        'a.description',
        'a.interaction_level',
        'a.attributes',
        'a.image_url',
        'a.current_status',
        'a.last_check_at',
        'a.last_health_check',
        'a.deleted_at',
    ];

    // ─────────────────────────────────────────────────────────────
    // Constructor
    // ─────────────────────────────────────────────────────────────

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    // ─────────────────────────────────────────────────────────────
    // Búsqueda
    // ─────────────────────────────────────────────────────────────

    /**
     * Busca un animal por ID.
     */
    public function findById(int $id): ?array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields,
                       c.name AS cafe_name, c.slug AS cafe_slug,
                       z.name AS zone_name
                FROM animals a
                JOIN cafes c ON c.id = a.cafe_id
                LEFT JOIN cafe_zones z ON z.id = a.current_zone_id
                WHERE a.id = :id LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($animal) {
            $animal = $this->decodeJsonFields($animal);
        }

        return $animal ?: null;
    }

    // ─────────────────────────────────────────────────────────────
    // Listados
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene animales de un café.
     */
    public function findByCafe(int $cafeId, ?string $status = null): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        $where = ['a.cafe_id = :cafe_id'];
        $params = ['cafe_id' => $cafeId];

        if ($status !== null) {
            $this->validateInArray($status, self::VALID_STATUSES, 'status');
            $where[] = 'a.current_status = :status';
            $params['status'] = $status;
        }

        $whereClause = \implode(' AND ', $where);

        $sql = "SELECT $fields, z.name AS zone_name
                FROM animals a
                LEFT JOIN cafe_zones z ON z.id = a.current_zone_id
                WHERE {$whereClause}
                ORDER BY a.name ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return \array_map([$this, 'decodeJsonFields'], $rows);
    }

    /**
     * Obtiene animales activos disponibles para interacción.
     */
    public function findAvailableForInteraction(int $cafeId): array
    {
        return $this->findByCafe($cafeId, self::STATUS_ACTIVE);
    }

    /**
     * Obtiene animales que necesitan descanso.
     * (Basado en reglas de especie y tiempo de interacción)
     */
    public function findNeedingRest(int $cafeId): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);
        // Evitar subconsulta correlacionada por fila: pre-aggregamos la última end_time por animal
        $sql = "SELECT $fields, sr.max_consecutive_minutes, sr.min_rest_minutes,
                       TIMESTAMPDIFF(MINUTE, COALESCE(s.last_end, a.last_check_at), NOW()) as minutes_since_rest
                FROM animals a
                JOIN species_rules sr ON sr.species_key = a.species_type
                LEFT JOIN (
                    SELECT animal_id, MAX(end_time) AS last_end
                    FROM interaction_sessions
                    GROUP BY animal_id
                ) s ON s.animal_id = a.id
                WHERE a.cafe_id = :cafe_id
                  AND a.current_status = 'active'
                  AND TIMESTAMPDIFF(MINUTE, COALESCE(s.last_end, a.last_check_at), NOW()) > sr.max_consecutive_minutes
                ORDER BY minutes_since_rest DESC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el número de especies distintas con animales activos.
     */
    public function countDistinctSpecies(): int
    {
        $stmt = $this->getDb()->query(
            "SELECT COUNT(DISTINCT species_type) FROM animals WHERE current_status IN ('active', 'resting')"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene animales por especie.
     */
    public function findBySpecies(string $speciesType): array
    {
        $fields = \implode(', ', self::SELECT_FIELDS);

        $sql = "SELECT $fields, c.name AS cafe_name, c.slug AS cafe_slug
                FROM animals a
                JOIN cafes c ON c.id = a.cafe_id
                WHERE a.species_type = :species
                  AND a.current_status IN ('active', 'resting')
                  AND c.is_active = 1
                ORDER BY c.name , a.name ";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('species', $speciesType, PDO::PARAM_STR);
        $stmt->execute();

        return \array_map([$this, 'decodeJsonFields'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ─────────────────────────────────────────────────────────────
    // Estado y Bienestar
    // ─────────────────────────────────────────────────────────────

    /**
     * Actualiza el estado de un animal.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $this->validateInArray($status, self::VALID_STATUSES, 'status');

        $stmt = $this->getDb()->prepare(
            'UPDATE animals SET current_status = :status, last_check_at = NOW() WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Mueve un animal a descanso.
     */
    public function sendToRest(int $id, ?int $restZoneId = null): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals
             SET current_status = :status,
                 current_zone_id = :zone_id,
                 last_check_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => self::STATUS_RESTING,
            'zone_id' => $restZoneId,
        ]);
    }

    /**
     * Devuelve un animal a estado activo.
     */
    public function activateFromRest(int $id, ?int $interactionZoneId = null): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals
             SET current_status = :status,
                 current_zone_id = :zone_id,
                 last_check_at = NOW()
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'status' => self::STATUS_ACTIVE,
            'zone_id' => $interactionZoneId,
        ]);
    }

    /**
     * Cambia la zona actual de un animal.
     */
    public function updateZone(int $id, ?int $zoneId): bool
    {
        $stmt = $this->getDb()->prepare(
            'UPDATE animals SET current_zone_id = :zone_id WHERE id = :id'
        );

        return $stmt->execute(['id' => $id, 'zone_id' => $zoneId]);
    }

    // ─────────────────────────────────────────────────────────────
    // Logs de Estado (Keeper)
    // ─────────────────────────────────────────────────────────────

    /**
     * Registra un log de estado del animal.
     * @throws \Throwable
     */
    public function logStatus(
        int $animalId,
        int $keeperId,
        string $mood,
        int $healthScore = 10,
        ?string $notes = null
    ): int {
        // Validar valores básicos
        $this->validateInArray($mood, self::VALID_MOODS, 'mood');
        $healthScore = \max(1, \min(10, $healthScore));

        // Obtener estado anterior del animal para registrar old_status/new_status
        $stmt = $this->getDb()->prepare('SELECT current_status FROM animals WHERE id = :id');
        $stmt->execute(['id' => $animalId]);
        $oldStatus = $stmt->fetchColumn() ?: null;
        $newStatus = $oldStatus; // este método no cambia el estado por sí mismo

        // Construir reason JSON de forma segura
        $reasonPayload = ['mood' => $mood, 'health_score' => $healthScore, 'notes' => $notes];

        try {
            $reason = \json_encode($reasonPayload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Fallback silencioso para entornos sin soporte de JSON_THROW_ON_ERROR
            $reason = \json_encode($reasonPayload);
        }

        // Usar transacción para mantener coherencia entre log y last_check_at
        $this->getDb()->beginTransaction();

        try {
            $sql = 'INSERT INTO animal_status_log
                (animal_id, old_status, new_status, reason, logged_by)
                VALUES (:animal_id, :old_status, :new_status, :reason, :logged_by)';

            $insert = $this->getDb()->prepare($sql);
            $insert->execute([
                'animal_id' => $animalId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'logged_by' => $keeperId,
            ]);

            $logId = (int) $this->getDb()->lastInsertId();

            // Actualizar last_check_at del animal
            $up = $this->getDb()->prepare('UPDATE animals SET last_check_at = NOW() WHERE id = :id');
            $up->execute(['id' => $animalId]);

            $this->getDb()->commit();

            return $logId;
        } catch (\Throwable $e) {
            $this->getDb()->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene el historial de logs de un animal.
     */
    public function getStatusLogs(int $animalId, int $days = 7): array
    {
        $stmt = $this->getDb()->prepare(
            'SELECT l.id, l.animal_id, l.old_status, l.new_status, l.reason, l.logged_by, l.created_at,
                    u.name AS keeper_name
             FROM animal_status_log l
             LEFT JOIN users u ON u.id = l.logged_by
             WHERE l.animal_id = :animal_id
               AND l.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             ORDER BY l.created_at DESC'
        );

        $stmt->bindValue('animal_id', $animalId, PDO::PARAM_INT);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r = $this->decodeReasonEntry($r);
        }

        return $rows;
    }

    /**
     * Obtiene el último log de cada animal de un café.
     */
    public function getLatestLogsForCafe(int $cafeId): array
    {
        // Derivamos el último log por animal usando una subconsulta agregada para evitar N subqueries
        $sql = 'SELECT a.id, a.name, a.current_status, a.last_check_at,
                       l.old_status, l.new_status, l.reason, l.created_at,
                       u.name AS keeper_name, l.logged_by
                FROM animals a
                LEFT JOIN (
                    SELECT l2.animal_id, l2.old_status, l2.new_status, l2.reason, l2.created_at, l2.logged_by
                    FROM animal_status_log l2
                    JOIN (
                        SELECT animal_id, MAX(created_at) AS ma
                        FROM animal_status_log
                        GROUP BY animal_id
                    ) m ON l2.animal_id = m.animal_id AND l2.created_at = m.ma
                ) l ON l.animal_id = a.id
                LEFT JOIN users u ON u.id = l.logged_by
                WHERE a.cafe_id = :cafe_id
                ORDER BY a.name';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r = $this->decodeReasonEntry($r);
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────
    // Incidentes
    // ─────────────────────────────────────────────────────────────

    /**
     * Registra un incidente.
     */
    public function reportIncident(
        int $animalId,
        int $reportedBy,
        string $incidentType,
        string $severity = 'medium',
        ?string $description = null
    ): int {
        $validTypes = ['bite', 'scratch', 'illness', 'accident', 'fight', 'other'];
        $this->validateInArray($incidentType, $validTypes, 'incident_type');

        $validSeverities = ['low', 'medium', 'high'];
        $this->validateInArray($severity, $validSeverities, 'severity');

        $sql = 'INSERT INTO animal_incidents
                (animal_id, reported_by, incident_type, severity, description)
                VALUES (:animal_id, :reported_by, :incident_type, :severity, :description)';

        $this->getDb()->prepare($sql)->execute([
            'animal_id' => $animalId,
            'reported_by' => $reportedBy,
            'incident_type' => $incidentType,
            'severity' => $severity,
            'description' => $description,
        ]);

        return (int) $this->getDb()->lastInsertId();
    }

    /**
     * Obtiene incidentes abiertos de un café.
     */
    public function getOpenIncidents(int $cafeId): array
    {
        $sql = 'SELECT i.id, i.animal_id, i.incident_type, i.description, i.severity,
                       i.reported_by, i.created_at,
                       a.name AS animal_name, u.name AS reporter_name
                FROM animal_incidents i
                JOIN animals a ON a.id = i.animal_id
                LEFT JOIN users u ON u.id = i.reported_by
                WHERE a.cafe_id = :cafe_id
                ORDER BY i.severity DESC, i.created_at DESC';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resuelve un incidente.
     *
     * Nota: Actualmente la tabla `animal_incidents` definida en las migraciones no contiene
     * una columna `status`. El módulo "keeper" que podría introducir estados (open/resolved)
     * no está implementado aún y está sujeto a cambios. Para mantener compatibilidad con
     * el esquema actual y evitar dependencias frágiles, esta función elimina el incidente
     * (borrado) como acción temporal para marcarlo resuelto. Cuando el módulo keeper esté
     * implementado, cambiar a un soft-delete o actualizar la columna de estado según el esquema.
     */
    public function resolveIncident(int $incidentId): bool
    {
        // Comprobar esquema en runtime y construir UPDATE dinámico si las columnas existen
        $hasStatus = false;
        $hasResolvedAt = false;

        try {
            $check = $this->getDb()->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'animal_incidents'
                 AND COLUMN_NAME IN ('status','resolved_at')"
            );
            $cols = $check->fetchAll(PDO::FETCH_COLUMN);
            $hasStatus = \in_array('status', $cols, true);
            $hasResolvedAt = \in_array('resolved_at', $cols, true);
        } catch (\Throwable) {
            // ignore and fallback to delete below
        }

        if ($hasStatus) {
            $parts = [];
            $parts[] = "status = 'resolved'";
            if ($hasResolvedAt) {
                $parts[] = 'resolved_at = NOW()';
            }
            $sql = 'UPDATE animal_incidents SET ' . \implode(', ', $parts) . ' WHERE id = :id';

            try {
                $stmt = $this->getDb()->prepare($sql);
                $ok = $stmt->execute(['id' => $incidentId]);
                if ($ok) {
                    return true;
                }
            } catch (\Throwable) {
                // fallthrough to delete
            }
        }

        // Fallback: eliminar el incidente (temporal)
        $stmt = $this->getDb()->prepare('DELETE FROM animal_incidents WHERE id = :id');
        $stmt->bindValue('id', $incidentId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // ─────────────────────────────────────────────────────────────
    // Relaciones entre animales
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene las relaciones de un animal.
     */
    public function getRelationships(int $animalId): array
    {
        $sql = 'SELECT
                    IF(r.animal_a = :id, r.animal_b, r.animal_a) AS related_id,
                    a.name AS related_name,
                    r.type
                FROM animal_relationships r
                JOIN animals a ON a.id = IF(r.animal_a = :id, r.animal_b, r.animal_a)
                WHERE r.animal_a = :id OR r.animal_b = :id';

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('id', $animalId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene animales incompatibles (relación hostile).
     */
    public function getIncompatible(int $animalId): array
    {
        $relationships = $this->getRelationships($animalId);

        return \array_values(\array_filter($relationships, static fn($r) => $r['type'] === 'hostile'));
    }

    // ─────────────────────────────────────────────────────────────
    // Estadísticas
    // ─────────────────────────────────────────────────────────────

    /**
     * Obtiene estadísticas de bienestar de un café.
     */
    public function getWelfareStats(int $cafeId): array
    {
        // Conteo por estado
        $stmt = $this->getDb()->prepare(
            'SELECT current_status, COUNT(*) as count
             FROM animals WHERE cafe_id = :cafe_id
             GROUP BY current_status'
        );
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->execute();

        $byStatus = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byStatus[$row['current_status']] = (int) $row['count'];
        }

        // Promedio de salud (último log de cada animal) usando subconsulta agregada para último log
        $sql = "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(l.reason, '$.health_score')) AS UNSIGNED)) as avg_health
             FROM animals a
             LEFT JOIN (
                 SELECT l2.animal_id, l2.reason
                 FROM animal_status_log l2
                 JOIN (
                     SELECT animal_id, MAX(created_at) AS ma
                     FROM animal_status_log
                     GROUP BY animal_id
                 ) m ON l2.animal_id = m.animal_id AND l2.created_at = m.ma
             ) l ON l.animal_id = a.id
             WHERE a.cafe_id = :cafe_id";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
        $stmt->execute();
        $avgHealth = (float) ($stmt->fetchColumn() ?: 0);

        // Incidentes abiertos (filtramos por estado 'open' si existe, sino contamos todos)
        $openIncidents = 0;

        try {
            // Intentar contar solo abiertos si la columna existe
            $check = $this->getDb()->prepare(
                "SELECT COUNT(*) FROM animal_incidents i
                 JOIN animals a ON a.id = i.animal_id
                 WHERE a.cafe_id = :cafe_id AND i.status = 'open'"
            );
            $check->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
            $check->execute();
            $openIncidents = (int) $check->fetchColumn();
        } catch (\Throwable) {
            // Fallback
            $stmt = $this->getDb()->prepare(
                'SELECT COUNT(*) FROM animal_incidents i
                 JOIN animals a ON a.id = i.animal_id
                 WHERE a.cafe_id = :cafe_id'
            );
            $stmt->bindValue('cafe_id', $cafeId, PDO::PARAM_INT);
            $stmt->execute();
            $openIncidents = (int) $stmt->fetchColumn();
        }

        return [
            'by_status' => $byStatus,
            'total' => \array_sum($byStatus),
            'avg_health' => \round($avgHealth, 1),
            'open_incidents' => $openIncidents,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function decodeJsonFields(array $animal): array
    {
        if (isset($animal['attributes']) && \is_string($animal['attributes'])) {
            $animal['attributes'] = \json_decode($animal['attributes'], true) ?? [];
        }

        return $animal;
    }

    /**
     * Decodifica el campo `reason` de un registro de log y añade mood/health_score/notes
     * al array proporcionado (no sobrescribe el contenido original salvo claves nuevas).
     *
     * @param array $row Registro que puede contener 'reason' JSON
     * @return array Registro enriquecido
     */
    private function decodeReasonEntry(array $row): array
    {
        if (isset($row['reason']) && \is_string($row['reason'])) {
            $decoded = \json_decode($row['reason'], true) ?: [];
            $row['mood'] = $decoded['mood'] ?? null;
            $row['health_score'] = $decoded['health_score'] ?? null;
            $row['notes'] = $decoded['notes'] ?? null;
        } else {
            $row['mood'] ??= null;
            $row['health_score'] ??= null;
            $row['notes'] ??= null;
        }

        return $row;
    }
}
