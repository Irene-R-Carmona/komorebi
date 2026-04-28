<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Value object inmutable para paginación offset-based.
 *
 * Usa la técnica del "sentinel row" (+1 fetchLimit) para detectar
 * si existe una página siguiente sin necesidad de un COUNT(*) extra.
 */
final readonly class Pagination
{
    public const int MAX_LIMIT   = 100;
    public const int DEFAULT_LIMIT = 20;

    public readonly int $page;
    public readonly int $limit;
    public readonly int $offset;
    /** Número de filas a pedir a la BD: limit + 1 para detectar hasNextPage */
    public readonly int $fetchLimit;

    private function __construct(int $page, int $limit)
    {
        $this->page       = \max(1, $page);
        $this->limit      = \min(\max(1, $limit), self::MAX_LIMIT);
        $this->offset     = ($this->page - 1) * $this->limit;
        $this->fetchLimit = $this->limit + 1;
    }

    public static function fromRequest(int $page, int $limit): self
    {
        return new self($page, $limit);
    }

    /**
     * ¿Hay una página siguiente?
     *
     * @param int $rowsReturned Número de filas devueltas por la BD (usando fetchLimit).
     *                          Si es > limit, hay más resultados.
     */
    public function hasNextPage(int $rowsReturned): bool
    {
        return $rowsReturned > $this->limit;
    }

    /**
     * Metadatos para incluir en el envelope de la API.
     *
     * @param bool $hasNextPage Resultado de hasNextPage() tras la consulta.
     * @return array<string, mixed>
     */
    public function toMeta(bool $hasNextPage): array
    {
        return [
            'page'          => $this->page,
            'limit'         => $this->limit,
            'has_next_page' => $hasNextPage,
        ];
    }
}
