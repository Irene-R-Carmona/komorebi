<?php

declare(strict_types=1);

namespace App\Core;

use PDOStatement;

/**
 * PDOStatement extendido que registra queries lentas.
 *
 * Se activa via PDO::ATTR_STATEMENT_CLASS en LoggingPDO.
 * Override de execute() añade medición con hrtime() y emite un log WARNING
 * si la duración supera el umbral configurado.
 */
final class LoggingPDOStatement extends PDOStatement
{
    /**
     * El constructor es protected porque PDO lo invoca internamente
     * al crear statements con ATTR_STATEMENT_CLASS.
     *
     * @param int $slowMs Umbral en milisegundos para considerar una query como lenta.
     */
    protected function __construct(private readonly int $slowMs)
    {
    }

    /**
     * Ejecuta el statement midiendo la duración con hrtime().
     * Si la duración supera $slowMs, emite Logger::warning.
     *
     * @param array<mixed>|null $params Parámetros de binding opcionales.
     */
    #[\Override]
    public function execute(?array $params = null): bool
    {
        $start = \hrtime(true);

        $result = parent::execute($params);

        $ms = (int) ((\hrtime(true) - $start) / 1_000_000);

        if ($ms >= $this->slowMs) {
            Logger::warning('[DB] Slow query', [
                'duration_ms' => $ms,
                'sql' => self::truncateSql($this->queryString),
            ]);
        }

        return $result;
    }

    /**
     * Trunca SQL a 500 caracteres para evitar logs excesivamente grandes.
     */
    private static function truncateSql(string $sql): string
    {
        if (\mb_strlen($sql) <= 500) {
            return $sql;
        }

        return \mb_substr($sql, 0, 497) . '...';
    }
}
