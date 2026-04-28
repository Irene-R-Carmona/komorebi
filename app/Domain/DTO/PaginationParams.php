<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Core\Pagination;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Parámetros de paginación, búsqueda y ordenamiento extraídos de una request GET.
 * Inmutable. Toda validación y sanitización ocurre en fromRequest().
 */
final readonly class PaginationParams
{
    public const string DIR_ASC  = 'asc';
    public const string DIR_DESC = 'desc';

    private function __construct(
        public string $search,
        public string $sort,
        public string $dir,
        public int    $page,
    ) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $q = $request->getQueryParams();

        $search = \trim((string) ($q['search'] ?? ''));
        $sort   = \trim((string) ($q['sort']   ?? ''));
        $dir    = \strtolower(\trim((string) ($q['dir'] ?? self::DIR_ASC)));
        $page   = \max(1, (int) ($q['page'] ?? 1));

        return new self(
            search: $search,
            sort:   $sort,
            dir:    $dir === self::DIR_DESC ? self::DIR_DESC : self::DIR_ASC,
            page:   $page,
        );
    }

    public function toPagination(int $limit = Pagination::DEFAULT_LIMIT): Pagination
    {
        return Pagination::fromRequest($this->page, $limit);
    }

    /**
     * Devuelve array de query params para construir URLs de sort/paginación.
     * Se fusiona con otros parámetros específicos del panel (status, role, etc.).
     *
     * @param array<string, string> $extra Parámetros adicionales del panel
     * @return array<string, string>
     */
    public function toQueryArray(array $extra = []): array
    {
        $base = ['search' => $this->search, 'sort' => $this->sort, 'dir' => $this->dir];

        return \array_filter(\array_merge($base, $extra), static fn($v) => $v !== '');
    }
}
