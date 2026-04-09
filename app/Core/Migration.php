<?php

declare(strict_types=1);

namespace App\Core;

use Exception;
use PDO;
use RuntimeException;

/**
 * Sistema de Migraciones
 *
 * Ejecuta migraciones SQL en orden secuencial.
 * Mantiene registro en tabla `migrations`.
 *
 * Uso:
 * - Colocar archivos SQL numerados en migrations/
 * - Ejecutar: Migration::run()
 * - Rollback: Migration::rollback()
 */
final class Migration
{
    private PDO $db;

    private string $migrationsPath;

    public function __construct(?string $path = null)
    {
        $this->db = Database::getConnection();
        $this->migrationsPath = $path ?? __DIR__ . '/../../migrations';
    }

    /**
     * Ejecuta todas las migraciones pendientes.
     *
     * @throws RuntimeException
     *
     * @return array<string> Migraciones ejecutadas
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();

        $executed = [];
        $files = $this->getMigrationFiles();

        foreach ($files as $file) {
            $name = \basename($file, '.sql');

            if ($this->isMigrationExecuted($name)) {
                continue;
            }

            try {
                $sql = \file_get_contents($file);

                if ($sql === false) {
                    throw new RuntimeException("No se puede leer: $file");
                }

                // Ejecutar cada statement (separados por ;)
                foreach (\explode(';', $sql) as $statement) {
                    $statement = \trim($statement);

                    if ($statement !== '') {
                        $this->db->exec($statement);
                    }
                }

                $this->recordMigration($name);
                $executed[] = $name;

                Logger::info('Migración ejecutada', [
                    'migration' => $name,
                ]);
            } catch (Exception $e) {
                Logger::error('Error en migración', [
                    'migration' => $name,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException("Error en migración $name: " . $e->getMessage());
            }
        }

        return $executed;
    }

    /**
     * Rollback de última migración.
     *
     * @throws RuntimeException
     *
     * @return string|null Migración revertida
     */
    public function rollback(): ?string
    {
        $this->ensureMigrationsTable();

        // Obtener última migración ejecutada
        $stmt = $this->db->query(
            'SELECT batch, migration FROM migrations ORDER BY batch DESC, migration DESC LIMIT 1'
        );
        $last = $stmt->fetch();

        if (!$last) {
            return null; // No hay migraciones
        }

        $name = $last['migration'];
        $rollbackFile = $this->migrationsPath . "/rollback_$name.sql";

        if (!\file_exists($rollbackFile)) {
            throw new RuntimeException("No existe rollback para: $name");
        }

        try {
            $sql = \file_get_contents($rollbackFile);

            if ($sql === false) {
                throw new RuntimeException("No se puede leer: $rollbackFile");
            }

            foreach (\explode(';', $sql) as $statement) {
                $statement = \trim($statement);

                if ($statement !== '') {
                    $this->db->exec($statement);
                }
            }

            $this->removeMigration($name);
            Logger::info('Rollback ejecutado', [
                'migration' => $name,
            ]);

            return $name;
        } catch (Exception $e) {
            throw new RuntimeException("Error en rollback $name: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estado de migraciones.
     *
     * @return array<array{migration: string, batch: int, executed_at: string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $stmt = $this->db->query(
            'SELECT migration, batch, executed_at FROM migrations ORDER BY batch, migration'
        );

        return $stmt->fetchAll();
    }

    // ─────────────────────────────────────────────────────────────
    // Métodos privados
    // ─────────────────────────────────────────────────────────────

    private function ensureMigrationsTable(): void
    {
        $sql = <<<SQL
                CREATE TABLE IF NOT EXISTS migrations (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT UNSIGNED NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL;

        $this->db->exec($sql);
    }

    private function getMigrationFiles(): array
    {
        if (!\is_dir($this->migrationsPath) && !\mkdir($concurrentDirectory = $this->migrationsPath, 0o755, true) && !\is_dir($concurrentDirectory)) {
            throw new RuntimeException(\sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $files = \glob($this->migrationsPath . '/*.sql');

        if ($files === false) {
            return [];
        }

        // Filtrar solo migraciones (no rollbacks)
        $files = \array_filter($files, static fn ($f) => !\str_contains($f, 'rollback_'));

        // Ordenar por nombre (numeración)
        \sort($files);

        return $files;
    }

    private function isMigrationExecuted(string $name): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM migrations WHERE migration = :name LIMIT 1'
        );
        $stmt->execute(['name' => $name]);

        return $stmt->fetch() !== false;
    }

    private function recordMigration(string $name): void
    {
        $batch = $this->getNextBatch();

        $stmt = $this->db->prepare(
            'INSERT INTO migrations (migration, batch) VALUES (:name, :batch)'
        );
        $stmt->execute(['name' => $name, 'batch' => $batch]);
    }

    private function removeMigration(string $name): void
    {
        $stmt = $this->db->prepare('DELETE FROM migrations WHERE migration = :name');
        $stmt->execute(['name' => $name]);
    }

    private function getNextBatch(): int
    {
        $stmt = $this->db->query('SELECT MAX(batch) as max_batch FROM migrations');
        $result = $stmt->fetch();

        return ($result['max_batch'] ?? 0) + 1;
    }
}
