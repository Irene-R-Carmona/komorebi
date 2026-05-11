<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface PassInclusionRepositoryInterface
{
    /**
     * Devuelve las inclusiones de un pase con datos de categoría.
     *
     * @return array<int, array{
     *     id: int,
     *     pass_product_id: int,
     *     category_id: int,
     *     quantity_per_pax: int,
     *     max_unit_price: int|null,
     *     category_name: string,
     *     category_slug: string
     * }>
     */
    public function findByPassId(int $passProductId): array;

    /**
     * Devuelve las inclusiones de varios pases en una sola consulta.
     * El array resultado está indexado por pass_product_id.
     *
     * @param  int[]  $ids
     * @return array<int, list<array{id:int,pass_product_id:int,category_id:int,quantity_per_pax:int,max_unit_price:int|null,category_name:string,category_slug:string}>>
     */
    public function findByPassIds(array $ids): array;
}
