<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Pagination;
use Override;
use PDO;
use PDOStatement;
use Throwable;

abstract class AbstractRepository implements RepositoryInterface
{
    private const string SQL_WHERE = ' WHERE ';

    protected ?PDO $db = null;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    protected function getDb(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    /**
     * Ejecuta un callback dentro de una transacción usando $this->getDb().
     * Si ya hay una transacción activa, ejecuta el callback directamente.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function transact(callable $callback): mixed
    {
        $pdo = $this->getDb();
        if ($pdo->inTransaction()) {
            return $callback();
        }
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    abstract protected function getTable(): string;

    /**
     * Returns ONLY presentation-safe fields: NO passwords, IPs, credentials,
     * operational/internal fields, or recipe data.
     * For sensitive field access, define explicit named methods in the concrete repository.
     */
    abstract protected function getSelectFields(): array;

    #[Override]
    public function findById(int $id): mixed
    {
        return $this->findByIdRaw($id);
    }

    protected function findByIdRaw(int $id): ?array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $table = $this->getTable();
        $sql = "SELECT $fields FROM $table WHERE $this->primaryKey = :id LIMIT 1";
        $params = ['id' => $id];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : (array) $result;
    }

    #[Override]
    public function findAll(): array
    {
        return $this->findAllRaw();
    }

    protected function findAllRaw(): array
    {
        $fields = \implode(', ', $this->getSelectFields());
        $table = $this->getTable();
        $sql = "SELECT $fields FROM $table";

        /** @var PDOStatement $stmt */
        $stmt = $this->execTimed(fn() => $this->getDb()->query($sql), $sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[Override]
    public function exists(int $id): bool
    {
        $table = $this->getTable();
        $sql = "SELECT 1 FROM $table WHERE $this->primaryKey = :id LIMIT 1";
        $params = ['id' => $id];

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($params), $sql, $params);

        return (bool) $stmt->fetch();
    }

    #[Override]
    public function create(array $data): int
    {
        $table = $this->getTable();
        $fields = \array_keys($data);
        $placeholders = \array_map(static fn($f) => ":$f", $fields);

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            \implode(', ', $fields),
            \implode(', ', $placeholders)
        );

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(fn() => $stmt->execute($data), $sql, $data);

        return (int) $this->getDb()->lastInsertId();
    }

    #[Override]
    public function update(int $id, array $data): bool
    {
        $table = $this->getTable();
        $fields = \array_keys($data);
        $setParts = \array_map(static fn($f) => "$f = :$f", $fields);

        $sql = \sprintf(
            "UPDATE %s SET %s WHERE $this->primaryKey = :id",
            $table,
            \implode(', ', $setParts)
        );

        $data['id'] = $id;

        return (bool) $this->execTimed(
            fn() => $this->getDb()->prepare($sql)->execute($data),
            $sql,
            $data
        );
    }

    #[Override]
    public function delete(int $id): bool
    {
        $table = $this->getTable();
        $sql = "DELETE FROM $table WHERE $this->primaryKey = :id";
        $params = ['id' => $id];

        $stmt = $this->getDb()->prepare($sql);

        return (bool) $this->execTimed(fn() => $stmt->execute($params), $sql, $params);
    }

    /**
     * Ejecuta un callable midiendo su duración. Registra en canal 'db' si supera umbrales.
     * NUNCA loguea los parámetros completos (pueden contener PII) — solo el count.
     */
    protected function execTimed(callable $fn, string $sql, array $params = []): mixed
    {
        $start = \hrtime(true);
        $result = $fn();
        $ms = (\hrtime(true) - $start) / 1_000_000;

        if ($ms > 500) {
            Logger::channel('db')->error('[DB] Slow query', [
                'sql' => \substr($sql, 0, 200),
                'params_count' => \count($params),
                'duration_ms' => \round($ms, 2),
            ]);
        } elseif ($ms > 100) {
            Logger::channel('db')->warning('[DB] Slow query', [
                'sql' => \substr($sql, 0, 200),
                'params_count' => \count($params),
                'duration_ms' => \round($ms, 2),
            ]);
        }

        return $result;
    }

    public function softDelete(int $id): bool
    {
        return $this->update($id, ['deleted_at' => \date('Y-m-d H:i:s')]);
    }

    protected function count(array $conditions = []): int
    {
        $table = $this->getTable();
        $sql = "SELECT COUNT(*) as total FROM $table";

        if (!empty($conditions)) {
            $whereParts = \array_map(static fn($f) => "$f = :$f", \array_keys($conditions));
            $sql .= self::SQL_WHERE . \implode(' AND ', $whereParts);
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($conditions);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve filas paginadas usando el patrón sentinel (fetchLimit = limit + 1).
     *
     * El array retornado puede contener hasta fetchLimit filas; el llamador
     * debe invocar Pagination::hasNextPage() y descartar la fila extra.
     *
     * @param array<string, mixed> $conditions  Condiciones WHERE exactas (columna => valor)
     * @param string               $search      Término de búsqueda LIKE (vacío = sin búsqueda)
     * @param array<int, string>   $searchColumns Columnas donde aplicar LIKE
     * @param string               $sort        Columna de ordenamiento (debe estar en $sortWhitelist)
     * @param string               $sortDir     'asc' o 'desc'
     * @param array<int, string>   $sortWhitelist Columnas permitidas para ordenamiento
     * @return array<int, array<string, mixed>>
     */
    protected function findPaginated(
        Pagination $pagination,
        array $conditions = [],
        string $search = '',
        array $searchColumns = [],
        string $sort = '',
        string $sortDir = 'asc',
        array $sortWhitelist = [],
    ): array {
        $fields = \implode(', ', $this->getSelectFields());
        $table = $this->getTable();
        $sql = "SELECT $fields FROM $table";

        [$whereSql, $params] = $this->buildWhere($conditions, $search, $searchColumns);
        if ($whereSql !== '') {
            $sql .= self::SQL_WHERE . $whereSql;
        }

        if ($sort !== '' && \in_array($sort, $sortWhitelist, true)) {
            $dir = \strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
            $sql .= " ORDER BY $sort $dir";
        }

        $sql .= ' LIMIT :limit OFFSET :offset';
        $params['limit'] = $pagination->fetchLimit;
        $params['offset'] = $pagination->offset;

        $stmt = $this->getDb()->prepare($sql);
        $this->execTimed(static fn() => $stmt->execute($params), $sql, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta registros aplicando los mismos filtros que findPaginated().
     *
     * @param array<string, mixed> $conditions
     * @param array<int, string>   $searchColumns
     */
    protected function countFiltered(
        array $conditions = [],
        string $search = '',
        array $searchColumns = [],
    ): int {
        $table = $this->getTable();
        $sql = "SELECT COUNT(*) FROM $table";

        [$whereSql, $params] = $this->buildWhere($conditions, $search, $searchColumns);
        if ($whereSql !== '') {
            $sql .= self::SQL_WHERE . $whereSql;
        }

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Construye la cláusula WHERE con condiciones exactas y búsqueda LIKE opcional.
     *
     * @param array<string, mixed> $conditions
     * @param array<int, string>   $searchColumns
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $conditions, string $search, array $searchColumns): array
    {
        $parts = [];
        $params = [];

        foreach ($conditions as $col => $val) {
            $parts[] = "$col = :cond_$col";
            $params["cond_$col"] = $val;
        }

        $term = \trim($search);
        if ($term !== '' && !empty($searchColumns)) {
            $likeParts = [];
            foreach ($searchColumns as $col) {
                $key = 'search_' . \str_replace('.', '_', $col);
                $likeParts[] = "$col LIKE :$key";
                $params[$key] = '%' . $term . '%';
            }
            $parts[] = '(' . \implode(' OR ', $likeParts) . ')';
        }

        return [\implode(' AND ', $parts), $params];
    }
}
