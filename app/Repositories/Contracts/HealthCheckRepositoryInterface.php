<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\AnimalHealthCheckDTO;
use PDOException;

/**
 * Interfaz para el repositorio de chequeos de salud animal.
 * Define operaciones CRUD y consultas específicas del sistema de health checks.
 *
 * @package App\Repositories\Contracts
 */
interface HealthCheckRepositoryInterface
{
    /**
     * Obtener un chequeo por su ID.
     *
     * @param int $id ID del chequeo
     */
    public function findById(int $id): ?AnimalHealthCheckDTO;

    /**
     * Obtener el chequeo de un animal para una fecha específica.
     *
     * @param int $animalId ID del animal
     * @param string|null $date Fecha en formato Y-m-d (default: hoy)
     */
    public function findByAnimalAndDate(int $animalId, ?string $date = null): ?AnimalHealthCheckDTO;

    /**
     * Obtener el chequeo de hoy para un animal.
     *
     * @param int $animalId ID del animal
     */
    public function findTodayByAnimalId(int $animalId): ?AnimalHealthCheckDTO;

    /**
     * Obtener historial de chequeos de un animal.
     *
     * @param int $animalId ID del animal
     * @param int $limit Número máximo de resultados (default: 30)
     * @return array Lista de chequeos ordenados por fecha descendente
     */
    public function getCheckHistory(int $animalId, int $limit = 30): array;

    /**
     * Obtener todos los chequeos realizados hoy.
     * Usa la vista health_checks_today para optimización.
     *
     * @return array Lista de chequeos de hoy con datos del animal y keeper
     */
    public function getTodayChecks(): array;

    /**
     * Obtener animales que aún no tienen chequeo hoy.
     * Usa la vista animals_pending_check_today.
     *
     * @param int|null $cafeId Filtrar por café específico (opcional)
     * @return array Lista de animales pendientes de chequeo
     */
    public function getPendingAnimals(?int $cafeId = null): array;

    /**
     * Obtener chequeos con alertas activas (últimos N días).
     *
     * @param int $days Número de días hacia atrás (default: 7)
     * @return array Lista de chequeos con alertas
     */
    public function getCheckswithAlerts(int $days = 7): array;

    /**
     * Crear un nuevo chequeo de salud.
     *
     * @param array $data Datos del chequeo
     * @return int ID del chequeo creado
     * @throws PDOException Si falla la inserción
     */
    public function create(array $data): int;

    /**
     * Verificar si existe un chequeo para un animal en una fecha.
     *
     * @param int $animalId ID del animal
     * @param string $date Fecha en formato Y-m-d
     * @return bool True si existe, false en caso contrario
     */
    public function existsForAnimalOnDate(int $animalId, string $date): bool;

    /**
     * Contar chequeos realizados por un keeper en un rango de fechas.
     *
     * @param int $keeperId ID del keeper
     * @param string|null $startDate Fecha inicio (default: inicio del mes actual)
     * @param string|null $endDate Fecha fin (default: hoy)
     * @return int Número de chequeos realizados
     */
    public function countByKeeperInPeriod(int $keeperId, ?string $startDate = null, ?string $endDate = null): int;

    /**
     * Obtener estadísticas de alertas para el dashboard.
     *
     * @param int $days Número de días hacia atrás (default: 7)
     * @return array Estadísticas agrupadas por tipo de alerta
     */
    public function getAlertStatistics(int $days = 7): array;

    /**
     * Obtener logs recientes de las últimas 24 horas.
     *
     * @param int $limit Número máximo de resultados
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 20): array;

    /**
     * Registrar un cuidado simple (upsert por día).
     *
     * @param array $data Debe contener: animal_id, notes, logged_by_user_id
     * @return int ID del registro creado o actualizado
     */
    public function createCareLog(array $data): int;
}
