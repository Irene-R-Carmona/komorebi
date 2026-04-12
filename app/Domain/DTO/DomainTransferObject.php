<?php

declare(strict_types=1);

namespace App\Domain\DTO;

interface DomainTransferObject
{
    public static function fromArray(array $data): static;

    public function toViewArray(): array;
}
