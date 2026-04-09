<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BaseService;
use App\Core\Database;
use App\Core\Result;
use App\Models\AuditLog;
use App\Repositories\CafeRepository;
use App\Repositories\Contracts\CafeRepositoryInterface;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Servicio de gestión de cafés
 *
 * Encapsula toda la lógica de negocio relacionada con los cafés.
 *
 * @package Komorebi\Services
 */
final class CafeService extends BaseService
{
    private CafeRepositoryInterface $cafeRepo;
    private PDO $db; // Mantener temporalmente para queries complejas legacy

    public function __construct(?CafeRepositoryInterface $cafeRepo = null)
    {
        $this->cafeRepo = $cafeRepo ?? new CafeRepository();
        $this->db = Database::getConnection(); // Usar getConnection()
    }

    /**
     * Obtiene todos los cafés con filtros opcionales
     *
     * @param array   $filters Filtros opcionales (category, animal_type, is_active)
     * @param integer $limit   Límite de resultados
     * @param integer $offset  Offset para paginación
     * @return array Lista de cafés
     */
    public function getAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        // Para filtros simples, usar métodos específicos del repositorio
        if (isset($filters['is_active']) && (int)$filters['is_active'] === 1 && empty($filters['category']) && empty($filters['animal_type'])) {
            return $this->cafeRepo->findActive();
        }

        if (isset($filters['category']) && empty($filters['animal_type']) && !isset($filters['is_active'])) {
            return $this->cafeRepo->findByCategory($filters['category']);
        }

        return $this->cafeRepo->findFiltered($filters, $limit, $offset);
    }

    /**
     * Obtiene un café por ID
     *
     * @param integer $id ID del café
     * @return array|null Datos del café o null si no existe
     */
    public function getById(int $id): ?array
    {
        return $this->cafeRepo->findById($id);
    }

    /**
     * Crea un nuevo café
     *
     * @param array $data Datos del café
     * @return Result
     * @throws RuntimeException Si falla la creación en base de datos
     */
    public function create(array $data): Result
    {
        // Validación de campos requeridos
        $required = ['name', 'slug', 'location'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Result::fail("El campo '$field' es obligatorio", 'validation_error');
            }
        }

        // Preparar datos con valores por defecto
        $preparedData = [
            'name' => \trim($data['name']),
            'japanese_name' => !empty($data['japanese_name']) ? \trim($data['japanese_name']) : null,
            'slug' => \trim($data['slug']),
            'location' => \trim($data['location']),
            'category' => $data['category'] ?? '',
            'animal_type' => $data['animal_type'] ?? '',
            'description' => !empty($data['description']) ? \trim($data['description']) : null,
            'price_per_hour' => (int) ($data['price_per_hour'] ?? 0),
            'capacity_max' => (int) ($data['capacity_max'] ?? 0),
            'opening_time' => $data['opening_time'] ?? '09:00:00',
            'closing_time' => $data['closing_time'] ?? '18:00:00',
            'image_url' => !empty($data['image_url']) ? \trim($data['image_url']) : null,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 0,
            'has_reservations' => isset($data['has_reservations']) ? (int) $data['has_reservations'] : 0,
        ];

        try {
            $cafeId = $this->cafeRepo->create($preparedData);

            // Log de auditoría
            AuditLog::log('create_cafe', 'cafe', $cafeId, null, $preparedData);

            return Result::ok($cafeId);
        } catch (PDOException $e) {
            throw new RuntimeException('Error al crear el café: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza un café existente
     *
     * @param integer $id   ID del café
     * @param array   $data Datos a actualizar
     * @return Result
     * @throws RuntimeException Si falla la actualización en base de datos
     */
    public function update(int $id, array $data): Result
    {
        // Verificar que el café existe (usa repositorio)
        if (!$this->cafeRepo->findById($id)) {
            return Result::fail('Café no encontrado', 'not_found');
        }

        // Campos actualizables
        $allowedFields = [
            'name',
            'japanese_name',
            'slug',
            'location',
            'category',
            'animal_type',
            'description',
            'image_url',
            'opening_time',
            'closing_time',
            'price_per_hour',
            'capacity_max',
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (\array_key_exists($field, $data)) {
                // Procesar valor según el tipo de campo
                if (\in_array($field, ['price_per_hour', 'capacity_max'], true)) {
                    $params[$field] = (int) $data[$field];
                } else {
                    $params[$field] = !empty($data[$field]) ? \trim($data[$field]) : null;
                }
            }
        }

        if (empty($params)) {
            return Result::ok(true); // No hay nada que actualizar
        }

        try {
            $this->cafeRepo->update($id, $params);

            // Log de auditoría
            AuditLog::log('update_cafe', 'cafe', $id, null, $data);

            return Result::ok(true);
        } catch (PDOException $e) {
            throw new RuntimeException('Error al actualizar el café: ' . $e->getMessage());
        }
    }

    /**
     * Activa/Desactiva un café
     *
     * @param integer $id ID del café
     * @return Result
     * @throws RuntimeException Si falla la actualización en base de datos
     */
    public function toggleActive(int $id): Result
    {
        $cafe = $this->cafeRepo->findById($id);
        if (!$cafe) {
            return Result::fail('Café no encontrado', 'not_found');
        }

        $newStatus = $cafe['is_active'] ? 0 : 1;

        try {
            $this->cafeRepo->update($id, ['is_active' => $newStatus]);

            // Log de auditoría del cambio de estado
            AuditLog::log('toggle_cafe_status', 'cafe', $id, null, ['is_active' => $newStatus]);

            return Result::ok(true);
        } catch (PDOException $e) {
            throw new RuntimeException('Error al cambiar el estado del café: ' . $e->getMessage());
        }
    }

    /**
     * Elimina (desactiva) un café
     *
     * @param integer $id ID del café
     * @return Result
     * @throws RuntimeException Si falla la eliminación en base de datos
     */
    public function delete(int $id): Result
    {
        $cafe = $this->cafeRepo->findById($id);
        if (!$cafe) {
            return Result::fail('Café no encontrado', 'not_found');
        }

        try {
            $this->cafeRepo->softDelete($id);

            // Log de auditoría de eliminación
            AuditLog::log('delete_cafe', 'cafe', $id, null, null);

            return Result::ok(true);
        } catch (PDOException $e) {
            throw new RuntimeException('Error al eliminar el café: ' . $e->getMessage());
        }
    }

    /**
     * Busca cafés por nombre o ubicación
     *
     * @param string  $query Término de búsqueda
     * @param integer $limit Límite de resultados
     * @return array Lista de cafés encontrados
     */
    public function search(string $query, int $limit = 20): array
    {
        $searchTerm = "%$query%";

        $sql = '
            SELECT *
            FROM cafes
            WHERE (name LIKE ? OR location LIKE ? OR animal_type LIKE ?)
            AND is_active = 1
            ORDER BY name
            LIMIT ?
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas de cafés
     *
     * @return (null|scalar)[]|false Estadísticas (total, activos, por categoría, etc.)
     *
     * @psalm-return array<string, null|scalar>|false
     */
    public function getStats(): array|false
    {
        $sql = '
            SELECT
                COUNT(*) as total,
                SUM(IF(is_active = 1, 1, 0)) as active,
                SUM(IF(has_reservations = 1, 1, 0)) as with_reservations,
                COUNT(DISTINCT category) as categories,
                COUNT(DISTINCT animal_type) as animal_types
            FROM cafes
        ';

        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }
}
