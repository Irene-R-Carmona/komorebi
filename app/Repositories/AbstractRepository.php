<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repositorio base abstracto con operaciones CRUD comunes.
 *
 * Los repositorios concretos extienden esta clase e implementan
 * lógica específica de cada entidad.
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Obtener el nombre de la tabla.
     */
    abstract protected function getTable(): string;

    /**
     * Obtener los campos para SELECT (evita SELECT *).
     */
    abstract protected function getSelectFields(): array;

    #[\Override]
    public function findById(int $id): ?array
    {
        $fields = implode(', ', $this->getSelectFields());
        $table = $this->getTable();

        $stmt = $this->db->prepare(
            "SELECT $fields FROM $table WHERE $this->primaryKey = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : (array) $result;
    }

    #[\Override]
    public function findAll(): array
    {
        $fields = implode(', ', $this->getSelectFields());
        $table = $this->getTable();

        return $this->db->query("SELECT $fields FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }

    #[\Override]
    public function exists(int $id): bool
    {
        $table = $this->getTable();

        $stmt = $this->db->prepare(
            "SELECT 1 FROM $table WHERE $this->primaryKey = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        return (bool) $stmt->fetch();
    }

    #[\Override]
    public function create(array $data): int
    {
        $table = $this->getTable();
        $fields = array_keys($data);
        $placeholders = array_map(static fn($f) => ":$f", $fields);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    #[\Override]
    public function update(int $id, array $data): bool
    {
        $table = $this->getTable();
        $fields = array_keys($data);
        $setParts = array_map(static fn($f) => "$f = :$f", $fields);

        $sql = sprintf(
            "UPDATE %s SET %s WHERE $this->primaryKey = :id",
            $table,
            implode(', ', $setParts)
        );

        $data['id'] = $id;
        return $this->db->prepare($sql)->execute($data);
    }

    #[\Override]
    public function delete(int $id): bool
    {
        $table = $this->getTable();

        $stmt = $this->db->prepare(
            "DELETE FROM $table WHERE $this->primaryKey = :id"
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Soft delete (marca como eliminado sin borrar físicamente).
     */
    public function softDelete(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Contar registros con condiciones opcionales.
     */
    protected function count(array $conditions = []): int
    {
        $table = $this->getTable();
        $sql = "SELECT COUNT(*) as total FROM $table";

        if (!empty($conditions)) {
            $whereParts = array_map(static fn($f) => "$f = :$f", array_keys($conditions));
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);

        return (int) $stmt->fetchColumn();
    }
}
