<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Interfaz del repositorio de animales
 *
 * Define operaciones de acceso a datos de animales siguiendo
 * el principio de Inversión de Dependencias (SOLID).
 */
interface AnimalRepositoryInterface
{
    /**
     * Buscar un animal por su ID
     *
     * @param integer $id ID del animal
     * @return array|null Array con datos del animal o null si no existe
     *                    Campos: id, cafe_id, name, species_type, current_status, etc.
     */
    public function findById(int $id): ?array;

    /**
     * Obtener todos los animales activos de un café
     *
     * @param integer $cafeId ID del café
     * @return array Lista de animales activos
     */
    public function findActiveByCafe(int $cafeId): array;

    /**
     * Verificar si un animal está disponible para interacción
     *
     * @param integer $animalId ID del animal
     * @return boolean True si el animal está active y no bloqueado
     */
    public function isAvailable(int $animalId): bool;

    /**
     * Verificar si un animal está descansando o no disponible
     *
     * @param integer $animalId ID del animal
     * @return boolean True si el animal está descansando
     */
    public function isResting(int $animalId): bool;

    /**
     * Obtener animales con info de café y logs (optimizado - elimina N+1)
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAnimalsWithCafeInfoOptimized(): array;

    /**
     * Obtener estadísticas de salud de animales (una sola query)
     *
     * @return array{
     *   total_animals: int,
     *   healthy: int,
     *   monitoring: int,
     *   sick: int,
     *   logs_today: int
     * }
     */
    public function getHealthStatistics(): array;

    /**
     * Obtener logs recientes de cuidado
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 20): array;

    /**
     * Obtener incidentes activos
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveIncidents(): array;

    /**
     * Actualizar la URL de imagen de un animal.
     *
     * @param integer $animalId ID del animal
     * @param string $imageUrl URL de la nueva imagen
     * @return bool True si se actualizó correctamente
     */
    public function updateImageUrl(int $animalId, string $imageUrl): bool;
}
