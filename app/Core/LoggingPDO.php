<?php

declare(strict_types=1);

namespace App\Core;

use Override;
use PDO;
use PDOStatement;

/**
 * PDO extendido que registra queries lentas.
 *
 * Inyecta LoggingPDOStatement via ATTR_STATEMENT_CLASS para interceptar
 * todas las queries preparadas con prepare() + execute().
 *
 * También mide directamente exec() y query() (queries sin preparar).
 */
final class LoggingPDO extends PDO
{
    /**
     * @param string             $dsn
     * @param string|null        $username
     * @param string|null        $password
     * @param array<int, mixed>  $options   Opciones PDO adicionales; ATTR_STATEMENT_CLASS
     *                                      se añade automáticamente.
     */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ) {
        $slowMs = Env::int('DB_SLOW_QUERY_MS', 100);

        $options[PDO::ATTR_STATEMENT_CLASS] = [LoggingPDOStatement::class, [$slowMs]];

        parent::__construct($dsn, $username, $password, $options);

        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LoggingPDOStatement::class, [$slowMs]]);
    }

    /**
     * @inheritDoc
     * Mide la duración de exec() y loguea si supera DB_SLOW_QUERY_MS.
     */
    #[Override]
    public function exec(string $statement): int|false
    {
        $slowMs = Env::int('DB_SLOW_QUERY_MS', 100);
        $start = \hrtime(true);

        $result = parent::exec($statement);

        $ms = (int) ((\hrtime(true) - $start) / 1_000_000);

        if ($slowMs > 0 && $ms >= $slowMs) {
            Logger::warning('[DB] Slow query (exec)', [
                'duration_ms' => $ms,
                'sql' => \mb_strlen($statement) <= 500
                    ? $statement
                    : \mb_substr($statement, 0, 497) . '...',
            ]);
        }

        return $result;
    }

    /**
     * @inheritDoc
     * Mide la duración de query() y loguea si supera DB_SLOW_QUERY_MS.
     *
     * @param string     $query
     * @param int|null   $fetchMode
     * @param mixed      ...$fetchModeArgs
     */
    #[Override]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $slowMs = Env::int('DB_SLOW_QUERY_MS', 100);
        $start = \hrtime(true);

        $result = $fetchMode !== null
            ? parent::query($query, $fetchMode, ...$fetchModeArgs)
            : parent::query($query);

        $ms = (int) ((\hrtime(true) - $start) / 1_000_000);

        if ($slowMs > 0 && $ms >= $slowMs) {
            Logger::warning('[DB] Slow query (query)', [
                'duration_ms' => $ms,
                'sql' => \mb_strlen($query) <= 500
                    ? $query
                    : \mb_substr($query, 0, 497) . '...',
            ]);
        }

        return $result;
    }
}
