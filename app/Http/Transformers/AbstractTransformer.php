<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * Base para todos los Transformers.
 *
 * Implementa `collection()` delegando en `transform()`.
 */
abstract class AbstractTransformer implements TransformerInterface
{
    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function collection(array $items): array
    {
        return \array_values(\array_map(fn(array $item) => $this->transform($item), $items));
    }
}
