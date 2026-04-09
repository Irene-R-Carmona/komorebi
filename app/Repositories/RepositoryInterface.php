<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Interface base para todos los repositorios.
 *
 * Define operaciones CRUD comunes que todos los repositorios deben implementar.
 * Los repositorios encapsulan la lógica de acceso a datos, separándola de la lógica de negocio.
 */
interface RepositoryInterface
{
    /**
     * Buscar un registro por su ID.
     *
     * @param int $id
     * @return array|null Array asociativo con los datos o null si no existe
     */
    public function findById(int $id): ?array;

    /**
     * Obtener todos los registros.
     *
     * @return array Lista de registros
     */
    public function findAll(): array;

    /**
     * Crear un nuevo registro.
     *
     * @param array $data Datos del registro
     * @return int ID del registro creado
     */
    public function create(array $data): int;

    /**
     * Actualizar un registro existente.
     *
     * @param int $id ID del registro
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update(int $id, array $data): bool;

    /**
     * Eliminar un registro (soft delete si aplica).
     *
     * @param int $id ID del registro
     * @return bool True si se eliminó correctamente
     */
    public function delete(int $id): bool;

    /**
     * Verificar si existe un registro.
     *
     * @param int $id ID del registro
     * @return bool True si existe
     */
    public function exists(int $id): bool;
}
