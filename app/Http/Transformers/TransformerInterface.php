<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Contrato para todos los Transformers de API.
 *
 * Un Transformer convierte un array de datos crudos (fila de BD / resultado de servicio)
 * en un array listo para serializar como JSON, ocultando campos internos y
 * normalizando tipos.
 */
interface TransformerInterface
{
    /**
     * Transforma un único recurso.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function transform(array $data): array;

    /**
     * Transforma una colección de recursos.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function collection(array $items): array;
}
