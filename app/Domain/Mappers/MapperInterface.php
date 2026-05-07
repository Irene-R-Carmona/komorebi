<?php

declare(strict_types=1);

namespace App\Domain\Mappers;

use App\Domain\DTO\DomainTransferObject;

/**
 * Contrato para los Mappers de dominio.
 *
 * Un Mapper convierte un array de PDO (FETCH_ASSOC) en un DTO tipado.
 * Las implementaciones son `final readonly class` sin estado inyectable.
 */
interface MapperInterface
{
    /**
     * Convierte una fila de PDO en el DTO correspondiente.
     *
     * @param array<string, mixed> $row
     */
    public function toDTO(array $row): DomainTransferObject;
}
