<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\DTO\PaginationParams;

/**
 * Helpers de presentación para vistas SSR.
 * Funciones puras — sin estado, sin efectos secundarios.
 */
final class ViewHelpers
{
    /**
     * Genera un <a> de ordenamiento para cabeceras de tabla.
     *
     * Alterna dir cuando el campo ya es el activo; usa 'asc' para campos nuevos.
     * Preserva todos los query params actuales (search, filtros de panel, página → 1).
     *
     * @param array<string, string> $currentParams Query params actuales (de PaginationParams::toQueryArray())
     */
    public static function sortLink(string $label, string $field, array $currentParams): string
    {
        $isActive = ($currentParams['sort'] ?? '') === $field;
        $newDir   = ($isActive && ($currentParams['dir'] ?? 'asc') === 'asc')
            ? PaginationParams::DIR_DESC
            : PaginationParams::DIR_ASC;

        $params = \array_merge($currentParams, ['sort' => $field, 'dir' => $newDir, 'page' => '1']);
        $url    = '?' . \http_build_query($params);

        $arrow = '';
        if ($isActive) {
            $arrow = $newDir === PaginationParams::DIR_ASC
                ? ' <span aria-hidden="true">↓</span>'
                : ' <span aria-hidden="true">↑</span>';
        }

        $escaped = \htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return "<a href=\"{$url}\" class=\"sort-link" . ($isActive ? ' sort-active' : '') . "\">{$escaped}{$arrow}</a>";
    }

    /**
     * Genera controles de paginación prev/next.
     *
     * Usa el patrón sentinel: $meta['has_next_page'] indica si hay página siguiente.
     * Preserva todos los query params actuales (search, sort, dir, filtros de panel).
     *
     * @param array{page: int, has_next_page: bool} $meta    Resultado de Pagination::toMeta()
     * @param array<string, string>                 $currentParams Query params actuales
     */
    public static function paginationLinks(array $meta, array $currentParams): string
    {
        $page        = (int) ($meta['page'] ?? 1);
        $hasNextPage = (bool) ($meta['has_next_page'] ?? false);

        if ($page <= 1 && !$hasNextPage) {
            return '';
        }

        $html = '<nav class="pagination" aria-label="Paginación">';

        if ($page > 1) {
            $params = \array_merge($currentParams, ['page' => (string) ($page - 1)]);
            $url    = '?' . \http_build_query($params);
            $html  .= "<a href=\"{$url}\" class=\"pagination-prev\">&laquo; Anterior</a>";
        }

        $html .= "<span class=\"pagination-current\">Página {$page}</span>";

        if ($hasNextPage) {
            $params = \array_merge($currentParams, ['page' => (string) ($page + 1)]);
            $url    = '?' . \http_build_query($params);
            $html  .= "<a href=\"{$url}\" class=\"pagination-next\">Siguiente &raquo;</a>";
        }

        $html .= '</nav>';

        return $html;
    }
}
